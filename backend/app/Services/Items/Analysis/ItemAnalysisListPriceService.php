<?php

namespace App\Services\Items\Analysis;

use App\Models\FndrCalculationPercentage;
use App\Models\GeneralCalculationPercentage;
use App\Models\Item;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ItemAnalysisListPriceService
{
    private const MISSING_PERCENTAGES_MESSAGE = 'uno de los parametros de porcentaje no esta configurado adecuadamente';

    public function calculate(Item $item, string $mode): float
    {
        $price = $this->resolve($item, $mode);

        if ($price['value'] === null) {
            throw new InvalidArgumentException(self::MISSING_PERCENTAGES_MESSAGE);
        }

        return $price['value'];
    }

    public function resolve(Item $item, string $mode): array
    {
        return match (strtolower($mode)) {
            'general' => $this->calculateGeneral($item),
            'fndr' => $this->calculateFndr($item),
            default => throw new InvalidArgumentException('Modo de listado no soportado.'),
        };
    }

    private function calculateGeneral(Item $item): array
    {
        try {
            $percentages = $this->resolveGeneralPercentages();
        } catch (InvalidArgumentException) {
            return [
                'value' => null,
                'label' => self::MISSING_PERCENTAGES_MESSAGE,
            ];
        }

        return $this->calculateWithPercentages($item, $percentages);
    }

    private function calculateFndr(Item $item): array
    {
        try {
            $percentages = $this->resolveFndrPercentages();
        } catch (InvalidArgumentException) {
            return [
                'value' => null,
                'label' => self::MISSING_PERCENTAGES_MESSAGE,
            ];
        }

        return $this->calculateWithPercentages($item, $percentages);
    }

    private function calculateWithPercentages(Item $item, array $percentages): array
    {
        $materialsTotal = $this->totalByType($item, 1);
        $laborBaseTotal = $this->totalByType($item, 2);
        $toolsBaseTotal = $this->totalByType($item, 3);

        $socialChargesAmount = ($laborBaseTotal * $percentages['social_charges']) / 100;
        $laborVatAmount = (($laborBaseTotal + $socialChargesAmount) * $percentages['vat']) / 100;
        $laborTotal = $laborBaseTotal + $socialChargesAmount + $laborVatAmount;

        $minorToolsAmount = ($laborTotal * $percentages['minor_tools']) / 100;
        $toolsTotal = $toolsBaseTotal + $minorToolsAmount;

        $directCostTotal = $materialsTotal + $laborTotal + $toolsTotal;
        $administrationAmount = ($directCostTotal * $percentages['administration']) / 100;
        $utilityAmount = (($directCostTotal + $administrationAmount) * $percentages['utility']) / 100;
        $subtotalTotal = $directCostTotal + $administrationAmount + $utilityAmount;
        $transactionTaxAmount = ($subtotalTotal * $percentages['transaction_tax']) / 100;

        $total = $subtotalTotal + $transactionTaxAmount;

        return [
            'value' => round($total, 2),
            'label' => number_format((float) $total, 2, ',', '.'),
        ];
    }

    private function totalByType(Item $item, int $type): float
    {
        $query = DB::table('item_insumo')
            ->join('insumo', 'insumo.id_insumo', '=', 'item_insumo.id_insumo')
            ->where('item_insumo.id_item', $item->id_item)
            ->where('item_insumo.estado', 'AC')
            ->where('insumo.tipo', $type);

        if (in_array($type, [2, 3], true)) {
            $query->join('log_insumo', 'log_insumo.id_insumo', '=', 'item_insumo.id_insumo');
        }

        $rows = $query->select(['item_insumo.cantidad', 'insumo.precio'])->get();

        return $rows->sum(fn ($row): float => ((float) $row->cantidad) * ((float) $row->precio));
    }

    private function resolveFndrPercentages(): array
    {
        return $this->resolvePercentages(FndrCalculationPercentage::class);
    }

    private function resolveGeneralPercentages(): array
    {
        return $this->resolvePercentages(GeneralCalculationPercentage::class);
    }

    private function resolvePercentages(string $percentageModel): array
    {
        $map = [];

        $percentageModel::query()
            ->active()
            ->get()
            ->each(function ($percentage) use (&$map): void {
                $description = strtoupper(trim((string) $percentage->descripcion));

                if (str_contains($description, 'CARGAS SOCIALES')) {
                    $map['social_charges'] = (float) $percentage->porcentaje;
                }

                if (str_contains($description, 'IMPUESTO AL VALOR AGREGADO')) {
                    $map['vat'] = (float) $percentage->porcentaje;
                }

                if (str_contains($description, 'HERRAMIENTAS MENORES')) {
                    $map['minor_tools'] = (float) $percentage->porcentaje;
                }

                if (str_contains($description, 'GASTOS GRALES Y ADMINISTRATIVOS')) {
                    $map['administration'] = (float) $percentage->porcentaje;
                }

                if (str_contains($description, 'UTILIDAD')) {
                    $map['utility'] = (float) $percentage->porcentaje;
                }

                if (str_contains($description, 'IMPUESTO A LAS TRANSACCIONES')) {
                    $map['transaction_tax'] = (float) $percentage->porcentaje;
                }
            });

        foreach (['social_charges', 'vat', 'minor_tools', 'administration', 'utility', 'transaction_tax'] as $key) {
            if (! array_key_exists($key, $map)) {
                throw new InvalidArgumentException('Uno de los parametros de porcentaje del modo solicitado no esta configurado adecuadamente.');
            }
        }

        return $map;
    }
}

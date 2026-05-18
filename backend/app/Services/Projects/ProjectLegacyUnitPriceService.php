<?php

namespace App\Services\Projects;

use App\Models\FndrCalculationPercentage;
use App\Models\FpsCalculationPercentage;
use App\Models\GeneralCalculationPercentage;
use App\Models\Item;
use App\Models\ObrasCalculationPercentage;
use App\Models\UpreCalculationPercentage;
use Illuminate\Support\Facades\DB;

class ProjectLegacyUnitPriceService
{
    public function resolve(Item $item, string $format): float
    {
        $materials = $this->subtotalByType($item, 1);
        $labor = $this->subtotalByType($item, 2);
        $machinery = $this->subtotalByType($item, 3);
        $percentages = $this->resolvePercentages($format);

        if (! $this->hasRequiredPercentages($percentages)) {
            return 0.0;
        }

        $socialCharges = $labor * $percentages['cs'] / 100;
        $iva = ($labor + $socialCharges) * $percentages['iva'] / 100;
        $laborTotal = $labor + $socialCharges + $iva;
        $minorTools = $laborTotal * $percentages['hm'] / 100;
        $machineryTotal = $machinery + $minorTools;
        $directCost = $materials + $laborTotal + $machineryTotal;
        $administration = $directCost * $percentages['adm'] / 100;
        $utility = ($directCost + $administration) * $percentages['util'] / 100;
        $subtotal = $directCost + $administration + $utility;
        $transactionsTax = $subtotal * $percentages['it'] / 100;

        return $subtotal + $transactionsTax;
    }

    private function subtotalByType(Item $item, int $type): float
    {
        $rows = DB::table('item_insumo')
            ->join('insumo', 'insumo.id_insumo', '=', 'item_insumo.id_insumo')
            ->where('item_insumo.estado', 'AC')
            ->where('item_insumo.id_item', $item->id_item)
            ->where('insumo.tipo', $type)
            ->select(['item_insumo.cantidad', 'insumo.precio'])
            ->get();

        return $rows->sum(fn ($row): float => (float) $row->cantidad * (float) $row->precio);
    }

    private function resolvePercentages(string $format): array
    {
        $model = match ($format) {
            'PC_FPS' => FpsCalculationPercentage::class,
            'PC_UPRE' => UpreCalculationPercentage::class,
            'PC_FNDR' => FndrCalculationPercentage::class,
            'PC_OBRAS' => ObrasCalculationPercentage::class,
            default => GeneralCalculationPercentage::class,
        };

        $resolved = [];

        foreach ($model::query()->active()->get() as $row) {
            $description = mb_strtoupper((string) $row->descripcion);

            if (str_contains($description, 'CARGAS SOCIALES')) {
                $resolved['cs'] = (float) $row->porcentaje;
            }
            if (str_contains($description, 'IMPUESTO AL VALOR AGREGADO')) {
                $resolved['iva'] = (float) $row->porcentaje;
            }
            if (str_contains($description, 'HERRAMIENTAS MENORES')) {
                $resolved['hm'] = (float) $row->porcentaje;
            }
            if (str_contains($description, 'GASTOS GRALES Y ADMINISTRATIVOS')) {
                $resolved['adm'] = (float) $row->porcentaje;
            }
            if (str_contains($description, 'UTILIDAD')) {
                $resolved['util'] = (float) $row->porcentaje;
            }
            if (str_contains($description, 'IMPUESTO A LAS TRANSACCIONES')) {
                $resolved['it'] = (float) $row->porcentaje;
            }
        }

        return $resolved;
    }

    private function hasRequiredPercentages(array $percentages): bool
    {
        return count(array_intersect_key($percentages, array_flip(['cs', 'iva', 'hm', 'adm', 'util', 'it']))) === 6;
    }
}

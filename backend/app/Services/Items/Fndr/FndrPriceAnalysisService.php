<?php

namespace App\Services\Items\Fndr;

use App\Models\FndrCalculationPercentage;
use App\Models\InputLog;
use App\Models\Item;
use App\Models\ItemInput;
use App\Models\UpreCalculationPercentage;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FndrPriceAnalysisService
{
    public function buildCurrent(Item $item, string $mode = 'fndr'): array
    {
        return $this->build($item, false, null, $mode);
    }

    public function buildRecalculated(Item $item, CarbonInterface $date, string $mode = 'fndr'): array
    {
        return $this->build($item, true, $date, $mode);
    }

    public function calculateCurrentPrice(Item $item, string $mode = 'fndr'): float
    {
        return $this->buildCurrent($item, $mode)['totals']['total_price'];
    }

    private function build(Item $item, bool $useHistoricalLogs, ?CarbonInterface $date, string $mode): array
    {
        $item->loadMissing(['groupCatalog', 'subgroupCatalog', 'unitMeasure']);

        $modeConfig = $this->resolveModeConfig($mode);
        $percentageModel = $modeConfig['model'];

        $percentages = $percentageModel::query()
            ->active()
            ->orderBy('id_porcentaje')
            ->get();

        $percentageMap = $this->normalizePercentages($percentages);

        $components = $useHistoricalLogs
            ? $this->loadHistoricalComponents($item, $date)
            : $this->loadCurrentComponents($item);

        $materials = $this->mapComponents($components->where('type_id', 1)->values());
        $labor = $this->mapComponents($components->where('type_id', 2)->values());
        $tools = $this->mapComponents($components->where('type_id', 3)->values());

        $totals = $this->calculateTotals($materials, $labor, $tools, $percentageMap);

        return [
            'item' => [
                'id_item' => $item->id_item,
                'name' => $item->item,
                'status' => $item->estado,
                'date' => $item->fecha_item?->toDateString(),
                'price' => $item->precio,
                'group' => $item->groupCatalog ? [
                    'id' => $item->groupCatalog->id_grupo,
                    'name' => $item->groupCatalog->nombre_grupo,
                    'code' => $item->groupCatalog->codigo_grupo,
                ] : null,
                'subgroup' => $item->subgroupCatalog ? [
                    'id' => $item->subgroupCatalog->id_subgrupo,
                    'description' => $item->subgroupCatalog->descripcion,
                    'code' => $item->subgroupCatalog->codigo,
                ] : null,
                'unit_measure' => $item->unitMeasure ? [
                    'id' => $item->unitMeasure->id_unidad_medida,
                    'description' => $item->unitMeasure->descripcion,
                    'abbreviation' => $item->unitMeasure->abreviatura,
                ] : null,
            ],
            'materials' => $materials,
            'labor' => $labor,
            'tools' => $tools,
            'percentages' => $percentages->map(fn ($percentage): array => [
                'id' => $percentage->id_porcentaje,
                'description' => $percentage->descripcion,
                'code' => $percentage->codigo,
                'percentage' => round((float) $percentage->porcentaje, 2),
                'status' => $percentage->estado,
            ])->values()->all(),
            'totals' => $totals,
            'meta' => [
                'mode' => strtolower($mode),
                'source' => $useHistoricalLogs ? 'historical_logs' : 'current_inputs',
                'reference_date' => $date?->toDateString(),
            ],
        ];
    }

    private function loadCurrentComponents(Item $item): Collection
    {
        return ItemInput::query()
            ->from('item_insumo')
            ->join('insumo', 'insumo.id_insumo', '=', 'item_insumo.id_insumo')
            ->join('unidad_medida', 'unidad_medida.id_unidad_medida', '=', 'insumo.unidad_medida')
            ->where('item_insumo.id_item', $item->id_item)
            ->where('item_insumo.estado', 'AC')
            ->select([
                'item_insumo.id_item_insumo',
                'item_insumo.id_insumo',
                'item_insumo.cantidad',
                'insumo.tipo as type_id',
                'insumo.descripcion',
                'insumo.precio as unit_price',
                'unidad_medida.id_unidad_medida as unit_measure_id',
                'unidad_medida.descripcion as unit_measure_description',
                'unidad_medida.abreviatura as unit_measure_abbreviation',
            ])
            ->orderBy('insumo.tipo')
            ->orderBy('insumo.descripcion')
            ->get();
    }

    private function loadHistoricalComponents(Item $item, CarbonInterface $date): Collection
    {
        $latestLogs = InputLog::query()
            ->select('id_insumo', DB::raw('MAX(id_log) as latest_log_id'))
            ->whereDate('fecha', '<=', $date->toDateString())
            ->groupBy('id_insumo');

        return ItemInput::query()
            ->from('item_insumo')
            ->joinSub($latestLogs, 'latest_logs', function ($join): void {
                $join->on('latest_logs.id_insumo', '=', 'item_insumo.id_insumo');
            })
            ->join('log_insumo', 'log_insumo.id_log', '=', 'latest_logs.latest_log_id')
            ->join('unidad_medida', 'unidad_medida.id_unidad_medida', '=', 'log_insumo.unidad_medida')
            ->where('item_insumo.id_item', $item->id_item)
            ->where('item_insumo.estado', 'AC')
            ->select([
                'item_insumo.id_item_insumo',
                'item_insumo.id_insumo',
                'item_insumo.cantidad',
                'log_insumo.tipo as type_id',
                'log_insumo.descripcion',
                'log_insumo.precio as unit_price',
                'log_insumo.id_log',
                'unidad_medida.id_unidad_medida as unit_measure_id',
                'unidad_medida.descripcion as unit_measure_description',
                'unidad_medida.abreviatura as unit_measure_abbreviation',
            ])
            ->orderBy('type_id')
            ->orderBy('descripcion')
            ->get();
    }

    private function mapComponents(Collection $components): array
    {
        return $components->map(function ($component): array {
            $quantity = (float) $component->cantidad;
            $unitPrice = (float) $component->unit_price;
            $partial = $quantity * $unitPrice;

            return [
                'id_item_input' => (int) $component->id_item_insumo,
                'input_id' => (int) $component->id_insumo,
                'description' => $component->descripcion,
                'quantity' => round($quantity, 4),
                'unit_price' => round($unitPrice, 4),
                'partial' => round($partial, 4),
                'type_id' => (int) $component->type_id,
                'log_id' => isset($component->id_log) ? (int) $component->id_log : null,
                'unit_measure' => [
                    'id' => (int) $component->unit_measure_id,
                    'description' => $component->unit_measure_description,
                    'abbreviation' => $component->unit_measure_abbreviation,
                ],
            ];
        })->values()->all();
    }

    private function normalizePercentages(Collection $percentages): array
    {
        $map = [];

        foreach ($percentages as $percentage) {
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
        }

        foreach (['social_charges', 'vat', 'minor_tools', 'administration', 'utility', 'transaction_tax'] as $requiredKey) {
            if (! array_key_exists($requiredKey, $map)) {
                throw new InvalidArgumentException('Uno de los parametros de porcentaje del modo solicitado no esta configurado adecuadamente.');
            }
        }

        return $map;
    }

    private function calculateTotals(array $materials, array $labor, array $tools, array $percentages): array
    {
        $materialsTotal = round(collect($materials)->sum('partial'), 4);
        $laborBaseTotal = round(collect($labor)->sum('partial'), 4);
        $toolsBaseTotal = round(collect($tools)->sum('partial'), 4);

        $socialChargesAmount = round(($laborBaseTotal * $percentages['social_charges']) / 100, 4);
        $laborVatAmount = round((($laborBaseTotal + $socialChargesAmount) * $percentages['vat']) / 100, 4);
        $laborTotal = round($laborBaseTotal + $socialChargesAmount + $laborVatAmount, 4);

        $minorToolsAmount = round(($laborTotal * $percentages['minor_tools']) / 100, 4);
        $toolsTotal = round($toolsBaseTotal + $minorToolsAmount, 4);

        $directCostTotal = round($materialsTotal + $laborTotal + $toolsTotal, 4);
        $administrationAmount = round(($directCostTotal * $percentages['administration']) / 100, 4);
        $utilityAmount = round((($directCostTotal + $administrationAmount) * $percentages['utility']) / 100, 4);
        $subtotalTotal = round($directCostTotal + $administrationAmount + $utilityAmount, 4);
        $transactionTaxAmount = round(($subtotalTotal * $percentages['transaction_tax']) / 100, 4);
        $totalPrice = round($subtotalTotal + $transactionTaxAmount, 4);

        return [
            'materials_total' => $materialsTotal,
            'labor_base_total' => $laborBaseTotal,
            'social_charges_amount' => $socialChargesAmount,
            'labor_vat_amount' => $laborVatAmount,
            'labor_total' => $laborTotal,
            'tools_base_total' => $toolsBaseTotal,
            'minor_tools_amount' => $minorToolsAmount,
            'tools_total' => $toolsTotal,
            'direct_cost_total' => $directCostTotal,
            'administration_amount' => $administrationAmount,
            'utility_amount' => $utilityAmount,
            'subtotal_total' => $subtotalTotal,
            'transaction_tax_amount' => $transactionTaxAmount,
            'total_price' => $totalPrice,
        ];
    }

    private function resolveModeConfig(string $mode): array
    {
        return match (strtolower($mode)) {
            'fndr' => [
                'model' => FndrCalculationPercentage::class,
            ],
            'upre' => [
                'model' => UpreCalculationPercentage::class,
            ],
            default => throw new InvalidArgumentException('Modo de analisis no soportado.'),
        };
    }
}

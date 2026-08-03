<?php

namespace App\Modules\Projects\Services;

use App\Models\ProjectItem;
use App\Models\ProjectPercentageSnapshot;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProjectSnapshotAnalysisService
{
    public function build(ProjectItem $projectItem, string $format): array
    {
        $components = DB::table('proyecto_item_insumo_snapshot')
            ->where('id_proyecto_item', $projectItem->id_proyecto_item)
            ->orderBy('tipo')
            ->orderBy('descripcion')
            ->get();

        $percentages = ProjectPercentageSnapshot::query()
            ->where('id_proyecto', $projectItem->id_proyecto)
            ->where('formato', strtoupper($format))
            ->where('estado', 'AC')
            ->orderBy('id_snapshot')
            ->get();

        $percentageMap = $this->normalizePercentages($percentages);
        $materials = $this->mapComponents($components->where('tipo', 1)->values());
        $labor = $this->mapComponents($components->where('tipo', 2)->values());
        $tools = $this->mapComponents($components->where('tipo', 3)->values());
        $itemName = $projectItem->nombre_snapshot ?? $projectItem->item?->item ?? 'Item no disponible';

        return [
            'item' => [
                'id_item' => $projectItem->id_item,
                'name' => $itemName,
                'status' => $projectItem->estado_catalogo_snapshot ?? $projectItem->item?->estado,
                'date' => $projectItem->fecha?->toDateString(),
                'price' => $projectItem->precio,
                'group' => [
                    'id' => $projectItem->item?->groupCatalog?->id_grupo,
                    'name' => $projectItem->grupo_snapshot ?? $projectItem->item?->groupCatalog?->nombre_grupo,
                    'code' => $projectItem->item?->groupCatalog?->codigo_grupo,
                ],
                'subgroup' => [
                    'id' => $projectItem->item?->subgroupCatalog?->id_subgrupo,
                    'description' => $projectItem->subgrupo_snapshot ?? $projectItem->item?->subgroupCatalog?->descripcion,
                    'code' => $projectItem->item?->subgroupCatalog?->codigo,
                ],
                'unit_measure' => [
                    'id' => $projectItem->item?->unitMeasure?->id_unidad_medida,
                    'description' => $projectItem->item?->unitMeasure?->descripcion,
                    'abbreviation' => $projectItem->unidad_snapshot ?? $projectItem->item?->unitMeasure?->abreviatura,
                ],
            ],
            'materials' => $materials,
            'labor' => $labor,
            'tools' => $tools,
            'percentages' => $percentages->map(fn (ProjectPercentageSnapshot $percentage): array => [
                'id' => $percentage->id_snapshot,
                'description' => $percentage->descripcion,
                'code' => $percentage->codigo,
                'percentage' => round((float) $percentage->porcentaje, 2),
                'status' => $percentage->estado,
            ])->values()->all(),
            'totals' => $this->calculateTotals($materials, $labor, $tools, $percentageMap),
            'meta' => [
                'mode' => strtoupper($format) === 'PCA' ? 'general' : strtolower($format),
                'source' => 'project_version_snapshot',
                'reference_date' => null,
            ],
        ];
    }

    public function price(ProjectItem $projectItem, string $format = 'PCA'): float
    {
        return (float) $this->build($projectItem, $format)['totals']['total_price'];
    }

    private function mapComponents(Collection $components): array
    {
        return $components->map(fn ($component): array => [
            'id_item_input' => (int) ($component->id_item_insumo_origen ?? $component->id_snapshot),
            'input_id' => $component->id_insumo !== null ? (int) $component->id_insumo : null,
            'description' => $component->descripcion,
            'quantity' => round((float) $component->cantidad, 4),
            'unit_price' => round((float) $component->precio_unitario, 4),
            'partial' => round((float) $component->cantidad * (float) $component->precio_unitario, 4),
            'type_id' => $component->tipo !== null ? (int) $component->tipo : null,
            'log_id' => null,
            'group_name' => null,
            'subgroup_name' => null,
            'unit_measure' => [
                'id' => null,
                'description' => $component->unidad,
                'abbreviation' => $component->unidad,
            ],
            'snapshot_status' => $component->estado,
        ])->values()->all();
    }

    private function normalizePercentages(Collection $percentages): array
    {
        $map = [];

        foreach ($percentages as $percentage) {
            $description = mb_strtoupper(trim((string) $percentage->descripcion));

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

        foreach (['social_charges', 'vat', 'minor_tools', 'administration', 'utility', 'transaction_tax'] as $required) {
            if (! array_key_exists($required, $map)) {
                throw new InvalidArgumentException('Uno de los parametros de porcentaje del proyecto no esta configurado adecuadamente.');
            }
        }

        return $map;
    }

    private function calculateTotals(array $materials, array $labor, array $tools, array $percentages): array
    {
        $materialsTotal = (float) collect($materials)->sum('partial');
        $laborBaseTotal = (float) collect($labor)->sum('partial');
        $toolsBaseTotal = (float) collect($tools)->sum('partial');
        $socialChargesAmount = $laborBaseTotal * $percentages['social_charges'] / 100;
        $laborVatAmount = ($laborBaseTotal + $socialChargesAmount) * $percentages['vat'] / 100;
        $laborTotal = $laborBaseTotal + $socialChargesAmount + $laborVatAmount;
        $minorToolsAmount = $laborTotal * $percentages['minor_tools'] / 100;
        $toolsTotal = $toolsBaseTotal + $minorToolsAmount;
        $directCostTotal = $materialsTotal + $laborTotal + $toolsTotal;
        $administrationAmount = $directCostTotal * $percentages['administration'] / 100;
        $utilityAmount = ($directCostTotal + $administrationAmount) * $percentages['utility'] / 100;
        $subtotalTotal = $directCostTotal + $administrationAmount + $utilityAmount;
        $transactionTaxAmount = $subtotalTotal * $percentages['transaction_tax'] / 100;

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
            'total_price' => $subtotalTotal + $transactionTaxAmount,
        ];
    }
}

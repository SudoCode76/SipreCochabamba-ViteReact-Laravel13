<?php

namespace App\Modules\Items\Services;

use App\Models\Item;
use App\Modules\Items\Services\Analysis\ItemPriceAnalysisService;
use App\Support\Xlsx\SimpleXlsxResponse;
use Carbon\CarbonInterface;
use Illuminate\Http\Response;

class ItemBudgetXlsxService
{
    public function __construct(
        private readonly ItemCompositionService $itemCompositionService,
        private readonly ItemPriceAnalysisService $itemPriceAnalysisService,
        private readonly HistoricalBreakdownPdfService $historicalBreakdownPdfService,
    ) {}

    public function priceAnalysis(Item $item, string $mode = 'general', ?CarbonInterface $date = null): Response
    {
        $analysis = $date
            ? $this->itemPriceAnalysisService->buildRecalculated($item, $date, $mode)
            : $this->itemPriceAnalysisService->buildCurrent($item, $mode);

        return SimpleXlsxResponse::make($date ? 'recalcular_analisis_precios.xlsx' : 'analisis_precios_unitarios.xlsx', [
            [
                'title' => 'Componentes',
                'rows' => $this->analysisComponentsRows($analysis),
            ],
            [
                'title' => 'Parametros',
                'rows' => $this->analysisPercentagesRows($analysis),
            ],
            [
                'title' => 'Resumen',
                'rows' => $this->analysisTotalsRows($analysis),
            ],
        ]);
    }

    public function currentBreakdown(Item $item, int $type): Response
    {
        $rows = [
            ['Item', $item->item],
            ['Tipo', $this->typeLabel($type)],
            [],
            ['Nro', 'Insumo/Parametro', 'Unidad', 'Cantidad', 'Unitario', 'Parcial'],
        ];
        $total = 0.0;

        foreach ($this->itemCompositionService->listByType($item, $type) as $index => $row) {
            $quantity = (float) ($row['cantidad'] ?? 0);
            $unitPrice = (float) ($row['precio_unitario'] ?? 0);
            $partial = round((float) ($row['parcial'] ?? ($quantity * $unitPrice)), 2);
            $total += $partial;
            $rows[] = [
                $index + 1,
                $row['descripcion'] ?? '',
                $row['unidad'] ?? '',
                $quantity,
                $unitPrice,
                $partial,
            ];
        }

        $rows[] = [];
        $rows[] = ['TOTAL', '', '', '', '', round($total, 2)];

        return SimpleXlsxResponse::make($this->breakdownFilename($type), [[
            'title' => 'Desglose',
            'rows' => $rows,
        ]]);
    }

    public function historicalBreakdown(Item $item, int|string $type, CarbonInterface $date): Response
    {
        $typeId = $this->historicalBreakdownPdfService->normalizeType($type);
        $components = $this->historicalBreakdownPdfService->historicalComponents($item, $typeId, $date);
        $rows = [
            ['Item', $item->item],
            ['Tipo', $this->typeLabel($typeId)],
            ['Fecha de referencia', $date->toDateString()],
            [],
            ['Nro', 'Insumo/Parametro', 'Unidad', 'Cantidad', 'Unitario', 'Parcial'],
        ];
        $total = 0.0;

        foreach ($components as $index => $component) {
            $quantity = (float) $component->cantidad;
            $unitPrice = (float) $component->precio_insumo;
            $partial = round($quantity * $unitPrice, 2);
            $total += $partial;
            $rows[] = [
                $index + 1,
                $component->descripcion,
                $component->unidad_abreviatura ?? $component->medida ?? '',
                $quantity,
                $unitPrice,
                $partial,
            ];
        }

        $rows[] = [];
        $rows[] = ['TOTAL', '', '', '', '', round($total, 2)];

        return SimpleXlsxResponse::make('desgloce_recalculado.xlsx', [[
            'title' => 'Desglose historico',
            'rows' => $rows,
        ]]);
    }

    private function analysisComponentsRows(array $analysis): array
    {
        $rows = [
            ['Item', $analysis['item']['name'] ?? ''],
            ['Modo', $analysis['meta']['mode'] ?? ''],
            ['Fecha de referencia', $analysis['meta']['reference_date'] ?? ''],
            [],
            ['Tipo', 'Nro', 'Descripcion', 'Unidad', 'Cantidad', 'Unitario', 'Parcial'],
        ];

        foreach (['materials' => 'Materiales', 'labor' => 'Mano de Obra', 'tools' => 'Herramientas/Equipo'] as $key => $label) {
            foreach (($analysis[$key] ?? []) as $index => $row) {
                $rows[] = [
                    $label,
                    $index + 1,
                    $row['description'] ?? '',
                    $row['unit_measure']['abbreviation'] ?? $row['unit_measure']['description'] ?? '',
                    $row['quantity'] ?? 0,
                    $row['unit_price'] ?? 0,
                    $row['partial'] ?? 0,
                ];
            }
        }

        return $rows;
    }

    private function analysisPercentagesRows(array $analysis): array
    {
        $rows = [['Descripcion', 'Codigo', 'Porcentaje']];
        foreach (($analysis['percentages'] ?? []) as $percentage) {
            $rows[] = [
                $percentage['description'] ?? '',
                $percentage['code'] ?? '',
                $percentage['percentage'] ?? 0,
            ];
        }

        return $rows;
    }

    private function analysisTotalsRows(array $analysis): array
    {
        $labels = [
            'materials_total' => 'Total materiales',
            'labor_base_total' => 'Mano de obra base',
            'social_charges_amount' => 'Cargas sociales',
            'labor_vat_amount' => 'IVA mano de obra',
            'labor_total' => 'Total mano de obra',
            'tools_base_total' => 'Herramientas/equipo base',
            'minor_tools_amount' => 'Herramientas menores',
            'tools_total' => 'Total herramientas/equipo',
            'direct_cost_total' => 'Costo directo',
            'administration_amount' => 'Gastos grales. y administrativos',
            'utility_amount' => 'Utilidad',
            'subtotal_total' => 'Subtotal',
            'transaction_tax_amount' => 'Impuesto a las transacciones',
            'total_price' => 'Precio unitario final',
        ];
        $rows = [['Codigo', 'Concepto', 'Monto']];

        foreach ($labels as $key => $label) {
            $rows[] = [$key, $label, $analysis['totals'][$key] ?? 0];
        }

        return $rows;
    }

    private function typeLabel(int $type): string
    {
        return match ($type) {
            1 => 'Material',
            2 => 'Mano de Obra',
            3 => 'Maquinaria y Herramientas',
            default => 'Otro',
        };
    }

    private function breakdownFilename(int $type): string
    {
        return match ($type) {
            1 => 'desglose_materiales.xlsx',
            2 => 'desglose_mano_obra.xlsx',
            3 => 'desglose_maquinaria.xlsx',
            default => 'desglose_insumos.xlsx',
        };
    }
}

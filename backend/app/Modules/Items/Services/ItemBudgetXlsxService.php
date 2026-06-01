<?php

namespace App\Modules\Items\Services;

use App\Models\Item;
use App\Modules\Items\Services\Analysis\ItemPriceAnalysisService;
use App\Support\Pdf\LegacyPdfFormat;
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
                'title' => 'Analisis',
                'rows' => $this->analysisReportRows($analysis),
            ],
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

    private function analysisReportRows(array $analysis): array
    {
        $totals = $analysis['totals'] ?? [];
        $percentages = $this->resolveAnalysisPercentages($analysis['percentages'] ?? []);
        $totalPrice = round((float) ($totals['total_price'] ?? 0), 2);

        $rows = [
            ['Análisis de Precios Unitarios'],
            ['Item', $analysis['item']['name'] ?? '', '', 'Unidad', $analysis['item']['unit_measure']['description'] ?? $analysis['item']['unit_measure']['abbreviation'] ?? ''],
            ['Modo', $analysis['meta']['mode'] ?? ''],
            ['Fecha de referencia', $analysis['meta']['reference_date'] ?? ''],
            [],
            ['Nº P', 'Insumo/Parametro', 'Unid.', 'Cant.', 'Unit.(Bs)', 'Parcial(Bs)'],
            ['A', 'MATERIALES'],
        ];

        foreach (($analysis['materials'] ?? []) as $index => $row) {
            $rows[] = $this->componentReportRow($index, $row);
        }

        $rows[] = ['D', 'TOTAL MATERIALES', '', '', '(A)=', round((float) ($totals['materials_total'] ?? 0), 2)];
        $rows[] = ['B', 'MANO DE OBRA'];

        foreach (($analysis['labor'] ?? []) as $index => $row) {
            $rows[] = $this->componentReportRow($index, $row);
        }

        $rows[] = ['E', 'SUBTOTAL MANO DE OBRA', '', '', '(B)=', round((float) ($totals['labor_base_total'] ?? 0), 2)];
        $rows[] = [
            'F',
            $percentages['social_charges']['description'] ?? 'CARGAS SOCIALES',
            '',
            $this->percentageLabel($percentages['social_charges']['percentage'] ?? 0),
            '(E)=',
            round((float) ($totals['social_charges_amount'] ?? 0), 2),
        ];
        $rows[] = [
            'O',
            $percentages['vat']['description'] ?? 'IMPUESTO AL VALOR AGREGADO',
            '',
            $this->percentageLabel($percentages['vat']['percentage'] ?? 0),
            '(E+F)=',
            round((float) ($totals['labor_vat_amount'] ?? 0), 2),
        ];
        $rows[] = ['G', 'TOTAL MANO DE OBRA', '', '', '(E+F+O)=', round((float) ($totals['labor_total'] ?? 0), 2)];
        $rows[] = ['C', 'EQUIPO, MAQUINARIA Y HERRAMIENTA'];

        foreach (($analysis['tools'] ?? []) as $index => $row) {
            $rows[] = $this->componentReportRow($index, $row);
        }

        $rows[] = [
            'H',
            $percentages['minor_tools']['description'] ?? 'HERRAMIENTAS MENORES',
            '',
            $this->percentageLabel($percentages['minor_tools']['percentage'] ?? 0),
            '(G)=',
            round((float) ($totals['minor_tools_amount'] ?? 0), 2),
        ];
        $rows[] = ['I', 'TOTAL HERRAMIENTAS Y EQUIPO', '', '', '(C+H)=', round((float) ($totals['tools_total'] ?? 0), 2)];
        $rows[] = ['J', 'SUBTOTAL', '', '', '(D+G+I)=', round((float) ($totals['direct_cost_total'] ?? 0), 2)];
        $rows[] = [
            'L',
            $percentages['administration']['description'] ?? 'GASTOS GRALES Y ADMINISTRATIVOS',
            '',
            $this->percentageLabel($percentages['administration']['percentage'] ?? 0),
            '(E)=',
            round((float) ($totals['administration_amount'] ?? 0), 2),
        ];
        $rows[] = [
            'M',
            $percentages['utility']['description'] ?? 'UTILIDAD',
            '',
            $this->percentageLabel($percentages['utility']['percentage'] ?? 0),
            '(J+L)=',
            round((float) ($totals['utility_amount'] ?? 0), 2),
        ];
        $rows[] = ['N', 'PARCIAL', '', '', '(J+L+M)=', round((float) ($totals['subtotal_total'] ?? 0), 2)];
        $rows[] = [
            'M',
            $percentages['transaction_tax']['description'] ?? 'IMPUESTO A LAS TRANSACCIONES',
            '',
            $this->percentageLabel($percentages['transaction_tax']['percentage'] ?? 0),
            '(N)=',
            round((float) ($totals['transaction_tax_amount'] ?? 0), 2),
        ];
        $rows[] = ['Q', 'TOTAL PRECIO UNITARIO', '', '', '((N+P)=', $totalPrice];
        $rows[] = ['', 'PRECIO ADOPTADO', '', '', '', $totalPrice];
        $rows[] = ['SON:', LegacyPdfFormat::amountLiteral($totalPrice).' BOLIVIANOS.'];

        return $rows;
    }

    private function componentReportRow(int $index, array $row): array
    {
        return [
            $index + 1,
            $row['description'] ?? '',
            $row['unit_measure']['abbreviation'] ?? $row['unit_measure']['description'] ?? '',
            $row['quantity'] ?? 0,
            round((float) ($row['unit_price'] ?? 0), 2),
            round((float) ($row['partial'] ?? 0), 2),
        ];
    }

    private function resolveAnalysisPercentages(array $percentages): array
    {
        $resolved = [];

        foreach ($percentages as $percentage) {
            $description = mb_strtoupper((string) ($percentage['description'] ?? ''), 'UTF-8');
            $entry = [
                'description' => $description,
                'percentage' => (float) ($percentage['percentage'] ?? 0),
            ];

            if (str_contains($description, 'CARGAS SOCIALES')) {
                $resolved['social_charges'] = $entry;
            }
            if (str_contains($description, 'IMPUESTO AL VALOR AGREGADO')) {
                $resolved['vat'] = $entry;
            }
            if (str_contains($description, 'HERRAMIENTAS MENORES')) {
                $resolved['minor_tools'] = $entry;
            }
            if (str_contains($description, 'GASTOS GRALES Y ADMINISTRATIVOS')) {
                $resolved['administration'] = $entry;
            }
            if (str_contains($description, 'UTILIDAD')) {
                $resolved['utility'] = $entry;
            }
            if (str_contains($description, 'IMPUESTO A LAS TRANSACCIONES')) {
                $resolved['transaction_tax'] = $entry;
            }
        }

        return $resolved;
    }

    private function percentageLabel(float|int $percentage): string
    {
        $value = round((float) $percentage, 2);

        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.').'%';
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

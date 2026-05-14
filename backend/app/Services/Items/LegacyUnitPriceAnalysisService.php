<?php

namespace App\Services\Items;

use App\Models\Item;
use App\Services\Items\Analysis\ItemPriceAnalysisService;

class LegacyUnitPriceAnalysisService
{
    public function __construct(
        private readonly ItemPriceAnalysisService $priceAnalysisService,
    ) {}

    public function build(Item $item): array
    {
        $analysis = $this->priceAnalysisService->buildCurrent($item, 'general');

        return [
            'header' => [
                'entity' => 'Gobierno Autonomo Municipal de Cochabamba',
                'department' => 'Secretaria de Planificacion',
                'division' => 'Direccion de Proyectos',
                'country' => 'Cochabamba-Bolivia',
                'printed_at' => now()->format('d/m/Y H:i:s'),
                'title' => 'Analisis de Precios Unitarios',
            ],
            'item' => [
                'id_item' => $analysis['item']['id_item'],
                'name' => $analysis['item']['name'],
                'unit' => $analysis['item']['unit_measure']['description'] ?? null,
                'unit_abbreviation' => $analysis['item']['unit_measure']['abbreviation'] ?? null,
            ],
            'blocks' => [
                $this->buildComponentBlock('A', 'Materiales', $analysis['materials'], $analysis['totals']['materials_total']),
                $this->buildComponentBlock('E', 'Mano de obra', $analysis['labor'], $analysis['totals']['labor_total']),
                $this->buildComponentBlock('H', 'Herramientas / equipo', $analysis['tools'], $analysis['totals']['tools_total']),
            ],
            'parameters' => $this->buildParameterRows($analysis['percentages'], $analysis['totals']),
            'summary' => $this->buildSummaryRows($analysis['totals']),
            'totals' => $analysis['totals'],
            'raw' => $analysis,
        ];
    }

    private function buildComponentBlock(string $code, string $title, array $rows, float $total): array
    {
        return [
            'code' => $code,
            'title' => $title,
            'rows' => array_map(function (array $row, int $index): array {
                return [
                    'position' => $index + 1,
                    'description' => $row['description'],
                    'unit' => $row['unit_measure']['abbreviation'] ?? $row['unit_measure']['description'] ?? '',
                    'quantity' => $row['quantity'],
                    'unit_price' => $row['unit_price'],
                    'partial' => $row['partial'],
                    'group_name' => $row['group_name'],
                    'subgroup_name' => $row['subgroup_name'],
                ];
            }, $rows, array_keys($rows)),
            'total' => round($total, 4),
        ];
    }

    private function buildParameterRows(array $percentages, array $totals): array
    {
        return array_values(array_filter(array_map(function (array $percentage, int $index) use ($totals): ?array {
            $normalized = strtoupper(trim((string) $percentage['description']));

            $row = [
                'position' => $index + 1,
                'description' => $percentage['description'],
                'code' => $percentage['code'],
                'percentage' => (float) $percentage['percentage'],
                'base_amount' => null,
                'amount' => null,
            ];

            if (str_contains($normalized, 'CARGAS SOCIALES')) {
                $row['base_amount'] = $totals['labor_base_total'];
                $row['amount'] = $totals['social_charges_amount'];
            } elseif (str_contains($normalized, 'IMPUESTO AL VALOR AGREGADO')) {
                $row['base_amount'] = $totals['labor_base_total'] + $totals['social_charges_amount'];
                $row['amount'] = $totals['labor_vat_amount'];
            } elseif (str_contains($normalized, 'HERRAMIENTAS MENORES')) {
                $row['base_amount'] = $totals['labor_total'];
                $row['amount'] = $totals['minor_tools_amount'];
            } elseif (str_contains($normalized, 'GASTOS GRALES Y ADMINISTRATIVOS')) {
                $row['base_amount'] = $totals['direct_cost_total'];
                $row['amount'] = $totals['administration_amount'];
            } elseif (str_contains($normalized, 'UTILIDAD')) {
                $row['base_amount'] = $totals['direct_cost_total'] + $totals['administration_amount'];
                $row['amount'] = $totals['utility_amount'];
            } elseif (str_contains($normalized, 'IMPUESTO A LAS TRANSACCIONES')) {
                $row['base_amount'] = $totals['subtotal_total'];
                $row['amount'] = $totals['transaction_tax_amount'];
            }

            return $row['amount'] === null ? null : $row;
        }, $percentages, array_keys($percentages))));
    }

    private function buildSummaryRows(array $totals): array
    {
        return [
            ['code' => 'A', 'label' => 'Total materiales', 'amount' => $totals['materials_total']],
            ['code' => 'B', 'label' => 'Mano de obra base', 'amount' => $totals['labor_base_total']],
            ['code' => 'C', 'label' => 'Cargas sociales', 'amount' => $totals['social_charges_amount']],
            ['code' => 'D', 'label' => 'IVA mano de obra', 'amount' => $totals['labor_vat_amount']],
            ['code' => 'E', 'label' => 'Total mano de obra', 'amount' => $totals['labor_total']],
            ['code' => 'F', 'label' => 'Herramientas / equipo base', 'amount' => $totals['tools_base_total']],
            ['code' => 'G', 'label' => 'Herramientas menores', 'amount' => $totals['minor_tools_amount']],
            ['code' => 'H', 'label' => 'Total herramientas / equipo', 'amount' => $totals['tools_total']],
            ['code' => 'I', 'label' => 'Costo directo', 'amount' => $totals['direct_cost_total']],
            ['code' => 'J', 'label' => 'Gastos grales. y administrativos', 'amount' => $totals['administration_amount']],
            ['code' => 'K', 'label' => 'Utilidad', 'amount' => $totals['utility_amount']],
            ['code' => 'L', 'label' => 'Subtotal', 'amount' => $totals['subtotal_total']],
            ['code' => 'M', 'label' => 'Impuesto a las transacciones', 'amount' => $totals['transaction_tax_amount']],
            ['code' => 'TOTAL', 'label' => 'Precio unitario final', 'amount' => $totals['total_price']],
        ];
    }
}

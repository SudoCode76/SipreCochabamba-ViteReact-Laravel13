<?php

namespace App\Modules\Projects\Services;

use App\Models\Project;
use App\Models\ProjectPercentageSnapshot;
use App\Support\Xlsx\SimpleXlsxResponse;
use Carbon\CarbonInterface;
use Illuminate\Http\Response;

class ProjectBudgetXlsxService
{
    public function __construct(
        private readonly ProjectBudgetService $projectBudgetService,
        private readonly ProjectLegacyUnitPriceService $projectLegacyUnitPriceService,
        private readonly ProjectInputBreakdownPdfService $projectInputBreakdownPdfService,
        private readonly ProjectInputsReportPdfService $projectInputsReportPdfService,
        private readonly ProjectInputsGroupedReportPdfService $projectInputsGroupedReportPdfService,
    ) {}

    public function budgetByGroup(Project $project): Response
    {
        return $this->budgetByGroupResponse($project, $this->projectBudgetService->budgetByGroupPdfData($project), 'presupuesto_por_rubros.xlsx');
    }

    public function budgetRecalculation(Project $project, CarbonInterface $date): Response
    {
        $budget = $this->projectBudgetService->budgetRecalculation($project, $date);

        return $this->budgetByGroupResponse($project, $budget, 'presupuesto_recalculado.xlsx', [
            ['Fecha de referencia', $date->toDateString()],
        ]);
    }

    public function incidenceSummary(Project $project, string $format): Response
    {
        $percentages = $this->resolvePercentages($project, $format);
        $budget = $this->projectBudgetService->budgetByGroupPdfData($project);
        $rows = [
            ['Proyecto', $project->nombre_proyecto],
            ['Formato', $format],
            [],
            ['Nro', 'Grupo', 'Subgrupo', 'Item', 'F Cargas Sociales', 'H Herramientas Menores', 'L Gastos Adm.', 'M Utilidad', 'O IVA', 'P IT'],
        ];
        $totals = ['f' => 0.0, 'h' => 0.0, 'l' => 0.0, 'm' => 0.0, 'o' => 0.0, 'p' => 0.0];

        foreach (($budget['items'] ?? []) as $index => $item) {
            $f = $item['mano_obra'] * ($percentages['cs'] ?? 0) / 100;
            $o = ($item['mano_obra'] + $f) * ($percentages['iva'] ?? 0) / 100;
            $g = $item['mano_obra'] + $f + $o;
            $h = $g * ($percentages['hm'] ?? 0) / 100;
            $i = $item['herramientas'] + $h;
            $j = $item['materiales'] + $g + $i;
            $l = $j * ($percentages['adm'] ?? 0) / 100;
            $m = ($j + $l) * ($percentages['util'] ?? 0) / 100;
            $n = $j + $l + $m;
            $p = $n * ($percentages['it'] ?? 0) / 100;

            foreach (['f' => $f, 'h' => $h, 'l' => $l, 'm' => $m, 'o' => $o, 'p' => $p] as $key => $value) {
                $totals[$key] += $value;
            }

            $rows[] = [
                $index + 1,
                $item['grupo'] ?? '',
                $item['subgrupo'] ?? '',
                $item['descripcion'] ?? '',
                round($f, 2),
                round($h, 2),
                round($l, 2),
                round($m, 2),
                round($o, 2),
                round($p, 2),
            ];
        }

        $rows[] = [];
        $rows[] = ['Totales', '', '', '', round($totals['f'], 2), round($totals['h'], 2), round($totals['l'], 2), round($totals['m'], 2), round($totals['o'], 2), round($totals['p'], 2)];

        return SimpleXlsxResponse::make('resumen_incidencia.xlsx', [[
            'title' => 'Resumen incidencia',
            'rows' => $rows,
        ]]);
    }

    public function generalBudget(Project $project, string $format): Response
    {
        $items = $this->projectBudgetService->generalBudgetPdfItems($project, $format, $this->projectLegacyUnitPriceService);
        $rows = [
            ['Proyecto', $project->nombre_proyecto],
            ['Formato', $format],
            [],
            ['Nro', 'Grupo', 'Subgrupo', 'Descripcion', 'Unidad', 'Cantidad', 'Unitario', 'Parcial'],
        ];
        $total = 0.0;

        foreach ($items as $index => $item) {
            $price = round((float) $item['precio'], 2);
            $quantity = round((float) $item['cantidad'], 4);
            $partial = round($quantity * $price, 2);
            $total += $partial;
            $rows[] = [
                $index + 1,
                $item['nombre_grupo'] ?? '',
                $item['nombre_subgrupo'] ?? '',
                $item['nombre_item'] ?? '',
                $item['unidad'] ?? '',
                $quantity,
                $price,
                $partial,
            ];
        }

        $rows[] = [];
        $rows[] = ['TOTAL', '', '', '', '', '', '', round($total, 2)];

        return SimpleXlsxResponse::make('presupuesto_general.xlsx', [[
            'title' => 'Presupuesto general',
            'rows' => $rows,
        ]]);
    }

    public function inputBreakdown(Project $project, int $type): Response
    {
        $rows = [
            ['Proyecto', $project->nombre_proyecto],
            ['Tipo', $this->typeLabel($type)],
            [],
            ['Nro', 'Prioridad', 'Item', 'Insumo/Parametro', 'Unidad', 'Cantidad', 'Unitario', 'Parcial'],
        ];
        $total = 0.0;

        foreach ($this->projectInputBreakdownPdfService->rows($project, $type) as $index => $row) {
            $total += (float) $row['parcial'];
            $rows[] = [
                $index + 1,
                $row['prioridad'],
                $row['nombre_item'],
                $row['descripcion'],
                $row['unidad'],
                $row['cantidad'],
                $row['precio_unitario'],
                $row['parcial'],
            ];
        }

        $rows[] = [];
        $rows[] = ['TOTAL', '', '', '', '', '', '', round($total, 2)];

        return SimpleXlsxResponse::make($this->inputBreakdownFilename($type), [[
            'title' => 'Desglose',
            'rows' => $rows,
        ]]);
    }

    public function inputsReport(Project $project): Response
    {
        $rows = [
            ['Proyecto', $project->nombre_proyecto],
            [],
            ['Nro', 'Tipo', 'Insumo', 'Unidad', 'Cantidad', 'P. Unitario', 'Parcial'],
        ];
        $total = 0.0;

        foreach ($this->projectInputsReportPdfService->rows($project) as $index => $row) {
            $total += (float) $row['parcial'];
            $rows[] = [
                $index + 1,
                $row['tipo_nombre'],
                $row['descripcion'],
                $row['unidad'],
                $row['cantidad'],
                $row['precio_unitario'],
                $row['parcial'],
            ];
        }

        $rows[] = [];
        $rows[] = ['TOTAL GENERAL', '', '', '', '', '', round($total, 2)];

        return SimpleXlsxResponse::make('reporte_consolidado_insumos.xlsx', [[
            'title' => 'Insumos',
            'rows' => $rows,
        ]]);
    }

    public function groupedInputsReport(Project $project): Response
    {
        $rows = [
            ['Proyecto', $project->nombre_proyecto],
            [],
            ['Tipo', 'Insumo', 'Unidad', 'Prioridad', 'Item', 'Cant. Item', 'Cant. Insumo por Item', 'Cant. Total', 'P. Unitario', 'Parcial'],
        ];
        $total = 0.0;

        foreach ($this->projectInputsGroupedReportPdfService->rows($project) as $row) {
            $total += (float) $row['parcial'];
            $rows[] = [
                $row['tipo_nombre'],
                $row['insumo'],
                $row['unidad'],
                $row['prioridad'],
                $row['item'],
                $row['cantidad_item'],
                $row['cantidad_insumo_item'],
                $row['cantidad_total'],
                $row['precio_unitario'],
                $row['parcial'],
            ];
        }

        $rows[] = [];
        $rows[] = ['TOTAL GENERAL', '', '', '', '', '', '', '', '', round($total, 2)];

        return SimpleXlsxResponse::make('proyecto_agrupado_por_insumos.xlsx', [[
            'title' => 'Agrupado por insumos',
            'rows' => $rows,
        ]]);
    }

    private function budgetByGroupResponse(Project $project, array $budget, string $filename, array $metadata = []): Response
    {
        $rows = array_merge([
            ['Proyecto', $project->nombre_proyecto],
        ], $metadata, [
            [],
            ['Nro', 'Grupo', 'Subgrupo', 'Descripcion Item', 'Materiales', 'Mano de Obra', 'Maquinaria y Herram.'],
        ]);

        foreach (($budget['items'] ?? []) as $index => $item) {
            $rows[] = [
                $index + 1,
                $item['grupo'] ?? '',
                $item['subgrupo'] ?? '',
                $item['descripcion'] ?? '',
                (float) ($item['materiales'] ?? 0),
                (float) ($item['mano_obra'] ?? 0),
                (float) ($item['herramientas'] ?? 0),
            ];
        }

        $totals = $budget['totals'] ?? [];
        $rows[] = [];
        $rows[] = ['Totales por rubro', '', '', '', (float) ($totals['materiales'] ?? 0), (float) ($totals['mano_obra'] ?? 0), (float) ($totals['herramientas'] ?? 0)];

        return SimpleXlsxResponse::make($filename, [[
            'title' => 'Presupuesto rubros',
            'rows' => $rows,
        ]]);
    }

    private function resolvePercentages(Project $project, string $format): array
    {
        $resolved = [];
        foreach (ProjectPercentageSnapshot::query()
            ->where('id_proyecto', $project->id_proyecto)
            ->where('formato', strtoupper($format))
            ->where('estado', 'AC')
            ->get() as $row) {
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

    private function typeLabel(int $type): string
    {
        return match ($type) {
            1 => 'Material',
            2 => 'Mano de Obra',
            3 => 'Maquinaria y Herramientas',
            default => 'Otro',
        };
    }

    private function inputBreakdownFilename(int $type): string
    {
        return match ($type) {
            1 => 'desglose_materiales.xlsx',
            2 => 'desglose_mano_obra.xlsx',
            3 => 'desglose_maquinaria.xlsx',
            default => 'desglose_insumos.xlsx',
        };
    }
}

<?php

namespace App\Modules\Projects\Services;

use App\Models\Project;
use App\Models\ProjectPercentageSnapshot;
use App\Support\Pdf\LegacyPdfFormat;
use App\Support\Xlsx\MunicipalXlsxHeader;
use Carbon\CarbonInterface;
use Illuminate\Http\Response;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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

        return $this->budgetByGroupResponse($project, $budget, 'presupuesto_recalculado.xlsx');
    }

    public function incidenceSummary(Project $project, string $format): Response
    {
        $percentages = $this->resolvePercentages($project, $format);
        $budget = $this->projectBudgetService->budgetByGroupPdfData($project);
        $rows = $this->projectRows($project);
        $rows[] = ['type' => 'meta', 'values' => ['Formato', $format]];
        $rows[] = ['type' => 'spacer'];
        $totals = ['f' => 0.0, 'h' => 0.0, 'l' => 0.0, 'm' => 0.0, 'o' => 0.0, 'p' => 0.0];
        $lastGroupId = null;
        $lastSubgroupId = null;

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

            if ($lastGroupId !== ($item['id_grupo'] ?? null)) {
                $rows[] = ['type' => 'group', 'values' => [$item['grupo'] ?? '']];
                $rows[] = ['type' => 'subgroup', 'values' => [$item['subgrupo'] ?? '']];
                $lastGroupId = $item['id_grupo'] ?? null;
                $lastSubgroupId = $item['id_subgrupo'] ?? null;
            } elseif ($lastSubgroupId !== ($item['id_subgrupo'] ?? null)) {
                $rows[] = ['type' => 'subgroupLight', 'values' => [$item['subgrupo'] ?? '']];
                $lastSubgroupId = $item['id_subgrupo'] ?? null;
            }

            $rows[] = ['type' => 'data', 'values' => [
                $index + 1,
                $item['descripcion'] ?? '',
                round($f, 2),
                round($h, 2),
                round($l, 2),
                round($m, 2),
                round($o, 2),
                round($p, 2),
            ]];
        }

        $rows[] = ['type' => 'total', 'values' => ['Totales (Bs):', '', round($totals['f'], 2), round($totals['h'], 2), round($totals['l'], 2), round($totals['m'], 2), round($totals['o'], 2), round($totals['p'], 2)]];
        $rows[] = ['type' => 'literal', 'values' => ['Las referencias de las letras de cada incidencia se halla en el RESUMEN GENERAL']];

        return $this->projectReportResponse('resumen_incidencia.xlsx', 'RESUMEN POR INCIDENCIA', [
            'Nº',
            'Descripcion Item',
            '(F) '.$this->number($percentages['cs'] ?? 0).' %',
            '(H) '.$this->number($percentages['hm'] ?? 0).' %',
            '(L) '.$this->number($percentages['adm'] ?? 0).' %',
            '(M) '.$this->number($percentages['util'] ?? 0).' %',
            '(O) '.$this->number($percentages['iva'] ?? 0).' %',
            '(P) '.$this->number($percentages['it'] ?? 0).' %',
        ], $rows, [8, 38, 16, 16, 16, 16, 16, 16]);
    }

    public function generalBudget(Project $project, string $format): Response
    {
        $items = $this->projectBudgetService->generalBudgetPdfItems($project, $format, $this->projectLegacyUnitPriceService);
        $rows = [];
        $rows[] = ['type' => 'meta', 'values' => ['Formato', $format]];
        $rows[] = ['type' => 'spacer'];
        $total = 0.0;
        $moduleTotal = 0.0;
        $lastModule = null;
        $lastGroup = null;
        $lastSubgroup = null;

        foreach ($items as $index => $item) {
            if ($lastModule !== $item['modulo']) {
                if ($lastModule !== null) {
                    $rows[] = ['type' => 'subtotal', 'values' => ['SUBTOTAL MÓDULO '.$lastModule, round($moduleTotal, 2)]];
                }

                $rows[] = ['type' => 'module', 'values' => ['MÓDULO: '.$item['modulo']]];
                $lastModule = $item['modulo'];
                $lastGroup = null;
                $lastSubgroup = null;
                $moduleTotal = 0.0;
            }

            if ($lastGroup !== $item['nombre_grupo']) {
                $rows[] = ['type' => 'group', 'values' => [$item['nombre_grupo'] ?? '']];
                $lastGroup = $item['nombre_grupo'];
            }

            if ($lastSubgroup !== $item['nombre_subgrupo']) {
                $rows[] = ['type' => 'subgroup', 'values' => [$item['nombre_subgrupo'] ?? '']];
                $lastSubgroup = $item['nombre_subgrupo'];
            }

            $price = round((float) $item['precio'], 2);
            $quantity = round((float) $item['cantidad'], 4);
            $partial = round($quantity * $price, 2);
            $total += $partial;
            $moduleTotal += $partial;
            $rows[] = ['type' => 'data', 'values' => [
                $index + 1,
                $item['nombre_item'] ?? '',
                $item['unidad'] ?? '',
                $quantity,
                $price,
                $partial,
            ]];
        }

        if ($lastModule !== null) {
            $rows[] = ['type' => 'subtotal', 'values' => ['SUBTOTAL MÓDULO '.$lastModule, round($moduleTotal, 2)]];
        }

        $rows[] = ['type' => 'total', 'values' => ['TOTAL', round($total, 2)]];
        $rows[] = ['type' => 'literal', 'values' => ['SON: BOLIVIANOS '.ltrim(LegacyPdfFormat::amountLiteral(round($total, 2)))]];

        return $this->projectReportResponse('presupuesto_general.xlsx', 'Presupuesto General Del Proyecto', [
            'Nº', 'Descripción', 'Unid.', 'Cantidad', 'Unitario', 'Parcial (Bs)',
        ], $rows, [8, 48, 12, 16, 18, 18]);
    }

    public function inputBreakdown(Project $project, int $type): Response
    {
        $rows = $this->projectRows($project);
        $rows[] = ['type' => 'spacer'];
        $total = 0.0;
        $moduleTotal = 0.0;
        $lastModule = null;
        $lastItemName = null;
        $position = 0;

        foreach ($this->projectInputBreakdownPdfService->rows($project, $type) as $row) {
            if ($lastModule !== $row['modulo']) {
                if ($lastModule !== null) {
                    $rows[] = ['type' => 'subtotal', 'values' => ['SUBTOTAL MÓDULO '.$lastModule, round($moduleTotal, 2)]];
                }

                $rows[] = ['type' => 'module', 'values' => ['MÓDULO: '.$row['modulo']]];
                $lastModule = $row['modulo'];
                $lastItemName = null;
                $moduleTotal = 0.0;
            }

            if ($lastItemName !== $row['nombre_item']) {
                $rows[] = ['type' => 'section', 'values' => ['PR: '.$row['prioridad'].'   ITEM: '.$row['nombre_item']]];
                $lastItemName = $row['nombre_item'];
            }

            $position++;
            $total += (float) $row['parcial'];
            $moduleTotal += (float) $row['parcial'];
            $rows[] = ['type' => 'data', 'values' => [
                $position,
                $row['descripcion'],
                $row['unidad'],
                $row['cantidad'],
                $row['precio_unitario'],
                $row['parcial'],
            ]];
        }

        if ($lastModule !== null) {
            $rows[] = ['type' => 'subtotal', 'values' => ['SUBTOTAL MÓDULO '.$lastModule, round($moduleTotal, 2)]];
        }

        $rows[] = ['type' => 'total', 'values' => [$type === 2 ? 'TOTAL (Bs.)' : 'TOTAL', round($total, 2)]];
        $rows[] = ['type' => 'literal', 'values' => [$type === 3 ? 'SON:'.LegacyPdfFormat::amountLiteral($total).' BOLIVIANOS.' : 'SON: BOLIVIANOS  '.LegacyPdfFormat::amountLiteral($total).' ']];

        return $this->projectReportResponse($this->inputBreakdownFilename($type), $this->inputBreakdownTitle($type), [
            'Nº P', 'Insumo/Parametro', 'Unid.', 'Cant.', 'Unit.(Bs)', 'Parcial(Bs)',
        ], $rows, [8, 48, 12, 16, 18, 18]);
    }

    public function inputsReport(Project $project): Response
    {
        $rows = $this->projectRows($project);
        $rows[] = ['type' => 'spacer'];
        $rowsByType = collect($this->projectInputsReportPdfService->rows($project))->groupBy('tipo');
        $grandTotal = 0.0;

        foreach ([1 => 'MATERIAL', 2 => 'MANO DE OBRA', 3 => 'MAQUINARIA Y HERRAMIENTAS'] as $typeId => $label) {
            $typeRows = $rowsByType->get($typeId, collect())->values();

            if ($typeRows->isEmpty()) {
                continue;
            }

            $rows[] = ['type' => 'section', 'values' => [$label]];
            $typeTotal = 0.0;

            foreach ($typeRows as $index => $row) {
                $typeTotal += (float) $row['parcial'];
                $rows[] = ['type' => 'data', 'values' => [
                    $index + 1,
                    $row['descripcion'],
                    $row['unidad'],
                    $row['cantidad'],
                    $row['precio_unitario'],
                    $row['parcial'],
                ]];
            }

            $grandTotal += $typeTotal;
            $rows[] = ['type' => 'subtotal', 'values' => ['SUBTOTAL '.$label, round($typeTotal, 2)]];
        }

        $rows[] = ['type' => 'total', 'values' => ['TOTAL GENERAL', round($grandTotal, 2)]];
        $rows[] = ['type' => 'literal', 'values' => ['SON:'.LegacyPdfFormat::amountLiteral($grandTotal).' BOLIVIANOS.']];

        return $this->projectReportResponse('reporte_consolidado_insumos.xlsx', 'REPORTE CONSOLIDADO DE INSUMOS DEL PROYECTO', [
            'Nro', 'Insumo', 'Unidad', 'Cantidad', 'P. Unit. (Bs)', 'Parcial (Bs)',
        ], $rows, [8, 48, 12, 16, 18, 18]);
    }

    public function groupedInputsReport(Project $project): Response
    {
        $rows = $this->projectRows($project);
        $rows[] = ['type' => 'spacer'];
        $grouped = collect($this->projectInputsGroupedReportPdfService->rows($project))->groupBy('id_insumo');
        $grandTotal = 0.0;

        foreach ($grouped as $inputRows) {
            $first = $inputRows->first();
            $inputQuantity = (float) $inputRows->sum('cantidad_total');
            $inputTotal = (float) $inputRows->sum('parcial');
            $grandTotal += $inputTotal;

            $rows[] = ['type' => 'section', 'values' => [$first['tipo_nombre'].' - '.$first['insumo'].' | Unidad: '.($first['unidad'] ?? '').' | Cantidad total: '.$this->number($inputQuantity, 4)]];

            foreach ($inputRows->values() as $index => $row) {
                $rows[] = ['type' => 'data', 'values' => [
                    $index + 1,
                    $row['prioridad'],
                    $row['item'],
                    $row['cantidad_item'],
                    $row['cantidad_insumo_item'],
                    $row['cantidad_total'],
                    $row['precio_unitario'],
                    $row['parcial'],
                ]];
            }

            $rows[] = ['type' => 'subtotal', 'values' => ['SUBTOTAL INSUMO', round($inputTotal, 2)]];
        }

        $rows[] = ['type' => 'total', 'values' => ['TOTAL GENERAL', round($grandTotal, 2)]];
        $rows[] = ['type' => 'literal', 'values' => ['SON:'.LegacyPdfFormat::amountLiteral($grandTotal).' BOLIVIANOS.']];

        return $this->projectReportResponse('proyecto_agrupado_por_insumos.xlsx', 'REPORTE DE PROYECTO AGRUPADO POR INSUMOS', [
            'Nro', 'Pr.', 'Item', 'Cant. Item', 'Cant. Ins.', 'Cant. Total', 'Unit.(Bs)', 'Parcial(Bs)',
        ], $rows, [8, 8, 42, 14, 14, 14, 16, 16]);
    }

    private function budgetByGroupResponse(Project $project, array $budget, string $filename, array $metadata = []): Response
    {
        $rows = [];

        foreach ($metadata as $metadataRow) {
            $rows[] = ['type' => 'meta', 'values' => $metadataRow];
        }

        $rows[] = ['type' => 'spacer'];
        $hasModules = collect($budget['items'] ?? [])->contains(fn (array $item): bool => array_key_exists('modulo', $item));
        $lastModule = null;
        $moduleTotals = ['materiales' => 0.0, 'mano_obra' => 0.0, 'herramientas' => 0.0];
        $lastGroupId = null;
        $lastSubgroupId = null;

        foreach (($budget['items'] ?? []) as $index => $item) {
            if ($hasModules && $lastModule !== ($item['modulo'] ?? 'General')) {
                if ($lastModule !== null) {
                    $rows[] = ['type' => 'subtotal', 'values' => [
                        'Subtotal módulo '.$lastModule,
                        '',
                        round($moduleTotals['materiales'], 4),
                        round($moduleTotals['mano_obra'], 4),
                        round($moduleTotals['herramientas'], 4),
                    ]];
                }

                $lastModule = $item['modulo'] ?? 'General';
                $moduleTotals = ['materiales' => 0.0, 'mano_obra' => 0.0, 'herramientas' => 0.0];
                $lastGroupId = null;
                $lastSubgroupId = null;
                $rows[] = ['type' => 'module', 'values' => ['MÓDULO: '.$lastModule]];
            }

            if ($lastGroupId !== ($item['id_grupo'] ?? null)) {
                $rows[] = ['type' => 'group', 'values' => [$item['grupo'] ?? '']];
                $lastGroupId = $item['id_grupo'] ?? null;
            }

            if ($lastSubgroupId !== ($item['id_subgrupo'] ?? null)) {
                $rows[] = ['type' => 'subgroup', 'values' => [$item['subgrupo'] ?? '']];
                $lastSubgroupId = $item['id_subgrupo'] ?? null;
            }

            $moduleTotals['materiales'] += (float) ($item['materiales'] ?? 0);
            $moduleTotals['mano_obra'] += (float) ($item['mano_obra'] ?? 0);
            $moduleTotals['herramientas'] += (float) ($item['herramientas'] ?? 0);

            $rows[] = ['type' => 'data', 'values' => [
                $index + 1,
                $item['descripcion'] ?? '',
                (float) ($item['materiales'] ?? 0),
                (float) ($item['mano_obra'] ?? 0),
                (float) ($item['herramientas'] ?? 0),
            ]];
        }

        if ($hasModules && $lastModule !== null) {
            $rows[] = ['type' => 'subtotal', 'values' => [
                'Subtotal módulo '.$lastModule,
                '',
                round($moduleTotals['materiales'], 4),
                round($moduleTotals['mano_obra'], 4),
                round($moduleTotals['herramientas'], 4),
            ]];
        }

        $totals = $budget['totals'] ?? [];
        $rows[] = ['type' => 'total', 'values' => ['Totales por rubro (Bs):', '', (float) ($totals['materiales'] ?? 0), (float) ($totals['mano_obra'] ?? 0), (float) ($totals['herramientas'] ?? 0)]];

        return $this->projectReportResponse($filename, 'Presupuesto por Rubros', [
            'Nº', 'Descripcion Item', 'Materiales', 'Mano de Obra', 'Maquinaria y Herram.',
        ], $rows, [8, 54, 18, 18, 22]);
    }

    private function projectReportResponse(string $filename, string $title, array $columns, array $rows, array $widths): Response
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr(preg_replace('/[\\\\\/\\?\\*\\[\\]:]/', ' ', $title) ?: 'Reporte', 0, 31));

        $columnCount = count($columns);
        $headerColumnCount = max($columnCount, 6);
        $lastColumn = Coordinate::stringFromColumnIndex($columnCount);
        $rowNumber = MunicipalXlsxHeader::apply($sheet, $title, $headerColumnCount);

        foreach ($widths as $index => $width) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index + 1))->setWidth($width);
        }

        foreach ($columns as $index => $column) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($index + 1).$rowNumber, $column);
        }
        $sheet->getStyle('A'.$rowNumber.':'.$lastColumn.$rowNumber)->applyFromArray($this->xlsxHeaderStyle());
        $rowNumber++;

        foreach ($rows as $row) {
            $type = $row['type'] ?? 'data';
            $values = $row['values'] ?? [];

            if ($type === 'spacer') {
                $rowNumber++;
                continue;
            }

            if (in_array($type, ['project', 'meta'], true)) {
                $sheet->setCellValue('A'.$rowNumber, $values[0] ?? '');
                $sheet->setCellValue('B'.$rowNumber, $values[1] ?? '');
                $sheet->mergeCells('B'.$rowNumber.':'.$lastColumn.$rowNumber);
                $sheet->getStyle('A'.$rowNumber.':'.$lastColumn.$rowNumber)->getFont()->setBold(true);
                $rowNumber++;
                continue;
            }

            if (in_array($type, ['module', 'group', 'subgroup', 'subgroupLight', 'section', 'literal'], true)) {
                $sheet->mergeCells('A'.$rowNumber.':'.$lastColumn.$rowNumber);
                $sheet->setCellValue('A'.$rowNumber, $values[0] ?? '');
                $sheet->getStyle('A'.$rowNumber.':'.$lastColumn.$rowNumber)->applyFromArray($this->styleForRowType($type));
                $rowNumber++;
                continue;
            }

            if (in_array($type, ['subtotal', 'total'], true)) {
                if (count($values) >= $columnCount) {
                    foreach (array_slice($values, 0, $columnCount) as $index => $value) {
                        $cell = Coordinate::stringFromColumnIndex($index + 1).$rowNumber;
                        $this->setReportCellValue($sheet, $cell, $value, $index, $columns);
                        if (is_numeric($value)) {
                            $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        }
                    }
                } else {
                    $sheet->setCellValue('A'.$rowNumber, $values[0] ?? '');
                    if ($columnCount > 2) {
                        $sheet->mergeCells('A'.$rowNumber.':'.Coordinate::stringFromColumnIndex($columnCount - 1).$rowNumber);
                    }
                    $lastValue = $values[count($values) - 1] ?? 0;
                    $this->setReportCellValue($sheet, $lastColumn.$rowNumber, $lastValue, $columnCount - 1, $columns);
                    $sheet->getStyle($lastColumn.$rowNumber)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }
                $sheet->getStyle('A'.$rowNumber.':'.$lastColumn.$rowNumber)->applyFromArray($type === 'total' ? $this->xlsxTotalStyle() : $this->xlsxSubtotalStyle());
                $rowNumber++;
                continue;
            }

            foreach ($values as $index => $value) {
                $cell = Coordinate::stringFromColumnIndex($index + 1).$rowNumber;
                $this->setReportCellValue($sheet, $cell, $value, $index, $columns);
                if (is_numeric($value) && $index >= 2) {
                    $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }
            }
            $rowNumber++;
        }

        $lastRow = max(7, $rowNumber - 1);
        $sheet->freezePane('A8');
        $sheet->getStyle('A7:'.$lastColumn.$lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFD6E4E2'));
        $sheet->getStyle('A1:'.$lastColumn.$lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getPageSetup()->setFitToWidth(1)->setFitToHeight(0);

        $tempPath = tempnam(sys_get_temp_dir(), 'sipre_xlsx_');
        (new Xlsx($spreadsheet))->save($tempPath);
        $content = file_get_contents($tempPath);
        @unlink($tempPath);
        $spreadsheet->disconnectWorksheets();

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    private function projectRows(Project $project): array
    {
        return [[
            'type' => 'project',
            'values' => ['PROYECTO:', mb_strtoupper((string) $project->nombre_proyecto, 'UTF-8')],
        ]];
    }

    private function styleForRowType(string $type): array
    {
        return match ($type) {
            'module' => ['font' => ['bold' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '8CB9B5']]],
            'group' => ['font' => ['bold' => true, 'color' => ['rgb' => 'FCFDFD']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '99A3A2']]],
            'subgroup' => ['font' => ['bold' => true, 'color' => ['rgb' => 'FCFDFD']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '55827E']]],
            'section' => ['font' => ['bold' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'CCEBE8']]],
            'literal' => ['font' => ['bold' => true]],
            default => ['font' => ['bold' => true]],
        };
    }

    private function xlsxHeaderStyle(): array
    {
        return [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FCFDFD']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '55827E']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];
    }

    private function xlsxSubtotalStyle(): array
    {
        return [
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8F5F3']],
        ];
    }

    private function xlsxTotalStyle(): array
    {
        return [
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'CCEBE8']],
        ];
    }

    private function number(float $value, int $decimals = 2): string
    {
        return LegacyPdfFormat::number($value, $decimals);
    }

    private function setReportCellValue(Worksheet $sheet, string $cell, mixed $value, int $index, array $columns): void
    {
        if (! is_numeric($value) || $index < 2) {
            $sheet->setCellValue($cell, $value);

            return;
        }

        $sheet->setCellValueExplicit(
            $cell,
            LegacyPdfFormat::number((float) $value, $this->decimalsForColumn((string) ($columns[$index] ?? ''))),
            DataType::TYPE_STRING,
        );
    }

    private function decimalsForColumn(string $column): int
    {
        $normalized = mb_strtoupper($column, 'UTF-8');

        if (str_contains($normalized, 'CANT')) {
            return 4;
        }

        if (str_contains($normalized, 'MATERIALES') || str_contains($normalized, 'MANO DE OBRA') || str_contains($normalized, 'MAQUINARIA')) {
            return 4;
        }

        return 2;
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

    private function inputBreakdownTitle(int $type): string
    {
        return match ($type) {
            1 => 'Desglose de insumos general:MATERIALES',
            2 => 'Desglose de insumos general:MANO DE OBRA',
            3 => 'Desglose de insumos general:EQUIPO, MAQUINARIA Y HERRAMIENTAS',
            default => 'Desglose de insumos general',
        };
    }
}

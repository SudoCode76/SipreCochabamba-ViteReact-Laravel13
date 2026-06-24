<?php

namespace App\Support\Xlsx;

use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MunicipalXlsxHeader
{
    public static function apply(Worksheet $sheet, string $title, int $columnCount = 6): int
    {
        $columnCount = max($columnCount, 6);
        $lastColumn = self::columnName($columnCount);
        $printedAt = now('America/La_Paz')->format('d/m/Y H:i:s');

        $sheet->mergeCells('A1:D1');
        $sheet->setCellValue('A1', 'GOBIERNO AUTONOMO MUNICIPAL DE COCHABAMBA');
        $sheet->setCellValue('E1', 'FECHA IMPRESION:');
        if ($lastColumn !== 'F') {
            $sheet->mergeCells('F1:'.$lastColumn.'1');
        }
        $sheet->setCellValue('F1', $printedAt);

        $sheet->mergeCells('A2:'.$lastColumn.'2');
        $sheet->setCellValue('A2', 'SECRETARIA DE PLANIFICACION');
        $sheet->mergeCells('A3:'.$lastColumn.'3');
        $sheet->setCellValue('A3', 'DIRECCION DE PROYECTOS');
        $sheet->mergeCells('A4:'.$lastColumn.'4');
        $sheet->setCellValue('A4', 'COCHABAMBA-BOLIVIA');
        $sheet->mergeCells('A5:'.$lastColumn.'5');
        $sheet->setCellValue('A5', $title);

        $sheet->getStyle('A1:'.$lastColumn.'4')->getFont()->setBold(true)->setSize(9);
        $sheet->getStyle('A5:'.$lastColumn.'5')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A5:'.$lastColumn.'5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('E1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('F1:'.$lastColumn.'1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        return 7;
    }

    public static function columnName(int $column): string
    {
        $name = '';

        while ($column > 0) {
            $column--;
            $name = chr(65 + ($column % 26)).$name;
            $column = intdiv($column, 26);
        }

        return $name;
    }
}

<?php

namespace App\Support\Xlsx;

use Illuminate\Http\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SimpleXlsxResponse
{
    /**
     * @param  array<int, array{title: string, rows: array<int, array<int, mixed>>}>  $sheets
     */
    public static function make(string $filename, array $sheets): Response
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        foreach ($sheets as $index => $sheetData) {
            $sheet = $spreadsheet->createSheet($index);
            $sheet->setTitle(self::safeSheetTitle($sheetData['title']));
            $columnCount = self::maxColumnCount($sheetData['rows']);
            $startRow = MunicipalXlsxHeader::apply($sheet, $sheetData['title'], $columnCount);
            $sheet->fromArray($sheetData['rows'], null, 'A'.$startRow, true);
            self::styleSheet($sheet, $startRow, count($sheetData['rows']), $columnCount);
            $sheet->freezePane('A'.($startRow + 1));
        }

        if ($spreadsheet->getSheetCount() === 0) {
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle('Datos');
            $startRow = MunicipalXlsxHeader::apply($sheet, 'Datos');
            $sheet->fromArray([['Sin datos']], null, 'A'.$startRow, true);
            self::styleSheet($sheet, $startRow, 1, 6);
        }

        $spreadsheet->setActiveSheetIndex(0);

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

    private static function safeSheetTitle(string $title): string
    {
        $clean = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', ' ', $title) ?: 'Datos';

        return mb_substr(trim($clean) ?: 'Datos', 0, 31);
    }

    private static function maxColumnCount(array $rows): int
    {
        $max = 6;

        foreach ($rows as $row) {
            if (is_array($row)) {
                $max = max($max, count($row));
            }
        }

        return $max;
    }

    private static function styleSheet($sheet, int $startRow, int $rowCount, int $columnCount): void
    {
        $lastColumn = MunicipalXlsxHeader::columnName(max($columnCount, 6));
        $lastRow = max($startRow, $startRow + $rowCount - 1);

        foreach (range(1, max($columnCount, 6)) as $column) {
            $sheet->getColumnDimension(MunicipalXlsxHeader::columnName($column))->setAutoSize(true);
        }

        $sheet->getStyle('A'.$startRow.':'.$lastColumn.$startRow)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '55827E']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getStyle('A'.$startRow.':'.$lastColumn.$lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('A'.$startRow.':'.$lastColumn.$lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
    }
}

<?php

namespace App\Support\Xlsx;

use Illuminate\Http\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
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
            $sheet->fromArray($sheetData['rows'], null, 'A1', true);
            $sheet->freezePane('A2');
        }

        if ($spreadsheet->getSheetCount() === 0) {
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle('Datos');
            $sheet->fromArray([['Sin datos']], null, 'A1', true);
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
}

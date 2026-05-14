<?php

namespace App\Support\Pdf;

use Illuminate\Http\Response;

class MunicipalReportPdfFactory
{
    public static function make(string $title): MunicipalReportPdf
    {
        $pdf = new MunicipalReportPdf(
            $title,
            'P',
            defined('PDF_UNIT') ? PDF_UNIT : 'mm',
            'A4',
            true,
            'UTF-8',
            false,
        );

        $pdf->SetHeaderData(
            defined('PDF_HEADER_LOGO') ? PDF_HEADER_LOGO : '',
            defined('PDF_HEADER_LOGO_WIDTH') ? PDF_HEADER_LOGO_WIDTH : 0,
            (defined('PDF_HEADER_TITLE') ? PDF_HEADER_TITLE : '').' 001',
            defined('PDF_HEADER_STRING') ? PDF_HEADER_STRING : '',
            [0, 64, 25],
            [0, 64, 128],
        );
        $pdf->setFooterFont([
            defined('PDF_FONT_NAME_DATA') ? PDF_FONT_NAME_DATA : 'helvetica',
            '',
            defined('PDF_FONT_SIZE_DATA') ? PDF_FONT_SIZE_DATA : 8,
        ]);
        $pdf->setFooterData([0, 64, 0], [0, 64, 128]);
        $pdf->SetDefaultMonospacedFont(defined('PDF_FONT_MONOSPACED') ? PDF_FONT_MONOSPACED : 'courier');
        $pdf->SetMargins(
            defined('PDF_MARGIN_LEFT') ? PDF_MARGIN_LEFT : 15,
            defined('PDF_MARGIN_TOP') ? PDF_MARGIN_TOP : 27,
            defined('PDF_MARGIN_RIGHT') ? PDF_MARGIN_RIGHT : 15,
        );
        $pdf->SetMargins(
            defined('PDF_MARGIN_LEFT') ? PDF_MARGIN_LEFT : 15,
            35,
            defined('PDF_MARGIN_RIGHT') ? PDF_MARGIN_RIGHT : 15,
        );
        $pdf->SetHeaderMargin(defined('PDF_MARGIN_HEADER') ? PDF_MARGIN_HEADER : 5);
        $pdf->SetFooterMargin(defined('PDF_MARGIN_FOOTER') ? PDF_MARGIN_FOOTER : 10);
        $pdf->SetAutoPageBreak(true, defined('PDF_MARGIN_BOTTOM') ? PDF_MARGIN_BOTTOM : 25);
        $pdf->setImageScale(defined('PDF_IMAGE_SCALE_RATIO') ? PDF_IMAGE_SCALE_RATIO : 1.25);
        $pdf->setFontSubsetting(true);
        $pdf->AddPage('P', 'A4');
        $pdf->SetDisplayMode('real');
        $pdf->SetAutoPageBreak(true, 10);
        $pdf->SetFont('dejavusans', '', 7, '', true);

        return $pdf;
    }

    public static function inlineResponse(MunicipalReportPdf $pdf, string $filename): Response
    {
        $content = $pdf->Output($filename, 'S');

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}

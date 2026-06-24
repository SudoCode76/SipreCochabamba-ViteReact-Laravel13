<?php

namespace App\Modules\Projects\Services;

use App\Models\Project;
use App\Modules\Items\Services\LegacyUnitPriceAnalysisPdfService;
use Illuminate\Http\Response;
use TCPDF;

class ProjectUnitPricesPdfService
{
    public function __construct(
        private readonly ProjectBudgetService $projectBudgetService,
        private readonly LegacyUnitPriceAnalysisPdfService $legacyUnitPriceAnalysisPdfService,
    ) {}

    public function stream(Project $project, string $format): Response
    {
        $items = $this->projectBudgetService->unitPricesForLegacyProjectPdf($project, $format);
        $pdf = new LegacyProjectUnitPricesPdf('L', defined('PDF_UNIT') ? PDF_UNIT : 'mm', 'A4', true, 'UTF-8', false);
        $this->configurePdf($pdf);
        $pdf->AddPage('A4');
        $pdf->SetFont('dejavusans', '', 7, '', true);
        $pdf->Ln();

        if ($items === []) {
            $pdf->writeHTML('<p>No existen ítems activos para imprimir.</p>', true, false, true, false, '');

            return $this->inlineResponse($pdf);
        }

        foreach ($items as $row) {
            $analysis = $row['analysis'];
            [$html, $missingParametersHtml] = $this->legacyUnitPriceAnalysisPdfService->buildLegacyHtml($analysis, true);

            $pdf->Ln();
            $pdf->SetFont('dejavusans', '', 8, '', true);
            $pdf->writeHTML($missingParametersHtml ?? $html, true, false, true, false, '');
            $pdf->AddPage();
            $pdf->SetFont('dejavusans', '', 10, '', true);
        }

        return $this->inlineResponse($pdf);
    }

    private function configurePdf(LegacyProjectUnitPricesPdf $pdf): void
    {
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
            35,
            defined('PDF_MARGIN_RIGHT') ? PDF_MARGIN_RIGHT : 15,
        );
        $pdf->SetHeaderMargin(defined('PDF_MARGIN_HEADER') ? PDF_MARGIN_HEADER : 5);
        $pdf->SetFooterMargin(defined('PDF_MARGIN_FOOTER') ? PDF_MARGIN_FOOTER : 10);
        $pdf->SetAutoPageBreak(true, defined('PDF_MARGIN_BOTTOM') ? PDF_MARGIN_BOTTOM : 25);
        $pdf->setImageScale(defined('PDF_IMAGE_SCALE_RATIO') ? PDF_IMAGE_SCALE_RATIO : 1.25);
        $pdf->setFontSubsetting(true);
        $pdf->SetDisplayMode('real');
        $pdf->SetAutoPageBreak(true, 10);
    }

    private function inlineResponse(LegacyProjectUnitPricesPdf $pdf): Response
    {
        $filename = 'analisis_de_precios_unitarios_print.pdf';

        return response($pdf->Output($filename, 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}

class LegacyProjectUnitPricesPdf extends TCPDF
{
    public function Header(): void
    {
        date_default_timezone_set('America/La_Paz');
        setlocale(LC_TIME, 'es_RB');

        $this->SetY(10);
        $this->SetFont('helvetica', 'B', 9, '', true);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(120, 4, 'GOBIERNO AUTONOMO MUNICIPAL DE COCHABAMBA', 0, 0, 'L', 0, '', 3);
        $this->Cell(30, 4, 'FECHA IMPRESION:', 0, 0, 'L', 0, '', 3);
        $this->Cell(30, 4, date('d/m/Y H:i:s'), 0, 1, 'C', 0, '', 0, false, 'T', 'M');
        $this->Cell(180, 4, 'SECRETARIA DE PLANIFICACION', 0, 1, 'L', 0, '', 3);
        $this->Cell(180, 4, 'DIRECCION DE PROYECTOS', 0, 1, 'L', 0, '', 3);
        $this->Cell(180, 4, 'COCHABAMBA-BOLIVIA', 0, 1, 'L', 0, '', 3);
        $this->SetFont('helvetica', 'B', 11, '', true);
        $this->top_margin = $this->GetY() + 100;
        $this->writeHTML('', true, false, true, false, '');
    }

    public function Footer(): void
    {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'N', 6);
        $this->Cell(0, 10, 'Pagina '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

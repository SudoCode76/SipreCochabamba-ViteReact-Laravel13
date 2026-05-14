<?php

namespace App\Support\Pdf;

class MunicipalReportPdf extends \TCPDF
{
    public function __construct(
        private readonly string $reportTitle,
        mixed ...$tcpdfArgs,
    ) {
        parent::__construct(...$tcpdfArgs);
    }

    public function Header(): void
    {
        date_default_timezone_set('America/La_Paz');
        setlocale(LC_TIME, 'es_RB');

        $printableWidth = $this->getPageWidth() - $this->getMargins()['left'] - $this->getMargins()['right'];
        $headerScale = $printableWidth / 180;
        $titleWidth = 180 * $headerScale;
        $leftWidth = 120 * $headerScale;
        $labelWidth = 30 * $headerScale;
        $valueWidth = 30 * $headerScale;

        $this->SetY(10);
        $this->SetFont('helvetica', 'B', 9, '', true);
        $this->SetTextColor(0, 0, 0);
        $this->Cell($leftWidth, 4, 'GOBIERNO AUTONOMO MUNICIPAL DE COCHABAMBA', 0, 0, 'L', 0, '', 3);
        $this->Cell($labelWidth, 4, 'FECHA IMPRESION:', 0, 0, 'L', 0, '', 3);
        $this->Cell($valueWidth, 4, date('m/d/Y H:i:s'), 0, 1, 'C', 0, '', 0, false, 'T', 'M');
        $this->Cell($titleWidth, 4, 'SECRETARIA DE PLANIFICACION', 0, 1, 'L', 0, '', 3);
        $this->Cell($titleWidth, 4, 'DIRECCION DE PROYECTOS', 0, 1, 'L', 0, '', 3);
        $this->Cell($titleWidth, 4, 'COCHABAMBA-BOLIVIA', 0, 1, 'L', 0, '', 3);
        $this->SetFont('helvetica', 'B', 11, '', true);
        $this->Cell($titleWidth, 8, $this->reportTitle, 0, 1, 'C', 0, '', 3);

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

<?php

namespace App\Support\Pdf;

class ProjectGeneralBudgetPdf extends \TCPDF
{
    public function Header(): void
    {
        $printableWidth = $this->getPageWidth() - $this->getMargins()['left'] - $this->getMargins()['right'];
        $headerScale = $printableWidth / 180;
        $leftWidth = 120 * $headerScale;
        $labelWidth = 30 * $headerScale;
        $valueWidth = 30 * $headerScale;
        $lineWidth = 180 * $headerScale;

        $this->SetY(8);
        $this->SetFont('helvetica', 'B', 8, '', true);
        $this->SetTextColor(0, 0, 0);
        $this->Cell($leftWidth, 4, 'GOBIERNO AUTONOMO MUNICIPAL DE COCHABAMBA', 0, 0, 'L', 0, '', 3);
        $this->Cell($labelWidth, 4, 'FECHA IMPRESION:', 0, 0, 'L', 0, '', 3);
        $this->Cell($valueWidth, 4, date('m/d/Y H:i:s'), 0, 1, 'C', 0, '', 0, false, 'T', 'M');
        $this->Cell($lineWidth, 4, 'SECRETARIA DE PLANIFICACION', 0, 1, 'L', 0, '', 3);
        $this->Cell($lineWidth, 4, 'DIRECCION DE PROYECTOS', 0, 1, 'L', 0, '', 3);
        $this->Cell($lineWidth, 4, 'COCHABAMBA-BOLIVIA', 0, 1, 'L', 0, '', 3);
        $this->top_margin = $this->GetY() + 10;
        $this->writeHTML('', true, false, true, false, '');
    }

    public function Footer(): void
    {
        $this->SetY(-20);
        $this->SetFont('helvetica', 'N', 6);
        $this->Cell(0, 10, 'Pagina '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

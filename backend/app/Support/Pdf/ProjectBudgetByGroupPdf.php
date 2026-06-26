<?php

namespace App\Support\Pdf;

class ProjectBudgetByGroupPdf extends \TCPDF
{
    public function Header(): void
    {
        $printableWidth = $this->getPageWidth() - $this->getMargins()['left'] - $this->getMargins()['right'];
        $headerScale = $printableWidth / 180;
        $titleWidth = 180 * $headerScale;

        $this->SetY(10);
        $this->SetFont('helvetica', 'B', 9, '', true);
        $this->SetTextColor(0, 0, 0);
        $this->Cell($titleWidth, 4, 'GOBIERNO AUTONOMO MUNICIPAL DE COCHABAMBA', 0, 1, 'L', 0, '', 3);
        $this->Cell($titleWidth, 4, 'COCHABAMBA-BOLIVIA', 0, 1, 'L', 0, '', 3);
        $this->SetFont('helvetica', 'B', 11, '', true);
        $this->Cell($titleWidth, 8, 'Presupuesto por rubros', 0, 1, 'C', 0, '', 3);

        $this->top_margin = $this->GetY() + 100;
        $this->writeHTML('', true, false, true, false, '');
    }

    public function Footer(): void
    {
        date_default_timezone_set('America/La_Paz');

        $this->SetY(-15);
        $this->SetFont('helvetica', 'N', 6);
        $this->Cell(0, 10, 'Pagina '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
        $this->Cell(0, 5, date('d/m/Y H\hi:s'), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

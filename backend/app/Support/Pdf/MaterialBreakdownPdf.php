<?php

namespace App\Support\Pdf;

class MaterialBreakdownPdf extends \TCPDF
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
        $this->Cell(30, 4, date('m/d/Y H:i:s'), 0, 1, 'C', 0, '', 0, false, 'T', 'M');
        $this->Cell(180, 4, 'SECRETARIA DE PLANIFICACION', 0, 1, 'L', 0, '', 3);
        $this->Cell(180, 4, 'DIRECCION DE PROYECTOS', 0, 1, 'L', 0, '', 3);
        $this->Cell(180, 4, 'COCHABAMBA-BOLIVIA', 0, 1, 'L', 0, '', 3);
        $this->SetFont('helvetica', 'B', 11, '', true);
        $this->Cell(180, 8, 'Desglose de insumos general:MATERIALES', 0, 1, 'C', 0, '', 3);

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

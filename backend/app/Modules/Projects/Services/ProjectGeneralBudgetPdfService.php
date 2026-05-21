<?php

namespace App\Modules\Projects\Services;

use App\Models\Project;
use App\Support\Pdf\LegacyPdfFormat;
use App\Support\Pdf\ProjectGeneralBudgetPdf;
use Illuminate\Http\Response;

class ProjectGeneralBudgetPdfService
{
    public function __construct(
        private readonly ProjectBudgetService $projectBudgetService,
        private readonly ProjectLegacyUnitPriceService $projectLegacyUnitPriceService,
    ) {}

    public function stream(Project $project, string $format): Response
    {
        $items = $this->projectBudgetService->generalBudgetPdfItems($project, $format, $this->projectLegacyUnitPriceService);
        $pdf = $this->makePdf();

        if ($items === []) {
            $pdf->writeHTML('<div><h1 style="color:red" align="center">No existen registros!</h1></div>', true, false, true, false, '');
        } else {
            $pdf->writeHTML($this->buildHtml($project, $items), true, false, true, false, '');
        }

        return response($pdf->Output('presupuesto_general.pdf', 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="presupuesto_general.pdf"',
        ]);
    }

    private function buildHtml(Project $project, array $items): string
    {
        $total = 0.0;
        $lastGroup = null;
        $lastSubgroup = null;
        $html = '
<style>
.subseccion{background-color:#C8EFE6;font-size:10px;font-style:bold;}
.head{font-style:bold;color:black;font-size:10px;}
</style>
<table cellpadding="5px">
<tr><th colspan="4"><h1 align="center">Presupuesto General Del Proyecto</h1></th></tr>
<tr class="head">
<th width="100" height="20"><b>PROYECTO:</b></th>
<th colspan="3"><b>'.htmlentities((string) $project->nombre_proyecto).'</b></th>
</tr>
</table>
<table cellpadding="4px" border="1">
<thead>
<tr bgcolor="#55827e">
<th width="40"><b><font color="#fcfdfd">Nº</font></b></th>
<th width="280"><b><font color="#fcfdfd">Descripción</font></b></th>
<th width="50"><b><font color="#fcfdfd">Unid.</font></b></th>
<th width="80"><b><font color="#fcfdfd">Cantidad</font></b></th>
<th width="150"><b><font color="#fcfdfd">Unitario</font></b></th>
<th width="80"><b><font color="#fcfdfd">Parcial (Bs)</font></b></th>
</tr>
</thead>
<tbody>';

        foreach ($items as $index => $item) {
            if ($lastGroup !== $item['nombre_grupo']) {
                $html .= '
<tr nobr="true" bgcolor="#99a3a2"><td width="680" colspan="6"><h4><font color="#fcfdfd">'.htmlentities((string) $item['nombre_grupo']).'</font></h4></td></tr>';
                $lastGroup = $item['nombre_grupo'];
            }

            if ($lastSubgroup !== $item['nombre_subgrupo']) {
                $html .= '
<tr nobr="true" bgcolor="#55827e"><td width="680" colspan="6"><h4><font color="#fcfdfd">'.htmlentities((string) $item['nombre_subgrupo']).'</font></h4></td></tr>';
                $lastSubgroup = $item['nombre_subgrupo'];
            }

            $price = round((float) $item['precio'], 2);
            $quantity = round((float) $item['cantidad'], 4);
            $subtotal = $quantity * $price;
            $total += $subtotal;

            $html .= '
<tr nobr="true">
<td width="40">'.($index + 1).'</td>
<td width="280">'.htmlentities((string) $item['nombre_item']).'</td>
<td width="50">'.htmlentities((string) $item['unidad']).'</td>
<td width="80" align="right">'.LegacyPdfFormat::number($quantity, 4).'</td>
<td width="150" align="right">'.LegacyPdfFormat::number($price, 2).'</td>
<td width="80" align="right">'.LegacyPdfFormat::number($subtotal, 2).'</td>
</tr>';
        }

        $total = round($total, 2);
        $totalLiteral = 'SON: BOLIVIANOS '.ltrim(LegacyPdfFormat::amountLiteral($total));
        $html .= '
<tr class="subseccion" nobr="true">
<td colspan="4"><b>TOTAL</b></td>
<td colspan="2" align="right"><b>'.LegacyPdfFormat::number($total, 2).'</b></td>
</tr>
<tr nobr="true">
<td colspan="6">'.$totalLiteral.'</td>
</tr>
</tbody>
</table>';

        return $html;
    }

    private function makePdf(): ProjectGeneralBudgetPdf
    {
        $pdf = new ProjectGeneralBudgetPdf(
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
            [0, 10, 25],
            [0, 10, 128],
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
            25,
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
}

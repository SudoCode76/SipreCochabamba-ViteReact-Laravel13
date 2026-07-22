<?php

namespace App\Modules\Projects\Services;

use App\Models\Project;
use App\Support\Pdf\LegacyPdfFormat;
use App\Support\Pdf\MunicipalReportPdfFactory;
use App\Support\Pdf\ProjectBudgetByGroupPdf;
use Illuminate\Http\Response;

class ProjectBudgetByGroupPdfService
{
    public function __construct(
        private readonly ProjectBudgetService $projectBudgetService,
    ) {}

    public function stream(Project $project, ?array $signatureLayout = null): Response
    {
        $budget = $this->projectBudgetService->budgetByGroupPdfData($project);

        return $this->streamBudget($project, $budget, $signatureLayout);
    }

    public function streamHistorical(Project $project, array $budget, ?array $signatureLayout = null): Response
    {
        return $this->streamBudget($project, array_merge([
            'items_proyecto_count' => count($budget['items'] ?? []),
        ], $budget), $signatureLayout);
    }

    private function streamBudget(Project $project, array $budget, ?array $signatureLayout): Response
    {
        $items = $budget['items'] ?? [];

        $pdf = $this->makePdf();
        MunicipalReportPdfFactory::render($pdf, function () use ($budget, $items, $pdf, $project): void {
            $pdf->ln();

            if (($budget['items_proyecto_count'] ?? 0) === 0) {
                $pdf->writeHTML('<div><h1>El proyecto no tiene item registrados aun, por favor agregue item</h1></div>', true, false, true, false, '');
            } elseif ($items === []) {
                $pdf->writeHTML('<div><h1>El proyecto  tiene algun item que aun no fue creado por completo, por favor revise la existencia del item</h1></div>', true, false, true, false, '');
            } else {
                $pdf->SetFont('dejavusans', '', 8, '', true);
                $pdf->writeHTML($this->buildHtml($project, $items, $budget['totals'] ?? []), true, false, true, false, '');
            }
        }, $signatureLayout);

        return response($pdf->Output('presupuesto_por_rubros.pdf', 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="presupuesto_por_rubros.pdf"',
        ]);
    }

    private function buildHtml(Project $project, array $items, array $totals): string
    {
        $projectName = htmlentities(strtoupper((string) $project->nombre_proyecto));
        $html = '
 <style>
  .subseccion{
    background-color: #C8EFE6;
    font-size: 10px;
    font-style: italic;
  }
 </style>
 <table cellpadding="6px">
 <tr><td><h3>Proyecto:</h3></td><td colspan="5"><h3>'.$projectName.'</h3></td></tr>
 </table>
 <table cellpadding="6px" border="1">
   <tr bgcolor="#55827e">
   <th width="30"><b><font size="10px" color="#ffffff">Nº</font></b></th>
   <th width="250"><b><font size="10px" color="#ffffff">Descripcion Item</font></b></th>
   <th><b><font size="10px" color="#ffffff">Materiales</font></b></th>
   <th><b><font size="10px" color="#ffffff">Mano de Obra</font></b></th>
   <th><b><font size="10px" color="#ffffff">Maquinaria y Herram.</font></b></th>
   </tr>
 ';

        $hasModules = collect($items)->contains(fn (array $item): bool => array_key_exists('modulo', $item));
        $lastModule = null;
        $moduleTotals = ['materiales' => 0.0, 'mano_obra' => 0.0, 'herramientas' => 0.0];
        $lastGroupId = null;
        $lastSubgroupId = null;

        foreach ($items as $index => $item) {
            if ($hasModules && $lastModule !== ($item['modulo'] ?? 'General')) {
                if ($lastModule !== null) {
                    $html .= $this->moduleSubtotalHtml($lastModule, $moduleTotals);
                }

                $lastModule = $item['modulo'] ?? 'General';
                $moduleTotals = ['materiales' => 0.0, 'mano_obra' => 0.0, 'herramientas' => 0.0];
                $lastGroupId = null;
                $lastSubgroupId = null;
                $html .= '
          <tr bgcolor="#8cb9b5">
            <td colspan="5"><h4>MÓDULO: '.htmlentities((string) $lastModule).'</h4></td>
          </tr>';
            }

            if ($lastGroupId !== $item['id_grupo']) {
                $html .= '
          <tr bgcolor="#99a3a2">
            <td colspan="5"><h4><font color="#fcfdfd">'.htmlentities((string) $item['grupo']).'</font></h4></td>
          </tr>';
                $lastGroupId = $item['id_grupo'];
            }

            if ($lastSubgroupId !== $item['id_subgrupo']) {
                $html .= '
          <tr bgcolor="#55827e">
            <td colspan="5"><h4><font color="#fcfdfd">'.htmlentities((string) $item['subgrupo']).'</font></h4></td>
          </tr>';
                $lastSubgroupId = $item['id_subgrupo'];
            }

            $moduleTotals['materiales'] += (float) ($item['materiales'] ?? 0);
            $moduleTotals['mano_obra'] += (float) ($item['mano_obra'] ?? 0);
            $moduleTotals['herramientas'] += (float) ($item['herramientas'] ?? 0);

            $html .= '
          <tr nobr="true">
            <td width="30">'.($index + 1).'</td>
            <td width="250">'.htmlentities((string) ($item['descripcion'] ?? '')).'</td>
            <td align="right">'.LegacyPdfFormat::number((float) ($item['materiales'] ?? 0), 4).'</td>
            <td align="right">'.LegacyPdfFormat::number((float) ($item['mano_obra'] ?? 0), 4).'</td>
            <td align="right">'.LegacyPdfFormat::number((float) ($item['herramientas'] ?? 0), 4).'</td>
          </tr>';
        }

        if ($hasModules && $lastModule !== null) {
            $html .= $this->moduleSubtotalHtml($lastModule, $moduleTotals);
        }

        $html .= '
      <tr class="subseccion">
        <td colspan="2"><b>Totales por rubro (Bs):</b></td>
        <td align="right"><b>'.LegacyPdfFormat::number((float) ($totals['materiales'] ?? 0), 4).'</b></td>
        <td align="right"><b>'.LegacyPdfFormat::number((float) ($totals['mano_obra'] ?? 0), 4).'</b></td>
        <td align="right"><b>'.LegacyPdfFormat::number((float) ($totals['herramientas'] ?? 0), 4).'</b></td>
      </tr>
      </tbody>
      </table>';

        return $html;
    }

    private function moduleSubtotalHtml(string $module, array $totals): string
    {
        return '
      <tr class="subseccion">
        <td colspan="2"><b>Subtotal módulo '.htmlentities($module).'</b></td>
        <td align="right"><b>'.LegacyPdfFormat::number((float) ($totals['materiales'] ?? 0), 4).'</b></td>
        <td align="right"><b>'.LegacyPdfFormat::number((float) ($totals['mano_obra'] ?? 0), 4).'</b></td>
        <td align="right"><b>'.LegacyPdfFormat::number((float) ($totals['herramientas'] ?? 0), 4).'</b></td>
      </tr>';
    }

    private function makePdf(): ProjectBudgetByGroupPdf
    {
        $pdf = new ProjectBudgetByGroupPdf(
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
}

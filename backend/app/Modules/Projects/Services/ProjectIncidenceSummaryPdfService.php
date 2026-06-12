<?php

namespace App\Modules\Projects\Services;

use App\Models\Project;
use App\Models\ProjectPercentageSnapshot;
use App\Support\Pdf\LegacyPdfFormat;
use App\Support\Pdf\MunicipalReportPdfFactory;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class ProjectIncidenceSummaryPdfService
{
    public function __construct(
        private readonly ProjectBudgetService $projectBudgetService,
    ) {}

    public function stream(Project $project, string $format): Response
    {
        $percentages = $this->resolvePercentages($project, $format);
        $budget = $this->projectBudgetService->budgetByGroupPdfData($project);
        $items = $budget['items'] ?? [];

        $pdf = MunicipalReportPdfFactory::make('RESUMEN POR INCIDENCIA');
        $pdf->SetFont('dejavusans', '', 7, '', true);

        if (! $this->hasRequiredPercentages($percentages)) {
            $pdf->writeHTML(
                '<div><h1>La configuración de parametros de calculo esta incompleta o algun parametro escencial esta inactivo, revise su configuracion.</h1></div>',
                true,
                false,
                true,
                false,
                '',
            );
        } elseif ($items === []) {
            $pdf->writeHTML('<div><h1>El proyecto no tiene items disponibles para resumir incidencias.</h1></div>', true, false, true, false, '');
        } else {
            $pdf->writeHTML($this->buildHtml($project, $items, $percentages), true, false, true, false, '');
        }

        return MunicipalReportPdfFactory::inlineResponse($pdf, 'resumen_incidencia.pdf');
    }

    private function resolvePercentages(Project $project, string $format): array
    {
        return $this->normalizePercentages(ProjectPercentageSnapshot::query()
            ->where('id_proyecto', $project->id_proyecto)
            ->where('formato', strtoupper($format))
            ->where('estado', 'AC')
            ->get());
    }

    private function normalizePercentages(Collection $rows): array
    {
        $resolved = [];

        foreach ($rows as $row) {
            $description = mb_strtoupper((string) $row->descripcion);

            if (str_contains($description, 'CARGAS SOCIALES')) {
                $resolved['cs'] = (float) $row->porcentaje;
            }
            if (str_contains($description, 'IMPUESTO AL VALOR AGREGADO')) {
                $resolved['iva'] = (float) $row->porcentaje;
            }
            if (str_contains($description, 'HERRAMIENTAS MENORES')) {
                $resolved['hm'] = (float) $row->porcentaje;
            }
            if (str_contains($description, 'GASTOS GRALES Y ADMINISTRATIVOS')) {
                $resolved['adm'] = (float) $row->porcentaje;
            }
            if (str_contains($description, 'UTILIDAD')) {
                $resolved['util'] = (float) $row->porcentaje;
            }
            if (str_contains($description, 'IMPUESTO A LAS TRANSACCIONES')) {
                $resolved['it'] = (float) $row->porcentaje;
            }
        }

        return $resolved;
    }

    private function hasRequiredPercentages(array $percentages): bool
    {
        return count(array_intersect_key($percentages, array_flip(['cs', 'iva', 'hm', 'adm', 'util', 'it']))) === 6;
    }

    private function buildHtml(Project $project, array $items, array $percentages): string
    {
        $totals = ['f' => 0.0, 'h' => 0.0, 'l' => 0.0, 'm' => 0.0, 'o' => 0.0, 'p' => 0.0];
        $html = '
<style>
.subseccion{background-color:#C8EFE6;font-size:10px;font-style:italic;}
.head{font-style:bold;color:black;font-size:12px;}
.grupo{background-color:#99a3a2;font-size:10px;font-style:bold;color:#ffffff;}
.subgrupo{background-color:#55827e;font-size:9px;font-style:bold;color:#ffffff;}
.tab{background-color:#fff;}
.totales{font-size:9px;}
</style>
<table cellpadding="6px">
<tr class="head"><td width="90">PROYECTO:</td><td>'.htmlentities(strtoupper((string) $project->nombre_proyecto)).'</td></tr>
</table>
<br>
<table cellpadding="6px" border="1">
<tr bgcolor="#55827e">
<td width="25"><b><font color="#fcfdfd">Nº</font></b></td>
<td width="180"><b><font color="#fcfdfd">Descripcion Item</font></b></td>
<td><b><font color="#fcfdfd">(F) '.LegacyPdfFormat::number($percentages['cs'], 2).' %</font></b></td>
<td><b><font color="#fcfdfd">(H) '.LegacyPdfFormat::number($percentages['hm'], 2).' %</font></b></td>
<td><b><font color="#fcfdfd">(L) '.LegacyPdfFormat::number($percentages['adm'], 2).' %</font></b></td>
<td><b><font color="#fcfdfd">(M) '.LegacyPdfFormat::number($percentages['util'], 2).' %</font></b></td>
<td><b><font color="#fcfdfd">(O) '.LegacyPdfFormat::number($percentages['iva'], 2).' %</font></b></td>
<td><b><font color="#fcfdfd">(P) '.LegacyPdfFormat::number($percentages['it'], 2).' %</font></b></td>
</tr>';

        $lastGroupId = null;
        $lastSubgroupId = null;

        foreach ($items as $index => $item) {
            $f = $item['mano_obra'] * $percentages['cs'] / 100;
            $o = ($item['mano_obra'] + $f) * $percentages['iva'] / 100;
            $g = $item['mano_obra'] + $f + $o;
            $h = $g * $percentages['hm'] / 100;
            $i = $item['herramientas'] + $h;
            $j = $item['materiales'] + $g + $i;
            $l = $j * $percentages['adm'] / 100;
            $m = ($j + $l) * $percentages['util'] / 100;
            $n = $j + $l + $m;
            $p = $n * $percentages['it'] / 100;

            if ($lastGroupId !== $item['id_grupo']) {
                $html .= '
<tr bgcolor="#99a3a2"><td class="grupo" colspan="8"><h4><font color="#fcfdfd">'.htmlentities((string) $item['grupo']).'</font></h4></td></tr>
<tr bgcolor="#55827e"><td class="subgrupo" colspan="8"><h4><font color="#fcfdfd">'.htmlentities((string) $item['subgrupo']).'</font></h4></td></tr>';
                $lastGroupId = $item['id_grupo'];
                $lastSubgroupId = $item['id_subgrupo'];
            } elseif ($lastSubgroupId !== $item['id_subgrupo']) {
                $html .= '
<tr><td class="tab"></td><td colspan="7">'.htmlentities((string) $item['subgrupo']).'</td></tr>';
                $lastSubgroupId = $item['id_subgrupo'];
            }

            $html .= '
<tr>
<td width="25">'.($index + 1).'</td>
<td width="180">'.htmlentities((string) $item['descripcion']).'</td>
<td align="right">'.LegacyPdfFormat::number($f, 2).'</td>
<td align="right">'.LegacyPdfFormat::number($h, 2).'</td>
<td align="right">'.LegacyPdfFormat::number($l, 2).'</td>
<td align="right">'.LegacyPdfFormat::number($m, 2).'</td>
<td align="right">'.LegacyPdfFormat::number($o, 2).'</td>
<td align="right">'.LegacyPdfFormat::number($p, 2).'</td>
</tr>';

            $totals['f'] += $f;
            $totals['h'] += $h;
            $totals['l'] += $l;
            $totals['m'] += $m;
            $totals['o'] += $o;
            $totals['p'] += $p;
        }

        $html .= '
<tr class="totales">
<td colspan="2">Totales (Bs): </td>
<td align="right">'.LegacyPdfFormat::number($totals['f'], 2).'</td>
<td align="right">'.LegacyPdfFormat::number($totals['h'], 2).'</td>
<td align="right">'.LegacyPdfFormat::number($totals['l'], 2).'</td>
<td align="right">'.LegacyPdfFormat::number($totals['m'], 2).'</td>
<td align="right">'.LegacyPdfFormat::number($totals['o'], 2).'</td>
<td align="right">'.LegacyPdfFormat::number($totals['p'], 2).'</td>
</tr>
</tbody>
</table>
<p>Las referencias de las letras de cada incidencia se halla en el RESUMEN GENERAL</p>';

        return $html;
    }
}

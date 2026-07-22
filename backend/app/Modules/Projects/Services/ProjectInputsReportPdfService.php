<?php

namespace App\Modules\Projects\Services;

use App\Models\Project;
use App\Support\Pdf\LegacyPdfFormat;
use App\Support\Pdf\MunicipalReportPdfFactory;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProjectInputsReportPdfService
{
    public function __construct(
        private readonly ProjectItemInputSnapshotService $snapshotService,
    ) {}

    private const TYPE_LABELS = [
        1 => 'MATERIAL',
        2 => 'MANO DE OBRA',
        3 => 'MAQUINARIA Y HERRAMIENTAS',
    ];

    public function stream(Project $project, ?array $signatureLayout = null): Response
    {
        $rows = $this->rows($project);
        $pdf = MunicipalReportPdfFactory::make('REPORTE CONSOLIDADO DE INSUMOS DEL PROYECTO');
        MunicipalReportPdfFactory::render($pdf, function () use ($pdf, $project, $rows): void {
            $pdf->ln();

            if ($rows === []) {
                $pdf->writeHTML('<div><h1>No existen registros!</h1></div>', true, false, true, false, '');
            } else {
                $pdf->SetFont('dejavusans', '', 8, '', true);
                $pdf->ln();
                $pdf->writeHTML($this->buildHtml($project, $rows), true, false, true, false, '');
            }
        }, $signatureLayout);

        return MunicipalReportPdfFactory::inlineResponse($pdf, 'reporte_consolidado_insumos.pdf');
    }

    public function rows(Project $project): array
    {
        return $this->queryRows($project)
            ->map(function ($row): array {
                $quantity = round((float) $row->cantidad_total, 4);
                $unitPrice = round((float) $row->precio_unitario, 2);

                return [
                    'id_insumo' => (int) $row->id_insumo,
                    'tipo' => (int) $row->tipo,
                    'tipo_nombre' => self::TYPE_LABELS[(int) $row->tipo] ?? 'OTROS',
                    'descripcion' => $row->descripcion,
                    'unidad' => $row->unidad,
                    'cantidad' => $quantity,
                    'precio_unitario' => $unitPrice,
                    'parcial' => round($quantity * $unitPrice, 2),
                ];
            })
            ->all();
    }

    private function queryRows(Project $project): Collection
    {
        $this->snapshotService->ensureForProject($project);

        return DB::table('proyecto_item_insumo_snapshot')
            ->join('proyecto_item', 'proyecto_item.id_proyecto_item', '=', 'proyecto_item_insumo_snapshot.id_proyecto_item')
            ->where('proyecto_item_insumo_snapshot.estado', '<>', 'EX')
            ->where('proyecto_item.estado', 'AC')
            ->where('proyecto_item.id_proyecto', $project->id_proyecto)
            ->whereIn('proyecto_item_insumo_snapshot.tipo', [1, 2, 3])
            ->groupBy([
                'proyecto_item_insumo_snapshot.id_insumo',
                'proyecto_item_insumo_snapshot.descripcion',
                'proyecto_item_insumo_snapshot.tipo',
                'proyecto_item_insumo_snapshot.precio_unitario',
                'proyecto_item_insumo_snapshot.unidad',
            ])
            ->orderBy('proyecto_item_insumo_snapshot.tipo')
            ->orderBy('proyecto_item_insumo_snapshot.descripcion')
            ->select([
                'proyecto_item_insumo_snapshot.id_insumo',
                'proyecto_item_insumo_snapshot.descripcion',
                'proyecto_item_insumo_snapshot.tipo',
                'proyecto_item_insumo_snapshot.precio_unitario',
                'proyecto_item_insumo_snapshot.unidad',
                DB::raw('SUM(proyecto_item_insumo_snapshot.cantidad * proyecto_item.cantidad) as cantidad_total'),
            ])
            ->get();
    }

    private function buildHtml(Project $project, array $rows): string
    {
        $rowsByType = collect($rows)->groupBy('tipo');
        $grandTotal = 0.0;

        $html = '
 <style>
  .head{
    font-style:bold;
    color:black;
    font-size:12px;
  }
  .section{
    background-color:#ccebe8;
    font-style:bold;
  }
  .total{
    background-color:#e8f5f3;
    font-style:bold;
  }
 </style>
 <table>
 <tr class="head">
  <th width="90" height="40">PROYECTO:</th>
  <th colspan="5">'.htmlentities(mb_strtoupper((string) $project->nombre_proyecto, 'UTF-8')).'</th>
 </tr>
 </table>
 <table cellpadding="6px">
 <thead>
   <tr bgcolor="#55827e">
   <th width="35"><font color="#fcfdfd">Nro</font></th>
   <th width="255"><font color="#fcfdfd">Insumo</font></th>
   <th width="60"><font color="#fcfdfd">Unidad</font></th>
   <th width="80" align="right"><font color="#fcfdfd">Cantidad</font></th>
   <th width="105" align="right"><font color="#fcfdfd">P. Unit. (Bs)</font></th>
   <th width="105" align="right"><font color="#fcfdfd">Parcial (Bs)</font></th>
   </tr>
 </thead>
 <tbody>';

        foreach (self::TYPE_LABELS as $type => $label) {
            $typeRows = $rowsByType->get($type, collect())->values();

            if ($typeRows->isEmpty()) {
                continue;
            }

            $html .= '
          <tr class="section">
            <td colspan="6"><b>'.htmlentities($label).'</b></td>
          </tr>';

            $typeTotal = 0.0;
            foreach ($typeRows as $index => $row) {
                $typeTotal += $row['parcial'];
                $html .= '
          <tr>
            <td width="35">'.($index + 1).'</td>
            <td width="255">'.htmlentities((string) $row['descripcion']).'</td>
            <td width="60">'.htmlentities((string) ($row['unidad'] ?? '')).'</td>
            <td width="80" align="right">'.LegacyPdfFormat::number($row['cantidad'], 4).'</td>
            <td width="105" align="right">'.LegacyPdfFormat::number($row['precio_unitario'], 2).'</td>
            <td width="105" align="right">'.LegacyPdfFormat::number($row['parcial'], 2).'</td>
          </tr>';
            }

            $grandTotal += $typeTotal;
            $html .= '
      <tr class="total">
        <td colspan="5"><b>SUBTOTAL '.$label.'</b></td>
        <td align="right"><b>'.LegacyPdfFormat::number($typeTotal, 2).'</b></td>
      </tr>';
        }

        $html .= '
      <tr bgcolor="#ccebe8">
        <td colspan="5"><b>TOTAL GENERAL</b></td>
        <td align="right"><b>'.LegacyPdfFormat::number($grandTotal, 2).'</b></td>
      </tr>
      <tr>
        <td width="100%"><b>SON:'.LegacyPdfFormat::amountLiteral($grandTotal).' BOLIVIANOS.</b></td>
      </tr>
      </tbody>
      </table>';

        return $html;
    }
}

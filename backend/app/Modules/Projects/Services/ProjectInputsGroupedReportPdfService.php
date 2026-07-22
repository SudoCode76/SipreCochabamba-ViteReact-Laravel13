<?php

namespace App\Modules\Projects\Services;

use App\Models\Project;
use App\Support\Pdf\LegacyPdfFormat;
use App\Support\Pdf\MunicipalReportPdfFactory;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProjectInputsGroupedReportPdfService
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
        $pdf = MunicipalReportPdfFactory::make('REPORTE DE PROYECTO AGRUPADO POR INSUMOS');
        MunicipalReportPdfFactory::render($pdf, function () use ($pdf, $project, $rows): void {
            $pdf->ln();

            if ($rows === []) {
                $pdf->writeHTML('<div><h1>No existen registros!</h1></div>', true, false, true, false, '');
            } else {
                $pdf->SetFont('dejavusans', '', 7, '', true);
                $pdf->ln();
                $pdf->writeHTML($this->buildHtml($project, $rows), true, false, true, false, '');
            }
        }, $signatureLayout);

        return MunicipalReportPdfFactory::inlineResponse($pdf, 'proyecto_agrupado_por_insumos.pdf');
    }

    public function rows(Project $project): array
    {
        return $this->queryRows($project)
            ->map(function ($row): array {
                $projectItemQuantity = round((float) $row->cantidad_item_proyecto, 4);
                $inputQuantityPerItem = round((float) $row->cantidad_insumo_item, 4);
                $totalQuantity = round($projectItemQuantity * $inputQuantityPerItem, 4);
                $unitPrice = round((float) $row->precio_unitario, 2);

                return [
                    'id_insumo' => (int) $row->id_insumo,
                    'tipo' => (int) $row->tipo,
                    'tipo_nombre' => self::TYPE_LABELS[(int) $row->tipo] ?? 'OTROS',
                    'insumo' => $row->insumo,
                    'unidad' => $row->unidad,
                    'precio_unitario' => $unitPrice,
                    'id_item' => (int) $row->id_item,
                    'prioridad' => (int) $row->prioridad,
                    'item' => $row->item,
                    'cantidad_item' => $projectItemQuantity,
                    'cantidad_insumo_item' => $inputQuantityPerItem,
                    'cantidad_total' => $totalQuantity,
                    'parcial' => round($totalQuantity * $unitPrice, 2),
                ];
            })
            ->all();
    }

    private function queryRows(Project $project): Collection
    {
        $this->snapshotService->ensureForProject($project);

        return DB::table('proyecto_item_insumo_snapshot')
            ->join('proyecto_item', 'proyecto_item.id_proyecto_item', '=', 'proyecto_item_insumo_snapshot.id_proyecto_item')
            ->join('item', 'item.id_item', '=', 'proyecto_item.id_item')
            ->where('proyecto_item_insumo_snapshot.estado', '<>', 'EX')
            ->where('proyecto_item.estado', 'AC')
            ->where('proyecto_item.id_proyecto', $project->id_proyecto)
            ->whereIn('proyecto_item_insumo_snapshot.tipo', [1, 2, 3])
            ->orderBy('proyecto_item_insumo_snapshot.tipo')
            ->orderBy('proyecto_item_insumo_snapshot.descripcion')
            ->orderBy('proyecto_item.prioridad')
            ->select([
                'proyecto_item_insumo_snapshot.id_insumo',
                'proyecto_item_insumo_snapshot.descripcion as insumo',
                'proyecto_item_insumo_snapshot.tipo',
                'proyecto_item_insumo_snapshot.precio_unitario',
                'proyecto_item_insumo_snapshot.unidad',
                'item.id_item',
                'item.item',
                'proyecto_item.prioridad',
                'proyecto_item.cantidad as cantidad_item_proyecto',
                'proyecto_item_insumo_snapshot.cantidad as cantidad_insumo_item',
            ])
            ->get();
    }

    private function buildHtml(Project $project, array $rows): string
    {
        $grouped = collect($rows)->groupBy('id_insumo');
        $grandTotal = 0.0;

        $html = '
 <style>
  .head{font-style:bold;color:black;font-size:12px;}
  .input{background-color:#ccebe8;font-style:bold;}
  .total{background-color:#e8f5f3;font-style:bold;}
 </style>
 <table>
 <tr class="head">
  <th width="90" height="40">PROYECTO:</th>
  <th colspan="7">'.htmlentities(mb_strtoupper((string) $project->nombre_proyecto, 'UTF-8')).'</th>
 </tr>
 </table>
 <table cellpadding="5px" width="610">
 <thead>
   <tr bgcolor="#55827e">
   <th width="35"><font color="#fcfdfd">Nro</font></th>
   <th width="45"><font color="#fcfdfd">Pr.</font></th>
   <th width="205"><font color="#fcfdfd">Item</font></th>
   <th width="55" align="right"><font color="#fcfdfd">Cant. Item</font></th>
   <th width="55" align="right"><font color="#fcfdfd">Cant. Ins.</font></th>
   <th width="60" align="right"><font color="#fcfdfd">Cant. Total</font></th>
   <th width="75" align="right"><font color="#fcfdfd">Unit.(Bs)</font></th>
   <th width="80" align="right"><font color="#fcfdfd">Parcial(Bs)</font></th>
   </tr>
 </thead>
 <tbody>';

        foreach ($grouped as $inputRows) {
            $first = $inputRows->first();
            $inputQuantity = (float) $inputRows->sum('cantidad_total');
            $inputTotal = (float) $inputRows->sum('parcial');
            $grandTotal += $inputTotal;

            $html .= '
          <tr class="input">
            <td width="610" colspan="8"><b>'.htmlentities((string) $first['tipo_nombre']).' - '.htmlentities((string) $first['insumo']).' | Unidad: '.htmlentities((string) ($first['unidad'] ?? '')).' | Cantidad total: '.LegacyPdfFormat::number($inputQuantity, 4).'</b></td>
          </tr>';

            foreach ($inputRows->values() as $index => $row) {
                $html .= '
          <tr>
            <td width="35">'.($index + 1).'</td>
            <td width="45">'.$row['prioridad'].'</td>
            <td width="205">'.htmlentities((string) $row['item']).'</td>
            <td width="55" align="right">'.LegacyPdfFormat::number($row['cantidad_item'], 4).'</td>
            <td width="55" align="right">'.LegacyPdfFormat::number($row['cantidad_insumo_item'], 4).'</td>
            <td width="60" align="right">'.LegacyPdfFormat::number($row['cantidad_total'], 4).'</td>
            <td width="75" align="right">'.LegacyPdfFormat::number($row['precio_unitario'], 2).'</td>
            <td width="80" align="right">'.LegacyPdfFormat::number($row['parcial'], 2).'</td>
          </tr>';
            }

            $html .= '
      <tr class="total">
        <td width="530" colspan="7"><b>SUBTOTAL INSUMO</b></td>
        <td width="80" align="right"><b>'.LegacyPdfFormat::number($inputTotal, 2).'</b></td>
      </tr>';
        }

        $html .= '
      <tr bgcolor="#ccebe8">
        <td width="530" colspan="7"><b>TOTAL GENERAL</b></td>
        <td width="80" align="right"><b>'.LegacyPdfFormat::number($grandTotal, 2).'</b></td>
      </tr>
      <tr>
        <td width="610" colspan="8"><b>SON:'.LegacyPdfFormat::amountLiteral($grandTotal).' BOLIVIANOS.</b></td>
      </tr>
      </tbody>
      </table>';

        return $html;
    }
}

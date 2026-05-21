<?php

namespace App\Modules\Items\Services;

use App\Models\InputLog;
use App\Models\Item;
use App\Models\ItemInput;
use App\Support\Pdf\LegacyPdfFormat;
use App\Support\Pdf\MunicipalReportPdfFactory;
use Carbon\CarbonInterface;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class HistoricalBreakdownPdfService
{
    public function stream(Item $item, int|string $type, CarbonInterface $date): Response
    {
        $typeId = $this->normalizeType($type);
        $item->loadMissing(['unitMeasure']);

        $pdf = MunicipalReportPdfFactory::make($this->titleForType($typeId));
        $pdf->ln();

        if ($item->id_item === null || strtoupper((string) $item->estado) !== 'AC') {
            $pdf->writeHTML('<div><h1 style="color:red" align="center">El item esta inactivo.....!</h1></div>', true, false, true, false, '');
            $pdf->SetFont('dejavusans', '', 10, '', true);
        } else {
            $components = $this->historicalComponents($item, $typeId, $date);

            if ($components->isEmpty()) {
                $pdf->writeHTML('<div><h1>El item esta incompleto por favor revisar la existencia insumos.</h1></div>', true, false, true, false, '');
                $pdf->SetFont('dejavusans', '', 10, '', true);
            } else {
                $pdf->SetFont('dejavusans', '', 8, '', true);
                $pdf->ln();
                $pdf->writeHTML($this->buildHtml($item, $components), true, false, true, false, '');
                $pdf->SetFont('dejavusans', '', 10, '', true);
            }
        }

        return MunicipalReportPdfFactory::inlineResponse($pdf, 'desgloce_recalculado.pdf');
    }

    public function historicalComponents(Item $item, int $type, CarbonInterface $date): Collection
    {
        $latestLogs = InputLog::query()
            ->select('id_insumo', DB::raw('MAX(id_log) as latest_log_id'))
            ->whereDate('fecha', '<=', $date->toDateString())
            ->where('tipo', $type)
            ->groupBy('id_insumo');

        return ItemInput::query()
            ->from('item_insumo')
            ->joinSub($latestLogs, 'latest_logs', function ($join): void {
                $join->on('latest_logs.id_insumo', '=', 'item_insumo.id_insumo');
            })
            ->join('item', 'item.id_item', '=', 'item_insumo.id_item')
            ->join('grupo', 'grupo.id_grupo', '=', 'item.grupo')
            ->join('sub_grupo', 'sub_grupo.id_subgrupo', '=', 'item.subgrupo')
            ->join('log_insumo', 'log_insumo.id_log', '=', 'latest_logs.latest_log_id')
            ->join('unidad_medida', 'unidad_medida.id_unidad_medida', '=', 'log_insumo.unidad_medida')
            ->where('item_insumo.id_item', $item->id_item)
            ->where('item_insumo.estado', 'AC')
            ->select([
                'item_insumo.id_item_insumo',
                'item_insumo.id_insumo',
                'item_insumo.id_item',
                'item_insumo.cantidad',
                'grupo.nombre_grupo',
                'sub_grupo.descripcion as subgrupo',
                'unidad_medida.descripcion as medida',
                'unidad_medida.abreviatura as unidad_abreviatura',
                'item.item',
                'item.id_unidad as unidad_item',
                'log_insumo.tipo as tipo_insumo',
                'log_insumo.precio as precio_insumo',
                'log_insumo.descripcion',
                'log_insumo.id_log',
            ])
            ->orderByDesc('item_insumo.id_insumo')
            ->orderByDesc('log_insumo.id_log')
            ->get();
    }

    public function normalizeType(int|string $type): int
    {
        $normalized = strtolower(trim((string) $type));

        return match ($normalized) {
            '1', 'material', 'materiales' => 1,
            '2', 'mano_obra', 'mano-de-obra', 'labor' => 2,
            '3', 'maquinaria', 'herramientas', 'machinery' => 3,
            default => throw new InvalidArgumentException('Tipo de desglose no soportado.'),
        };
    }

    private function titleForType(int $type): string
    {
        return match ($type) {
            1 => 'Desglose de insumos general: Material',
            2 => 'Desglose de insumos general: Mano de Obra',
            3 => 'Desglose de insumos general: Maquinaria y Herramientas',
            default => throw new InvalidArgumentException('Tipo de desglose no soportado.'),
        };
    }

    private function buildHtml(Item $item, Collection $components): string
    {
        $itemName = htmlentities(mb_strtoupper((string) $item->item, 'UTF-8'));
        $unit = htmlentities((string) ($item->unitMeasure?->descripcion ?? ''));
        $total = 0.0;

        $html = '
 <style>
  .seccion{
    background-color: #0D9C8A;
     font-size:12px;
     font-style:bold;
  }
  .subseccion{
    background-color: #C8EFE6;
     font-size:10px;
     font-style:bold;
    }
  .head{
    font-style:bold;
    color:black;
    font-size:12px;
  }
  .tab{
    background-color: #fff;
  }
 </style>
 <table>
 <tr class="head">
  <th width="35" height="40">ITEM:</th>
  <th colspan="3"> '.$itemName.'</th>
  <th align="right">UNIDAD:</th>
  <th> '.$unit.' </th>
 </tr>
 </table>

 <table cellpadding="6px">
 <thead>
   <tr bgcolor="#55827e">
   <th width="40"><font color="#fcfdfd">Nº P</font></th>
   <th width="280"><font color="#fcfdfd">Insumo/Parametro</font></th>
   <th width="60"><font color="#fcfdfd">Unid.</font></th>
   <th width="60" align="right"><font color="#fcfdfd">Cant.</font></th>
   <th width="110" align="right"><font color="#fcfdfd">Unit.(Bs)</font></th>
   <th width="110" align="right"><font color="#fcfdfd">Parcial(Bs)</font></th>
   </tr>
 </thead>
 <tbody>';

        foreach ($components as $index => $component) {
            $quantity = (float) $component->cantidad;
            $unitPrice = (float) $component->precio_insumo;
            $partial = $quantity * $unitPrice;
            $total += $partial;

            $html .= '
          <tr>
            <td width="40">'.($index + 1).'</td>
            <td width="280">'.htmlentities((string) $component->descripcion).'</td>
            <td width="60">'.htmlentities((string) ($component->unidad_abreviatura ?? $component->medida ?? '')).'</td>
            <td width="60" align="right">'.LegacyPdfFormat::number($quantity, 4).'</td>
            <td width="110" align="right">'.LegacyPdfFormat::number($unitPrice, 2).'</td>
            <td width="110" align="right">'.LegacyPdfFormat::number($partial, 2).'</td>
          </tr>';
        }

        $html .= '
      <tr bgcolor="#C8EFE6">
        <td colspan="5"><b>TOTAL</b></td>
        <td><b>'.LegacyPdfFormat::number($total, 2).'</b></td>
      </tr>
      <tr>
      <td width="100%"><b>SON: BOLIVIANOS  '.LegacyPdfFormat::amountLiteral($total).' </b></td>
      </tr>
      </tbody>
      </table>';

        return $html;
    }
}

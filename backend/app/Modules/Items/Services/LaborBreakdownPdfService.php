<?php

namespace App\Modules\Items\Services;

use App\Models\Item;
use App\Support\Pdf\LegacyPdfFormat;
use App\Support\Pdf\MunicipalReportPdfFactory;
use Illuminate\Http\Response;

class LaborBreakdownPdfService
{
    public function __construct(
        private readonly ItemCompositionService $itemCompositionService,
    ) {}

    public function stream(Item $item): Response
    {
        $item->loadMissing(['unitMeasure']);
        $laborItems = $this->itemCompositionService->listByType($item, 2);

        $pdf = MunicipalReportPdfFactory::make('Desglose de insumos general:MANO DE OBRA');
        $pdf->ln();

        if ($item->id_item === null || strtoupper((string) $item->estado) !== 'AC') {
            $pdf->writeHTML('<div><h1 style="color:red" align="center">El item esta inactivo.....!</h1></div>', true, false, true, false, '');
            $pdf->SetFont('dejavusans', '', 10, '', true);
        } elseif ($laborItems === []) {
            $pdf->writeHTML('<div><h1>El item esta incompleto por favor  revisar la existencia de mano de obra</h1></div>', true, false, true, false, '');
            $pdf->SetFont('dejavusans', '', 10, '', true);
        } else {
            $pdf->SetFont('dejavusans', '', 8, '', true);
            $pdf->ln();
            $pdf->writeHTML($this->buildHtml($item, $laborItems), true, false, true, false, '');
            $pdf->SetFont('dejavusans', '', 10, '', true);
        }

        return MunicipalReportPdfFactory::inlineResponse($pdf, 'desglose_mano_obra.pdf');
    }

    private function buildHtml(Item $item, array $laborItems): string
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

        foreach ($laborItems as $index => $laborItem) {
            $quantity = (float) ($laborItem['cantidad'] ?? 0);
            $unitPrice = (float) ($laborItem['precio_unitario'] ?? 0);
            $partial = $quantity * $unitPrice;
            $total += $partial;

            $html .= '
          <tr>
            <td width="40">'.($index + 1).'</td>
            <td width="280">'.htmlentities((string) ($laborItem['descripcion'] ?? '')).'</td>
            <td width="60">'.htmlentities((string) ($laborItem['unidad'] ?? '')).'</td>
            <td width="60" align="right">'.LegacyPdfFormat::number($quantity, 4).'</td>
            <td width="110" align="right">'.LegacyPdfFormat::number($unitPrice, 2).'</td>
            <td width="110" align="right">'.LegacyPdfFormat::number($partial, 2).'</td>
          </tr>';
        }

        $html .= '
      <tr bgcolor="#ccebe8">
        <td colspan="5"><b>TOTAL (Bs.)</b></td>
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

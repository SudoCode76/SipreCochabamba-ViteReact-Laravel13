<?php

namespace App\Services\Items;

use App\Models\Item;
use App\Support\Pdf\LegacyPdfFormat;
use App\Support\Pdf\MunicipalReportPdfFactory;
use Illuminate\Http\Response;

class LegacyUnitPriceAnalysisPdfService
{
    public function __construct(
        private readonly LegacyUnitPriceAnalysisService $legacyUnitPriceAnalysisService,
    ) {}

    public function stream(Item $item): Response
    {
        $document = $this->legacyUnitPriceAnalysisService->build($item);
        $analysis = $document['raw'];

        $pdf = MunicipalReportPdfFactory::make('Análisis de Precios Unitarios');
        $pdf->SetFont('dejavusans', '', 8, '', true);

        [$html, $missingParametersHtml] = $this->buildLegacyHtml($analysis);

        if ($missingParametersHtml !== null) {
            $pdf->writeHTML($missingParametersHtml, true, false, true, false, '');
            $pdf->SetFont('dejavusans', '', 10, '', true);
        } else {
            $pdf->writeHTML($html, true, false, true, false, '');
            $pdf->SetFont('dejavusans', '', 10, '', true);
        }

        return MunicipalReportPdfFactory::inlineResponse($pdf, 'analisis_de_precios_unitarios.pdf');
    }

    private function buildLegacyHtml(array $analysis): array
    {
        $item = htmlentities(mb_strtoupper((string) ($analysis['item']['name'] ?? ''), 'UTF-8'));
        $unit = htmlentities((string) ($analysis['item']['unit_measure']['description'] ?? ''));
        $materials = $analysis['materials'] ?? [];
        $labor = $analysis['labor'] ?? [];
        $tools = $analysis['tools'] ?? [];
        $percentages = $analysis['percentages'] ?? [];

        $acumA = 0.0;
        $acumB = 0.0;
        $acumC = 0.0;

        $wNo = '6.06%';
        $wDescription = '42.42%';
        $wUnit = '9.09%';
        $wQuantity = '9.09%';
        $wUnitPrice = '16.67%';
        $wPartial = '16.67%';
        $wSection = '5.30%';
        $wSectionContent = '94.70%';
        $wSubtotalLabel = '61.06%';
        $wMergedDescription = '51.91%';
        $wPercent = '9.16%';
        $wAdoptedLabel = '83.33%';
        $wItemName = '61.36%';

        $html = '';
        $html .= '
 <style>
  .seccion{
    background-color: #0D9C8A;
     font-size:12px;
     font-style:bold;
     color:#ffffff;
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
 </style>';

        $html .= '<table width="100%">
 <thead>
 <tr class="head">
  <th width="'.$wSection.'" height="20">ITEM:</th>
  <th width="'.$wItemName.'"> '.$item.'</th>
  <th width="'.$wUnitPrice.'" align="right">UNIDAD:</th>
  <th width="'.$wPartial.'"> '.$unit.' </th>
 </tr>
 </thead>
 </table>

 <table width="100%" cellpadding="6px">
 <thead>
     <tr bgcolor="#55827e">
        <th width="'.$wNo.'"><font color="#fcfdfd">Nº P</font></th>
        <th width="'.$wDescription.'"><font color="#fcfdfd">Insumo/Parametro</font></th>
        <th width="'.$wUnit.'"><font color="#fcfdfd">Unid.</font></th>
        <th width="'.$wQuantity.'" align="right"><font color="#fcfdfd">Cant.</font></th>
        <th width="'.$wUnitPrice.'" align="right"><font color="#fcfdfd">Unit.(Bs)</font></th>
        <th width="'.$wPartial.'" align="right"><font color="#fcfdfd">Parcial(Bs)</font></th>
     </tr>
 </thead>
 <tbody>
   <tr bgcolor="#ccebe8">
   <th width="'.$wSection.'"><b>A</b></th>
   <th width="'.$wSectionContent.'"><b>MATERIALES</b></th>
    </tr>';

        $numero = 1;
        foreach ($materials as $material) {
            $parcialRaw = (float) $material['quantity'] * (float) $material['unit_price'];
            $acumA += $parcialRaw;
            $html .= '<tr>
        <td width="'.$wNo.'">'.$numero.'</td>
        <td width="'.$wDescription.'">'.htmlentities((string) $material['description']).'</td>
        <td width="'.$wUnit.'">'.($material['unit_measure']['abbreviation'] ?? '').'</td>
        <td width="'.$wQuantity.'" align="right">'.LegacyPdfFormat::number((float) $material['quantity'], 4).'</td>
        <td width="'.$wUnitPrice.'" align="right">'.LegacyPdfFormat::number((float) $material['unit_price'], 2).'</td>
        <td width="'.$wPartial.'" align="right">'.LegacyPdfFormat::number($parcialRaw, 2).'</td>
      </tr>';
            $numero++;
        }

        $a = LegacyPdfFormat::number($acumA, 2);
        $html .= '<tr bgcolor="#ccebe8">
      <td width="'.$wSection.'"><b>D</b></td>
        <td width="'.$wSubtotalLabel.'"><b>TOTAL MATERIALES</b></td>
        <td width="'.$wUnitPrice.'"><b> (A)=</b></td>
        <td width="'.$wPartial.'" align="right"><b>'.$a.'</b></td>
      </tr>
      <tr>
      <td width="'.$wSection.'" align="right">B</td>
      <td width="'.$wSectionContent.'">MANO DE OBRA</td>
      </tr>';

        $numero = 1;
        foreach ($labor as $laborRow) {
            $parcialRaw = (float) $laborRow['quantity'] * (float) $laborRow['unit_price'];
            $acumB += $parcialRaw;
            $html .= '<tr>
        <td width="'.$wNo.'">'.$numero.'</td>
        <td width="'.$wDescription.'">'.htmlentities((string) $laborRow['description']).'</td>
        <td width="'.$wUnit.'">'.($laborRow['unit_measure']['abbreviation'] ?? '').'</td>
        <td width="'.$wQuantity.'" align="right">'.LegacyPdfFormat::number((float) $laborRow['quantity'], 4).'</td>
        <td width="'.$wUnitPrice.'" align="right">'.LegacyPdfFormat::number((float) $laborRow['unit_price'], 2).'</td>
        <td width="'.$wPartial.'" align="right">'.LegacyPdfFormat::number($parcialRaw, 2).'</td>
        </tr>';
            $numero++;
        }

        $b = LegacyPdfFormat::number($acumB, 2);
        $html .= '<tr bgcolor="#ccebe8">
        <td width="'.$wSection.'"><b>E</b></td>
        <td width="'.$wSubtotalLabel.'"><b>SUBTOTAL MANO DE OBRA</b></td>
        <td width="'.$wUnitPrice.'"><b> (B)=</b></td>
        <td width="'.$wPartial.'" align="right"><b>'.$b.'</b></td>
    </tr>';

        $resolved = $this->resolveLegacyPercentages($percentages);

        if (! $resolved['complete']) {
            $html1 = '<div><h1>La configuracion de parametros de calculo esta incompleta o algun parametro escencial esta inactivo, revise su configuracion.</h1>
              <h4> Verifique la existencia y estado de los siguientes parametros en su configuracion:</h4>
              <ul>
                <li>CARGAS SOCIALES</li>
                <li>IMPUESTO AL VALOR AGREGADO</li>
                <li>HERRAMIENTAS MENORES</li>
                <li>GASTOS GRALES Y ADMINISTRATIVOS</li>
                <li>UTILIDAD</li>
                <li>IMPUESTO A LAS TRANSACCIONES</li>
              </ul>
              </div>';

            return [$html, $html1];
        }

        $montoCs = ($acumB * $resolved['porcentaje_cs']) / 100;
        $montoCsF = LegacyPdfFormat::number($montoCs, 2);
        $suma = $acumB + $montoCs;
        $o = $suma * $resolved['porcentaje_iva'] / 100;
        $oF = number_format((float) $o, 2, '.', ',');
        $g = $acumB + $montoCs + $o;
        $gF = LegacyPdfFormat::number($g, 2);

        $html .= '<tr>
              <td width="'.$wSection.'">F</td>
                <td width="'.$wMergedDescription.'">'.$resolved['descripcion_porcentaje_cs'].'</td>
                <td width="'.$wPercent.'" align="right">'.$resolved['porcentaje_cs'].'%</td>
                 <td width="'.$wUnitPrice.'">(E)=</td>
                <td width="'.$wPartial.'" align="right">'.$montoCsF.'</td>
            </tr>
           <tr>
              <td width="'.$wSection.'">O</td>
                <td width="'.$wMergedDescription.'">'.$resolved['descripcion_iva'].'</td>
                <td width="'.$wPercent.'" align="right">'.$resolved['porcentaje_iva'].'%</td>
                 <td width="'.$wUnitPrice.'">(E+F)=</td>
                <td width="'.$wPartial.'" align="right">'.$oF.'</td>
            </tr>
             <tr bgcolor="#ccebe8">
                <td width="'.$wSection.'"><b>G</b></td>
                <td width="'.$wSubtotalLabel.'"><b>TOTAL MANO DE OBRA</b></td>
                <td width="'.$wUnitPrice.'"><b> (E+F+O)=</b></td>
                <td width="'.$wPartial.'" align="right"><b>'.$gF.'</b></td>
             </tr>
             <tr>
              <td width="'.$wSection.'" align="right">C</td>
              <td width="'.$wSectionContent.'">EQUIPO, MAQUINARIA Y HERRAMIENTA</td>
             </tr>';

        $numero = 1;
        foreach ($tools as $tool) {
            $parcialRaw = (float) $tool['quantity'] * (float) $tool['unit_price'];
            $acumC += $parcialRaw;
            $html .= '<tr>
                      <td width="'.$wNo.'">'.$numero.'</td>
                      <td width="'.$wDescription.'">'.htmlentities((string) $tool['description']).'</td>
                      <td width="'.$wUnit.'">'.($tool['unit_measure']['abbreviation'] ?? '').'</td>
                      <td width="'.$wQuantity.'" align="right">'.LegacyPdfFormat::number((float) $tool['quantity'], 4).'</td>
                      <td width="'.$wUnitPrice.'" align="right">'.LegacyPdfFormat::number((float) $tool['unit_price'], 2).'</td>
                      <td width="'.$wPartial.'" align="right">'.LegacyPdfFormat::number($parcialRaw, 2).'</td>
                      </tr>';
            $numero++;
        }

        $h = $g * $resolved['porcentaje_hm'] / 100;
        $hF = LegacyPdfFormat::number($h, 2);
        $i = $acumC + $h;
        $iF = LegacyPdfFormat::number($i, 2);
        $j = $acumA + $g + $i;
        $jF = LegacyPdfFormat::number($j, 2);
        $l = $j * $resolved['porcentaje_adm'] / 100;
        $lF = LegacyPdfFormat::number($l, 2);
        $m = ($j + $l) * $resolved['porcentaje_util'] / 100;
        $mF = LegacyPdfFormat::number($m, 2);
        $n = (float) $j + (float) $l + (float) $m;
        $nF = number_format((float) $n, 2, '.', ',');
        $p = $n * $resolved['porcentaje_it'] / 100;
        $pF = LegacyPdfFormat::number($p, 2);
        $q = (float) $n + (float) $p;
        $qF = LegacyPdfFormat::number($q, 2);
        $pa = LegacyPdfFormat::number($q, 2);
        $lit = LegacyPdfFormat::literalFromLegacyNumber($pa);
        $txt = 'SON: BOLIVIANOS  '.$lit.' ';

        $html .= '<tr>
              <td width="'.$wSection.'">H</td>
                <td width="'.$wMergedDescription.'">'.$resolved['descripcion_hm'].'</td>
                <td width="'.$wPercent.'" align="right">'.$resolved['porcentaje_hm'].'%</td>
                 <td width="'.$wUnitPrice.'">(G)=</td>
                <td width="'.$wPartial.'" align="right">'.$hF.'</td>
            </tr>
            <tr bgcolor="#ccebe8">
                <td width="'.$wSection.'"><b>I</b></td>
                <td width="'.$wSubtotalLabel.'"><b>TOTAL HERRAMIENTAS Y EQUIPO</b></td>
                <td width="'.$wUnitPrice.'"><b> (C+H)=</b></td>
                <td width="'.$wPartial.'" align="right"><b>'.$iF.'</b></td>
           </tr>
            <tr bgcolor="#ccebe8">
                <td width="'.$wSection.'"><b>J</b></td>
                <td width="'.$wSubtotalLabel.'"><b>SUBTOTAL</b></td>
                <td width="'.$wUnitPrice.'"><b> (D+G+I)=</b></td>
                <td width="'.$wPartial.'" align="right"><b>'.$jF.'</b></td>
           </tr>
            <tr>
              <td width="'.$wSection.'">L</td>
                <td width="'.$wMergedDescription.'">'.$resolved['descripcion_adm'].'</td>
                <td width="'.$wPercent.'" align="right">'.$resolved['porcentaje_adm'].'%</td>
                 <td width="'.$wUnitPrice.'">(E)=</td>
                <td width="'.$wPartial.'" align="right">'.$lF.'</td>
            </tr>
             <tr>
              <td width="'.$wSection.'">M</td>
                <td width="'.$wMergedDescription.'">'.$resolved['descripcion_util'].'</td>
                <td width="'.$wPercent.'" align="right">'.$resolved['porcentaje_util'].'%</td>
                 <td width="'.$wUnitPrice.'">(J+L)=</td>
                <td width="'.$wPartial.'" align="right">'.$mF.'</td>
            </tr>
            <tr bgcolor="#ccebe8">
                <td width="'.$wSection.'"><b>N</b></td>
                <td width="'.$wSubtotalLabel.'"><b>PARCIAL</b></td>
                <td width="'.$wUnitPrice.'"><b> (J+L+M)=</b></td>
                <td width="'.$wPartial.'" align="right"><b>'.$nF.'</b></td>
           </tr>
           <tr>
              <td width="'.$wSection.'">M</td>
                <td width="'.$wMergedDescription.'">'.$resolved['descripcion_it'].'</td>
                <td width="'.$wPercent.'" align="right">'.$resolved['porcentaje_it'].'%</td>
                 <td width="'.$wUnitPrice.'">(N)=</td>
                <td width="'.$wPartial.'" align="right">'.$pF.'</td>
            </tr>
            <tr bgcolor="#ccebe8" nobr="true">
                <td width="'.$wSection.'"><b>Q</b></td>
                <td width="'.$wSubtotalLabel.'"><b>TOTAL PRECIO UNITARIO</b></td>
                <td width="'.$wUnitPrice.'"><b> ((N+P)=</b></td>
                <td width="'.$wPartial.'" align="right"><b>'.$qF.'</b></td>
           </tr>
            <tr bgcolor="#ccebe8" nobr="true">
                <td width="'.$wAdoptedLabel.'"><b>PRECIO ADOPTADO</b></td>
                <td width="'.$wPartial.'" align="right"><b>'.$pa.'</b></td>
           </tr>
           <tr><td width="'.$wAdoptedLabel.'"><b>'.$txt.'</b></td></tr>
           </tbody></table><br/>';

        return [$html, null];
    }

    private function resolveLegacyPercentages(array $percentages): array
    {
        $resolved = ['complete' => false];

        foreach ($percentages as $percentage) {
            $description = mb_strtoupper((string) ($percentage['description'] ?? ''), 'UTF-8');
            $value = (float) ($percentage['percentage'] ?? 0);

            if (preg_match('/CARGAS SOCIALES/i', $description)) {
                $resolved['descripcion_porcentaje_cs'] = $description;
                $resolved['porcentaje_cs'] = $value;
            }
            if (preg_match('/IMPUESTO AL VALOR AGREGADO/i', $description)) {
                $resolved['descripcion_iva'] = $description;
                $resolved['porcentaje_iva'] = $value;
            }
            if (preg_match('/HERRAMIENTAS MENORES/i', $description)) {
                $resolved['descripcion_hm'] = $description;
                $resolved['porcentaje_hm'] = $value;
            }
            if (preg_match('/GASTOS GRALES Y ADMINISTRATIVOS/i', $description)) {
                $resolved['descripcion_adm'] = $description;
                $resolved['porcentaje_adm'] = $value;
            }
            if (preg_match('/UTILIDAD/i', $description)) {
                $resolved['descripcion_util'] = $description;
                $resolved['porcentaje_util'] = $value;
            }
            if (preg_match('/IMPUESTO A LAS TRANSACCIONES/i', $description)) {
                $resolved['descripcion_it'] = $description;
                $resolved['porcentaje_it'] = $value;
            }
        }

        $resolved['complete'] = isset(
            $resolved['descripcion_porcentaje_cs'],
            $resolved['porcentaje_iva'],
            $resolved['porcentaje_hm'],
            $resolved['porcentaje_adm'],
            $resolved['porcentaje_util'],
            $resolved['porcentaje_it'],
        );

        return $resolved;
    }
}

<?php

namespace App\Services\Items;

use App\Models\Item;
use App\Support\Pdf\LegacyUnitPriceAnalysisPdf;
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

        $pdf = new LegacyUnitPriceAnalysisPdf(
            'L',
            defined('PDF_UNIT') ? PDF_UNIT : 'mm',
            defined('PDF_PAGE_FORMAT') ? PDF_PAGE_FORMAT : 'A4',
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
            defined('PDF_MARGIN_TOP') ? PDF_MARGIN_TOP : 27,
            defined('PDF_MARGIN_RIGHT') ? PDF_MARGIN_RIGHT : 15,
        );
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
        $pdf->AddPage('L', 'A4');
        $pdf->SetDisplayMode('real');
        $pdf->SetAutoPageBreak(true, 10);
        $pdf->SetFont('dejavusans', '', 7, '', true);
        $pdf->SetFont('dejavusans', '', 8, '', true);

        [$html, $missingParametersHtml] = $this->buildLegacyHtml($analysis);

        if ($missingParametersHtml !== null) {
            $pdf->writeHTML($missingParametersHtml, true, false, true, false, '');
            $pdf->SetFont('dejavusans', '', 10, '', true);
        } else {
            $pdf->writeHTML($html, true, false, true, false, '');
            $pdf->SetFont('dejavusans', '', 10, '', true);
        }

        $content = $pdf->Output('analisis_de_precios_unitarios.pdf', 'S');

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="analisis_de_precios_unitarios.pdf"',
        ]);
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

        $html .= '<table>
 <thead>
 <tr class="head">
  <th width="35" height="20">ITEM:</th>
  <th colspan="3"> '.$item.'</th>
  <th align="right">UNIDAD:</th>
  <th> '.$unit.' </th>
 </tr>
 </thead>
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
 <tbody>
   <tr bgcolor="#ccebe8">
   <th width="35"><b>A</b></th>
   <th colspan="5" width="98%"><b>MATERIALES</b></th>
    </tr>';

        $numero = 1;
        foreach ($materials as $material) {
            $parcialRaw = (float) $material['quantity'] * (float) $material['unit_price'];
            $acumA += $parcialRaw;
            $html .= '<tr>
        <td width="40">'.$numero.'</td>
        <td width="280">'.htmlentities((string) $material['description']).'</td>
        <td width="60">'.($material['unit_measure']['abbreviation'] ?? '').'</td>
        <td width="60" align="right">'.$this->legacyNumber((float) $material['quantity'], 4).'</td>
        <td width="110" align="right">'.$this->legacyNumber((float) $material['unit_price'], 2).'</td>
        <td width="110" align="right">'.$this->legacyNumber($parcialRaw, 2).'</td>
      </tr>';
            $numero++;
        }

        $a = $this->legacyNumber($acumA, 2);
        $html .= '<tr bgcolor="#ccebe8">
      <td width="35"><b>D</b></td>
        <td colspan="3"><b>TOTAL MATERIALES</b></td>
        <td><b> (A)=</b></td>
        <td align="right"><b>'.$a.'</b></td>
      </tr>
      <tr>
      <td width="35" align="right">B</td>
      <td colspan="5">MANO DE OBRA</td>
      </tr>';

        $numero = 1;
        foreach ($labor as $laborRow) {
            $parcialRaw = (float) $laborRow['quantity'] * (float) $laborRow['unit_price'];
            $acumB += $parcialRaw;
            $html .= '<tr>
        <td width="40">'.$numero.'</td>
        <td width="280">'.htmlentities((string) $laborRow['description']).'</td>
        <td width="60">'.($laborRow['unit_measure']['abbreviation'] ?? '').'</td>
        <td width="60" align="right">'.$this->legacyNumber((float) $laborRow['quantity'], 4).'</td>
        <td width="110" align="right">'.$this->legacyNumber((float) $laborRow['unit_price'], 2).'</td>
        <td width="110" align="right">'.$this->legacyNumber($parcialRaw, 2).'</td>
        </tr>';
            $numero++;
        }

        $b = $this->legacyNumber($acumB, 2);
        $html .= '<tr bgcolor="#ccebe8">
        <td width="35"><b>E</b></td>
        <td colspan="3"><b>SUBTOTAL MANO DE OBRA</b></td>
        <td><b> (B)=</b></td>
        <td align="right"><b>'.$b.'</b></td>
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
        $montoCsF = $this->legacyNumber($montoCs, 2);
        $suma = $acumB + $montoCs;
        $o = $suma * $resolved['porcentaje_iva'] / 100;
        $oF = number_format((float) $o, 2, '.', ',');
        $g = $acumB + $montoCs + $o;
        $gF = $this->legacyNumber($g, 2);

        $html .= '<tr>
              <td width="35">F</td>
                <td colspan="2">'.$resolved['descripcion_porcentaje_cs'].'</td>
                <td align="right">'.$resolved['porcentaje_cs'].'%</td>
                 <td>(E)=</td>
                <td align="right">'.$montoCsF.'</td>
            </tr>
           <tr>
              <td width="35">O</td>
                <td colspan="2">'.$resolved['descripcion_iva'].'</td>
                <td align="right">'.$resolved['porcentaje_iva'].'%</td>
                 <td>(E+F)=</td>
                <td align="right">'.$oF.'</td>
            </tr>
             <tr bgcolor="#ccebe8">
                <td width="35"><b>G</b></td>
                <td colspan="3"><b>TOTAL MANO DE OBRA</b></td>
                <td><b> (E+F+O)=</b></td>
                <td align="right"><b>'.$gF.'</b></td>
             </tr>
             <tr>
              <td width="35" align="right">C</td>
              <td colspan="5">EQUIPO, MAQUINARIA Y HERRAMIENTA</td>
             </tr>';

        $numero = 1;
        foreach ($tools as $tool) {
            $parcialRaw = (float) $tool['quantity'] * (float) $tool['unit_price'];
            $acumC += $parcialRaw;
            $html .= '<tr>
                      <td width="40">'.$numero.'</td>
                      <td width="280">'.htmlentities((string) $tool['description']).'</td>
                      <td width="60">'.($tool['unit_measure']['abbreviation'] ?? '').'</td>
                      <td width="60" align="right">'.$this->legacyNumber((float) $tool['quantity'], 4).'</td>
                      <td width="110" align="right">'.$this->legacyNumber((float) $tool['unit_price'], 2).'</td>
                      <td width="110" align="right">'.$this->legacyNumber($parcialRaw, 2).'</td>
                      </tr>';
            $numero++;
        }

        $h = $g * $resolved['porcentaje_hm'] / 100;
        $hF = $this->legacyNumber($h, 2);
        $i = $acumC + $h;
        $iF = $this->legacyNumber($i, 2);
        $j = $acumA + $g + $i;
        $jF = $this->legacyNumber($j, 2);
        $l = $j * $resolved['porcentaje_adm'] / 100;
        $lF = $this->legacyNumber($l, 2);
        $m = ($j + $l) * $resolved['porcentaje_util'] / 100;
        $mF = $this->legacyNumber($m, 2);
        $n = (float) $j + (float) $l + (float) $m;
        $nF = number_format((float) $n, 2, '.', ',');
        $p = $n * $resolved['porcentaje_it'] / 100;
        $pF = $this->legacyNumber($p, 2);
        $q = (float) $n + (float) $p;
        $qF = $this->legacyNumber($q, 2);
        $pa = $this->legacyNumber($q, 2);
        $lit = $this->convertir($pa);
        $txt = 'SON: BOLIVIANOS  '.$lit.' ';

        $html .= '<tr>
              <td width="35">H</td>
                <td colspan="2">'.$resolved['descripcion_hm'].'</td>
                <td align="right">'.$resolved['porcentaje_hm'].'%</td>
                 <td>(G)=</td>
                <td align="right">'.$hF.'</td>
            </tr>
            <tr bgcolor="#ccebe8">
                <td width="35"><b>I</b></td>
                <td colspan="3"><b>TOTAL HERRAMIENTAS Y EQUIPO</b></td>
                <td><b> (C+H)=</b></td>
                <td align="right"><b>'.$iF.'</b></td>
           </tr>
            <tr bgcolor="#ccebe8">
                <td width="35"><b>J</b></td>
                <td colspan="3"><b>SUBTOTAL</b></td>
                <td><b> (D+G+I)=</b></td>
                <td align="right"><b>'.$jF.'</b></td>
           </tr>
            <tr>
              <td width="35">L</td>
                <td colspan="2">'.$resolved['descripcion_adm'].'</td>
                <td align="right">'.$resolved['porcentaje_adm'].'%</td>
                 <td>(E)=</td>
                <td align="right">'.$lF.'</td>
            </tr>
             <tr>
              <td width="35">M</td>
                <td colspan="2">'.$resolved['descripcion_util'].'</td>
                <td align="right">'.$resolved['porcentaje_util'].'%</td>
                 <td>(J+L)=</td>
                <td align="right">'.$mF.'</td>
            </tr>
            <tr bgcolor="#ccebe8">
                <td width="35"><b>N</b></td>
                <td colspan="3"><b>PARCIAL</b></td>
                <td><b> (J+L+M)=</b></td>
                <td align="right"><b>'.$nF.'</b></td>
           </tr>
           <tr>
              <td width="35">M</td>
                <td colspan="2">'.$resolved['descripcion_it'].'</td>
                <td align="right">'.$resolved['porcentaje_it'].'%</td>
                 <td>(N)=</td>
                <td align="right">'.$pF.'</td>
            </tr>
            <tr bgcolor="#ccebe8" nobr="true">
                <td width="35"><b>Q</b></td>
                <td colspan="3"><b>TOTAL PRECIO UNITARIO</b></td>
                <td><b> ((N+P)=</b></td>
                <td align="right"><b>'.$qF.'</b></td>
           </tr>
             <tr bgcolor="#ccebe8" nobr="true">
                <td colspan="5"><b>PRECIO ADOPTADO</b></td>
                <td align="right"><b>'.$pa.'</b></td>
           </tr>
           <tr><td colspan="5"><b>'.$txt.'</b></td></tr>
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

    private function legacyNumber(float $value, int $decimals): string
    {
        return number_format((float) $value, $decimals, ',', '.');
    }

    private function unidad(int $numero): string
    {
        return match ($numero) {
            9 => 'NUEVE',
            8 => 'OCHO',
            7 => 'SIETE',
            6 => 'SEIS',
            5 => 'CINCO',
            4 => 'CUATRO',
            3 => 'TRES',
            2 => 'DOS',
            1 => 'UNO',
            default => 'CERO',
        };
    }

    private function decena(int $numero): string
    {
        if ($numero >= 90 && $numero <= 99) {
            $texto = 'NOVENTA ';
            if ($numero > 90) {
                $texto .= 'Y '.$this->unidad($numero - 90);
            }

            return $texto;
        }
        if ($numero >= 80 && $numero <= 89) {
            $texto = 'OCHENTA ';
            if ($numero > 80) {
                $texto .= 'Y '.$this->unidad($numero - 80);
            }

            return $texto;
        }
        if ($numero >= 70 && $numero <= 79) {
            $texto = 'SETENTA ';
            if ($numero > 70) {
                $texto .= 'Y '.$this->unidad($numero - 70);
            }

            return $texto;
        }
        if ($numero >= 60 && $numero <= 69) {
            $texto = 'SESENTA ';
            if ($numero > 60) {
                $texto .= 'Y '.$this->unidad($numero - 60);
            }

            return $texto;
        }
        if ($numero >= 50 && $numero <= 59) {
            $texto = 'CINCUENTA ';
            if ($numero > 50) {
                $texto .= 'Y '.$this->unidad($numero - 50);
            }

            return $texto;
        }
        if ($numero >= 40 && $numero <= 49) {
            $texto = 'CUARENTA ';
            if ($numero > 40) {
                $texto .= 'Y '.$this->unidad($numero - 40);
            }

            return $texto;
        }
        if ($numero >= 30 && $numero <= 39) {
            $texto = 'TREINTA ';
            if ($numero > 30) {
                $texto .= 'Y '.$this->unidad($numero - 30);
            }

            return $texto;
        }
        if ($numero >= 20 && $numero <= 29) {
            return $numero === 20 ? 'VEINTE ' : 'VEINTI'.$this->unidad($numero - 20);
        }
        if ($numero >= 10 && $numero <= 19) {
            return match ($numero) {
                10 => 'DIEZ ',
                11 => 'ONCE ',
                12 => 'DOCE ',
                13 => 'TRECE ',
                14 => 'CATORCE ',
                15 => 'QUINCE ',
                16 => 'DIECISEIS ',
                17 => 'DIECISIETE ',
                18 => 'DIECIOCHO ',
                default => 'DIECINUEVE ',
            };
        }

        return $this->unidad($numero);
    }

    private function centena(int $numero): string
    {
        if ($numero >= 900 && $numero <= 999) {
            return 'NOVECIENTOS '.($numero > 900 ? $this->decena($numero - 900) : '');
        }
        if ($numero >= 800 && $numero <= 899) {
            return 'OCHOCIENTOS '.($numero > 800 ? $this->decena($numero - 800) : '');
        }
        if ($numero >= 700 && $numero <= 799) {
            return 'SETECIENTOS '.($numero > 700 ? $this->decena($numero - 700) : '');
        }
        if ($numero >= 600 && $numero <= 699) {
            return 'SEISCIENTOS '.($numero > 600 ? $this->decena($numero - 600) : '');
        }
        if ($numero >= 500 && $numero <= 599) {
            return 'QUINIENTOS '.($numero > 500 ? $this->decena($numero - 500) : '');
        }
        if ($numero >= 400 && $numero <= 499) {
            return 'CUATROCIENTOS '.($numero > 400 ? $this->decena($numero - 400) : '');
        }
        if ($numero >= 300 && $numero <= 399) {
            return 'TRESCIENTOS '.($numero > 300 ? $this->decena($numero - 300) : '');
        }
        if ($numero >= 200 && $numero <= 299) {
            return 'DOSCIENTOS '.($numero > 200 ? $this->decena($numero - 200) : '');
        }
        if ($numero >= 100 && $numero <= 199) {
            return $numero === 100 ? 'CIEN ' : 'CIENTO '.$this->decena($numero - 100);
        }

        return $this->decena($numero);
    }

    private function miles(int $numero): string
    {
        if ($numero >= 1000 && $numero < 2000) {
            return 'MIL '.$this->centena($numero % 1000);
        }
        if ($numero >= 2000 && $numero < 10000) {
            return $this->unidad((int) floor($numero / 1000)).' MIL '.$this->centena($numero % 1000);
        }

        return $this->centena($numero);
    }

    private function decmiles(int $numero): string
    {
        if ($numero === 10000) {
            return 'DIEZ MIL';
        }
        if ($numero > 10000 && $numero < 20000) {
            return $this->decena((int) floor($numero / 1000)).'MIL '.$this->centena($numero % 1000);
        }
        if ($numero >= 20000 && $numero < 100000) {
            return $this->decena((int) floor($numero / 1000)).' MIL '.$this->miles($numero % 1000);
        }

        return $this->miles($numero);
    }

    private function cienmiles(int $numero): string
    {
        if ($numero === 100000) {
            return 'CIEN MIL';
        }
        if ($numero >= 100000 && $numero < 1000000) {
            return $this->centena((int) floor($numero / 1000)).' MIL '.$this->centena($numero % 1000);
        }

        return $this->decmiles($numero);
    }

    private function millon(int $numero): string
    {
        if ($numero >= 1000000 && $numero < 2000000) {
            return 'UN MILLON '.$this->cienmiles($numero % 1000000);
        }
        if ($numero >= 2000000 && $numero < 10000000) {
            return $this->unidad((int) floor($numero / 1000000)).' MILLONES '.$this->cienmiles($numero % 1000000);
        }

        return $this->cienmiles($numero);
    }

    private function decmillon(int $numero): string
    {
        if ($numero === 10000000) {
            return 'DIEZ MILLONES';
        }
        if ($numero > 10000000 && $numero < 20000000) {
            return $this->decena((int) floor($numero / 1000000)).'MILLONES '.$this->cienmiles($numero % 1000000);
        }
        if ($numero >= 20000000 && $numero < 100000000) {
            return $this->decena((int) floor($numero / 1000000)).' MILLONES '.$this->millon($numero % 1000000);
        }

        return $this->millon($numero);
    }

    private function cienmillon(int $numero): string
    {
        if ($numero === 100000000) {
            return 'CIEN MILLONES';
        }
        if ($numero >= 100000000 && $numero < 1000000000) {
            return $this->centena((int) floor($numero / 1000000)).' MILLONES '.$this->millon($numero % 1000000);
        }

        return $this->decmillon($numero);
    }

    private function milmillon(int $numero): string
    {
        if ($numero >= 1000000000 && $numero < 2000000000) {
            return 'MIL '.$this->cienmillon($numero % 1000000000);
        }
        if ($numero >= 2000000000 && $numero < 10000000000) {
            return $this->unidad((int) floor($numero / 1000000000)).' MIL '.$this->cienmillon($numero % 1000000000);
        }

        return $this->cienmillon($numero);
    }

    private function convertir(string $numero): string
    {
        $num = str_replace('.', '', $numero);
        $cents = substr($num, strlen($num) - 2, strlen($num) - 1);
        $num = (int) $num;
        $numf = $this->milmillon($num);

        return ' '.$numf.' CON '.$cents.'/100';
    }
}

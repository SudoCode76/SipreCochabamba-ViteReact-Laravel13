<?php

namespace App\Services\Items;

use App\Models\Item;
use App\Support\Pdf\MaterialBreakdownPdf;
use Illuminate\Http\Response;

class MaterialBreakdownPdfService
{
    public function __construct(
        private readonly ItemCompositionService $itemCompositionService,
    ) {}

    public function stream(Item $item): Response
    {
        $item->loadMissing(['unitMeasure']);
        $materials = $this->itemCompositionService->listByType($item, 1);

        $pdf = new MaterialBreakdownPdf(
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
        $pdf->AddPage('P', 'A4');
        $pdf->SetDisplayMode('real');
        $pdf->SetAutoPageBreak(true, 10);
        $pdf->SetFont('dejavusans', '', 7, '', true);
        $pdf->ln();

        if ($item->id_item === null || strtoupper((string) $item->estado) !== 'AC') {
            $pdf->writeHTML('<div><h1 style="color:red" align="center">El item esta inactivo.....!</h1></div>', true, false, true, false, '');
            $pdf->SetFont('dejavusans', '', 10, '', true);
        } elseif ($materials === []) {
            $pdf->writeHTML('<div><h1>El item esta incompleto por favor  revisar la existencia de material</h1></div>', true, false, true, false, '');
            $pdf->SetFont('dejavusans', '', 10, '', true);
        } else {
            $pdf->SetFont('dejavusans', '', 8, '', true);
            $pdf->ln();
            $pdf->writeHTML($this->buildHtml($item, $materials), true, false, true, false, '');
            $pdf->SetFont('dejavusans', '', 10, '', true);
        }

        $content = $pdf->Output('desglose_materiales.pdf', 'S');

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="desglose_materiales.pdf"',
        ]);
    }

    private function buildHtml(Item $item, array $materials): string
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
  .grupo{
    background-color: #ccc;
    font-size:12px;
    font-style:bold;
  }
  .subgrupo{
    background-color: #ccc00;
    font-size:10px;
    font-style:italic;
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

        foreach ($materials as $index => $material) {
            $quantity = (float) ($material['cantidad'] ?? 0);
            $unitPrice = (float) ($material['precio_unitario'] ?? 0);
            $partial = $quantity * $unitPrice;
            $total += $partial;

            $html .= '
          <tr>
            <td width="40">'.($index + 1).'</td>
            <td width="280">'.htmlentities((string) ($material['descripcion'] ?? '')).'</td>
            <td width="60">'.htmlentities((string) ($material['unidad'] ?? '')).'</td>
            <td width="60" align="right">'.$this->legacyNumber($quantity, 4).'</td>
            <td width="110" align="right">'.$this->legacyNumber($unitPrice, 2).'</td>
            <td width="110" align="right">'.$this->legacyNumber($partial, 2).'</td>
          </tr>';
        }

        $html .= '
      <tr bgcolor="#ccebe8">
        <td colspan="5"><b>TOTAL</b></td>
        <td><b>'.$this->legacyNumber($total, 2).'</b></td>
      </tr>
      <tr>
      <td width="100%"><b>SON: BOLIVIANOS  '.$this->convertir($total).' </b></td>
      </tr>
      </tbody>
      </table>';

        return $html;
    }

    private function legacyNumber(float $value, int $decimals): string
    {
        return number_format($value, $decimals, ',', '.');
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
            return 'NOVENTA '.($numero > 90 ? 'Y '.$this->unidad($numero - 90) : '');
        }
        if ($numero >= 80 && $numero <= 89) {
            return 'OCHENTA '.($numero > 80 ? 'Y '.$this->unidad($numero - 80) : '');
        }
        if ($numero >= 70 && $numero <= 79) {
            return 'SETENTA '.($numero > 70 ? 'Y '.$this->unidad($numero - 70) : '');
        }
        if ($numero >= 60 && $numero <= 69) {
            return 'SESENTA '.($numero > 60 ? 'Y '.$this->unidad($numero - 60) : '');
        }
        if ($numero >= 50 && $numero <= 59) {
            return 'CINCUENTA '.($numero > 50 ? 'Y '.$this->unidad($numero - 50) : '');
        }
        if ($numero >= 40 && $numero <= 49) {
            return 'CUARENTA '.($numero > 40 ? 'Y '.$this->unidad($numero - 40) : '');
        }
        if ($numero >= 30 && $numero <= 39) {
            return 'TREINTA '.($numero > 30 ? 'Y '.$this->unidad($numero - 30) : '');
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

    private function convertir(float $value): string
    {
        $formatted = number_format($value, 2, '.', '');
        $parts = explode('.', $formatted);
        $integer = (int) ($parts[0] ?? 0);
        $cents = str_pad((string) ($parts[1] ?? '00'), 2, '0');

        return ' '.$this->milmillon($integer).' CON '.$cents.'/100';
    }
}

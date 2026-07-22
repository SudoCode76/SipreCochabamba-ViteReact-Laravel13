<?php

namespace App\Modules\Projects\Services;

use App\Models\Project;
use App\Support\Pdf\LegacyPdfFormat;
use App\Support\Pdf\MunicipalReportPdfFactory;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProjectInputBreakdownPdfService
{
    public function __construct(
        private readonly ProjectItemInputSnapshotService $snapshotService,
    ) {}

    public function stream(Project $project, int $type, ?array $signatureLayout = null): Response
    {
        $rows = $this->rows($project, $type);
        $pdf = MunicipalReportPdfFactory::make($this->titleFor($type));
        MunicipalReportPdfFactory::render($pdf, function () use ($pdf, $project, $rows, $type): void {
            $pdf->ln();

            if ($rows === []) {
                $pdf->writeHTML('<div><h1>No existen registros!</h1></div>', true, false, true, false, '');
            } else {
                $pdf->SetFont('dejavusans', '', 8, '', true);
                $pdf->ln();
                $pdf->writeHTML($this->buildHtml($project, $rows, $type), true, false, true, false, '');
            }
        }, $signatureLayout);

        return MunicipalReportPdfFactory::inlineResponse($pdf, $this->filenameFor($type));
    }

    public function rows(Project $project, int $type): array
    {
        return $this->queryRows($project, $type)
            ->map(function ($row): array {
                $quantity = round((float) $row->cantidad, 4);
                $unitPrice = round((float) $row->precio, 2);

                return [
                    'id_item_insumo' => (int) $row->id_item_insumo,
                    'id_insumo' => (int) $row->id_insumo,
                    'id_item' => (int) $row->id_item,
                    'id_modulo' => $row->id_modulo !== null ? (int) $row->id_modulo : null,
                    'modulo' => $row->modulo ?: 'General',
                    'prioridad' => (int) $row->prioridad,
                    'nombre_item' => $row->nombre_item,
                    'descripcion' => $row->descripcion,
                    'unidad' => $row->unidad,
                    'cantidad' => $quantity,
                    'precio_unitario' => $unitPrice,
                    'parcial' => round($quantity * $unitPrice, 2),
                ];
            })
            ->all();
    }

    private function queryRows(Project $project, int $type)
    {
        $this->snapshotService->ensureForProject($project);

        return DB::table('proyecto_item_insumo_snapshot')
            ->join('proyecto_item', 'proyecto_item.id_proyecto_item', '=', 'proyecto_item_insumo_snapshot.id_proyecto_item')
            ->join('item', 'item.id_item', '=', 'proyecto_item.id_item')
            ->leftJoin('modulo', 'modulo.id_modulo', '=', 'proyecto_item.id_modulo')
            ->where('proyecto_item_insumo_snapshot.estado', '<>', 'EX')
            ->where('proyecto_item.estado', 'AC')
            ->where('proyecto_item.id_proyecto', $project->id_proyecto)
            ->where('proyecto_item_insumo_snapshot.tipo', $type)
            ->orderByRaw('COALESCE(modulo.nombre_modulo, ?)', ['General'])
            ->orderBy('proyecto_item.prioridad')
            ->orderBy('item.item')
            ->orderBy('proyecto_item_insumo_snapshot.descripcion')
            ->select([
                'proyecto_item_insumo_snapshot.id_snapshot as id_item_insumo',
                'proyecto_item_insumo_snapshot.id_insumo',
                'proyecto_item.id_item',
                'proyecto_item.id_modulo',
                'modulo.nombre_modulo as modulo',
                'proyecto_item_insumo_snapshot.cantidad',
                'proyecto_item.prioridad',
                'item.item as nombre_item',
                'proyecto_item_insumo_snapshot.descripcion',
                'proyecto_item_insumo_snapshot.precio_unitario as precio',
                'proyecto_item_insumo_snapshot.unidad',
            ])
            ->get();
    }

    private function buildHtml(Project $project, array $rows, int $type): string
    {
        $total = 0.0;
        $moduleTotal = 0.0;
        $lastModule = null;
        $lastItemName = null;
        $position = 0;
        $html = '
 <style>
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
  <th width="70" height="40">PROYECTO:</th>
  <th colspan="5">'.htmlentities(mb_strtoupper((string) $project->nombre_proyecto, 'UTF-8')).'</th>
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

        foreach ($rows as $row) {
            if ($lastModule !== $row['modulo']) {
                if ($lastModule !== null) {
                    $html .= '
          <tr bgcolor="#d9f2ef">
            <td width="550" colspan="5"><b>SUBTOTAL MÓDULO '.htmlentities((string) $lastModule).'</b></td>
            <td width="110" align="right"><b>'.LegacyPdfFormat::number($moduleTotal, 2).'</b></td>
          </tr>';
                }

                $html .= '
          <tr bgcolor="#8cb9b5">
            <td width="660" colspan="6"><b>MÓDULO: '.htmlentities((string) $row['modulo']).'</b></td>
          </tr>';
                $lastModule = $row['modulo'];
                $lastItemName = null;
                $moduleTotal = 0.0;
            }

            if ($lastItemName !== $row['nombre_item']) {
                $html .= '
          <tr bgcolor="#ccebe8">
            <td width="660" colspan="6"><b>PR: '.$row['prioridad'].' &nbsp;&nbsp; ITEM: '.htmlentities((string) $row['nombre_item']).'</b></td>
          </tr>';
                $lastItemName = $row['nombre_item'];
            }

            $position++;
            $total += $row['parcial'];
            $moduleTotal += $row['parcial'];
            $html .= '
          <tr>
            <td width="40">'.$position.'</td>
            <td width="280">'.htmlentities((string) $row['descripcion']).'</td>
            <td width="60">'.htmlentities((string) ($row['unidad'] ?? '')).'</td>
            <td width="60" align="right">'.LegacyPdfFormat::number($row['cantidad'], 4).'</td>
            <td width="110" align="right">'.LegacyPdfFormat::number($row['precio_unitario'], 2).'</td>
            <td width="110" align="right">'.LegacyPdfFormat::number($row['parcial'], 2).'</td>
          </tr>';
        }

        if ($lastModule !== null) {
            $html .= '
      <tr bgcolor="#d9f2ef">
        <td width="550" colspan="5"><b>SUBTOTAL MÓDULO '.htmlentities((string) $lastModule).'</b></td>
        <td width="110" align="right"><b>'.LegacyPdfFormat::number($moduleTotal, 2).'</b></td>
      </tr>';
        }

        $html .= '
      <tr bgcolor="#ccebe8">
        <td width="550" colspan="5"><b>'.$this->totalLabelFor($type).'</b></td>
        <td width="110" align="right"><b>'.LegacyPdfFormat::number($total, 2).'</b></td>
      </tr>
      <tr>
      <td width="660" colspan="6"><b>'.$this->literalFor($type, $total).'</b></td>
      </tr>
      </tbody>
      </table>';

        return $html;
    }

    private function titleFor(int $type): string
    {
        return match ($type) {
            1 => 'Desglose de insumos general:MATERIALES',
            2 => 'Desglose de insumos general:MANO DE OBRA',
            3 => 'Desglose de insumos general:EQUIPO, MAQUINARIA Y HERRAMIENTAS',
            default => throw new InvalidArgumentException('Tipo de desglose no soportado.'),
        };
    }

    private function filenameFor(int $type): string
    {
        return match ($type) {
            1 => 'desglose_materiales.pdf',
            2 => 'desglose_mano_obra.pdf',
            3 => 'desglose_maquinaria.pdf',
            default => throw new InvalidArgumentException('Tipo de desglose no soportado.'),
        };
    }

    private function totalLabelFor(int $type): string
    {
        return $type === 2 ? 'TOTAL (Bs.)' : 'TOTAL';
    }

    private function literalFor(int $type, float $total): string
    {
        if ($type === 3) {
            return 'SON:'.LegacyPdfFormat::amountLiteral($total).' BOLIVIANOS.';
        }

        return 'SON: BOLIVIANOS  '.LegacyPdfFormat::amountLiteral($total).' ';
    }
}

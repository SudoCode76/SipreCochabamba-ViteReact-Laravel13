<?php

namespace App\Services\Projects;

use App\Models\Project;
use App\Models\ProjectItem;
use App\Services\Items\Fndr\FndrPriceAnalysisService;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProjectBudgetService
{
    public function __construct(
        private readonly ProjectItemIncidencePriceService $incidencePriceService,
        private readonly FndrPriceAnalysisService $priceAnalysisService,
    ) {}

    public function budgetByGroup(Project $project): array
    {
        $rows = $this->activeProjectItems($project)
            ->sortBy([
                fn (ProjectItem $item) => $item->item?->groupCatalog?->nombre_grupo,
                fn (ProjectItem $item) => $item->item?->subgroupCatalog?->descripcion,
            ])
            ->values();

        return $this->buildCurrentBudgetRows($rows);
    }

    public function budgetRecalculation(Project $project, CarbonInterface $date): array
    {
        $rows = $this->activeProjectItems($project)
            ->sortBy([
                fn (ProjectItem $item) => $item->item?->groupCatalog?->nombre_grupo,
                fn (ProjectItem $item) => $item->item?->subgroupCatalog?->descripcion,
            ])
            ->values();

        return $this->buildHistoricalBudgetRows($rows, $date);
    }

    public function incidenceSummary(Project $project, string $format): array
    {
        $rows = $this->activeProjectItems($project)
            ->sortBy([
                fn (ProjectItem $item) => $item->item?->groupCatalog?->nombre_grupo,
                fn (ProjectItem $item) => $item->item?->subgroupCatalog?->descripcion,
            ])
            ->values();

        $items = $rows->map(function (ProjectItem $row) use ($format): array {
            $price = $this->incidencePriceService->resolve($row->item, $format);
            $partial = round(((float) $row->cantidad) * $price, 4);

            return [
                'id_proyecto' => $row->id_proyecto,
                'nombre_proyecto' => $row->project?->nombre_proyecto,
                'id_item' => $row->id_item,
                'nombre_item' => $row->item?->item,
                'grupo' => $row->item?->groupCatalog?->nombre_grupo,
                'subgrupo' => $row->item?->subgroupCatalog?->descripcion,
                'cantidad' => round((float) $row->cantidad, 4),
                'precio' => $price,
                'parcial' => $partial,
            ];
        })->values();

        return [
            'items' => $items->all(),
            'totals' => [
                'total' => round($items->sum('parcial'), 4),
            ],
        ];
    }

    public function breakdownCalculation(Project $project, string $format): array
    {
        $rows = $this->activeProjectItems($project)
            ->sortBy(fn (ProjectItem $item) => $item->prioridad)
            ->values();

        $items = $rows->map(function (ProjectItem $row) use ($format): array {
            $price = $this->incidencePriceService->resolve($row->item, $format);

            return [
                'id_item' => $row->id_item,
                'nombre_item' => $row->item?->item,
                'nombre_grupo' => $row->item?->groupCatalog?->nombre_grupo,
                'nombre_subgrupo' => $row->item?->subgroupCatalog?->descripcion,
                'unidad' => $row->item?->unitMeasure?->abreviatura,
                'prioridad' => $row->prioridad,
                'cantidad' => round((float) $row->cantidad, 4),
                'precio' => $price,
                'parcial' => round(((float) $row->cantidad) * $price, 4),
            ];
        })->values();

        return [
            'items' => $items->all(),
            'totals' => [
                'total' => round($items->sum('parcial'), 4),
            ],
        ];
    }

    public function unitPrices(Project $project, string $format): array
    {
        $items = ProjectItem::query()
            ->with('item')
            ->where('id_proyecto', $project->id_proyecto)
            ->where('estado', 'AC')
            ->whereNotNull('id_item')
            ->whereHas('item')
            ->orderBy('prioridad')
            ->orderBy('id_item')
            ->get();

        return $items->map(function (ProjectItem $projectItem) use ($format): array {
            return [
                'id_proyecto_item' => $projectItem->id_proyecto_item,
                'prioridad' => $projectItem->prioridad,
                'cantidad' => round((float) $projectItem->cantidad, 4),
                'analysis' => $this->priceAnalysisService->buildCurrent($projectItem->item, $this->formatToMode($format)),
            ];
        })->values()->all();
    }

    private function activeProjectItems(Project $project): Collection
    {
        return ProjectItem::query()
            ->with(['project', 'item.groupCatalog', 'item.subgroupCatalog', 'item.unitMeasure'])
            ->where('proyecto_item.id_proyecto', $project->id_proyecto)
            ->where('proyecto_item.estado', 'AC')
            ->whereNotNull('proyecto_item.id_item')
            ->whereHas('item')
            ->get();
    }

    private function buildCurrentBudgetRows(Collection $rows): array
    {
        $items = $rows->map(function (ProjectItem $row): array {
            $acc = $this->currentAccumulators($row->id_proyecto, $row->id_item);

            return [
                'id_proyecto' => $row->id_proyecto,
                'nombre_proyecto' => $row->project?->nombre_proyecto,
                'id_item' => $row->id_item,
                'descripcion' => $row->item?->item,
                'materiales' => $acc['materiales'],
                'mano_obra' => $acc['mano_obra'],
                'herramientas' => $acc['herramientas'],
                'id_grupo' => $row->item?->groupCatalog?->id_grupo,
                'grupo' => $row->item?->groupCatalog?->nombre_grupo,
                'id_subgrupo' => $row->item?->subgroupCatalog?->id_subgrupo,
                'subgrupo' => $row->item?->subgroupCatalog?->descripcion,
            ];
        })->values();

        return [
            'items' => $items->all(),
            'totals' => [
                'materiales' => round($items->sum('materiales'), 4),
                'mano_obra' => round($items->sum('mano_obra'), 4),
                'herramientas' => round($items->sum('herramientas'), 4),
            ],
        ];
    }

    private function buildHistoricalBudgetRows(Collection $rows, CarbonInterface $date): array
    {
        $items = $rows->map(function (ProjectItem $row) use ($date): array {
            $acc = $this->historicalAccumulators($row->id_proyecto, $row->id_item, $date);

            return [
                'id_proyecto' => $row->id_proyecto,
                'nombre_proyecto' => $row->project?->nombre_proyecto,
                'id_item' => $row->id_item,
                'descripcion' => $row->item?->item,
                'materiales' => $acc['materiales'],
                'mano_obra' => $acc['mano_obra'],
                'herramientas' => $acc['herramientas'],
                'id_grupo' => $row->item?->groupCatalog?->id_grupo,
                'grupo' => $row->item?->groupCatalog?->nombre_grupo,
                'id_subgrupo' => $row->item?->subgroupCatalog?->id_subgrupo,
                'subgrupo' => $row->item?->subgroupCatalog?->descripcion,
            ];
        })->values();

        return [
            'items' => $items->all(),
            'totals' => [
                'materiales' => round($items->sum('materiales'), 4),
                'mano_obra' => round($items->sum('mano_obra'), 4),
                'herramientas' => round($items->sum('herramientas'), 4),
            ],
            'reference_date' => $date->toDateString(),
        ];
    }

    private function currentAccumulators(int $projectId, int $itemId): array
    {
        return [
            'materiales' => $this->currentAccumulatorByType($projectId, $itemId, 1),
            'mano_obra' => $this->currentAccumulatorByType($projectId, $itemId, 2),
            'herramientas' => $this->currentAccumulatorByType($projectId, $itemId, 3),
        ];
    }

    private function currentAccumulatorByType(int $projectId, int $itemId, int $type): float
    {
        $rows = DB::table('item_insumo')
            ->join('item', 'item.id_item', '=', 'item_insumo.id_item')
            ->join('insumo', 'insumo.id_insumo', '=', 'item_insumo.id_insumo')
            ->join('tipo_insumo', 'tipo_insumo.id_tipo', '=', 'insumo.tipo')
            ->join('proyecto_item', 'proyecto_item.id_item', '=', 'item.id_item')
            ->join('proyecto', 'proyecto.id_proyecto', '=', 'proyecto_item.id_proyecto')
            ->where('item_insumo.estado', 'AC')
            ->where('proyecto_item.estado', 'AC')
            ->where('proyecto_item.id_proyecto', $projectId)
            ->where('proyecto_item.id_item', $itemId)
            ->where('insumo.tipo', $type)
            ->orderBy('insumo.tipo')
            ->select(['item_insumo.cantidad', 'insumo.precio'])
            ->get();

        return round($rows->sum(fn ($row): float => round((float) $row->cantidad, 4) * round((float) $row->precio, 2)), 4);
    }

    private function historicalAccumulators(int $projectId, int $itemId, CarbonInterface $date): array
    {
        return [
            'materiales' => $this->historicalAccumulatorByType($projectId, $itemId, 1, $date),
            'mano_obra' => $this->historicalAccumulatorByType($projectId, $itemId, 2, $date),
            'herramientas' => $this->historicalAccumulatorByType($projectId, $itemId, 3, $date),
        ];
    }

    private function historicalAccumulatorByType(int $projectId, int $itemId, int $type, CarbonInterface $date): float
    {
        $rows = DB::table('item_insumo')
            ->join('item', 'item.id_item', '=', 'item_insumo.id_item')
            ->join('log_insumo', 'log_insumo.id_insumo', '=', 'item_insumo.id_insumo')
            ->join('tipo_insumo', 'tipo_insumo.id_tipo', '=', 'log_insumo.tipo')
            ->join('proyecto_item', 'proyecto_item.id_item', '=', 'item_insumo.id_item')
            ->join('proyecto', 'proyecto.id_proyecto', '=', 'proyecto_item.id_proyecto')
            ->whereDate('log_insumo.fecha', '<=', $date->toDateString())
            ->where('log_insumo.tipo', $type)
            ->where('proyecto_item.estado', 'AC')
            ->where('item_insumo.estado', 'AC')
            ->where('proyecto_item.id_item', $itemId)
            ->where('proyecto_item.id_proyecto', $projectId)
            ->orderBy('log_insumo.tipo')
            ->orderBy('log_insumo.id_insumo')
            ->orderByDesc('id_log')
            ->orderBy('item_insumo.id_item_insumo')
            ->select([
                'item_insumo.id_insumo',
                'item_insumo.cantidad',
                'log_insumo.precio',
                'log_insumo.id_log',
            ])
            ->get();

        $seen = [];
        $total = 0.0;

        foreach ($rows as $row) {
            if (isset($seen[$row->id_insumo])) {
                continue;
            }

            $seen[$row->id_insumo] = true;
            $total += round((float) $row->cantidad, 4) * round((float) $row->precio, 2);
        }

        return round($total, 4);
    }

    private function formatToMode(string $format): string
    {
        return match (strtoupper($format)) {
            'PCA' => 'general',
            'PC_OBRAS' => 'obras',
            'PC_FPS' => 'fps',
            'PC_FNDR' => 'fndr',
            'PC_UPRE' => 'upre',
            'PC_PROMAN' => 'proman',
        };
    }
}

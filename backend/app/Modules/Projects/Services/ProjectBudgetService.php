<?php

namespace App\Modules\Projects\Services;

use App\Models\Project;
use App\Models\ProjectItem;
use App\Modules\Items\Services\Analysis\ItemPriceAnalysisService;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProjectBudgetService
{
    public function __construct(
        private readonly ProjectItemIncidencePriceService $incidencePriceService,
        private readonly ItemPriceAnalysisService $priceAnalysisService,
        private readonly ProjectItemInputSnapshotService $snapshotService,
    ) {}

    public function budgetByGroup(Project $project): array
    {
        $this->snapshotService->ensureForProject($project);

        $rows = $this->activeProjectItems($project)
            ->sortBy([
                fn (ProjectItem $item) => $item->item?->groupCatalog?->nombre_grupo,
                fn (ProjectItem $item) => $item->item?->subgroupCatalog?->descripcion,
            ])
            ->values();

        return $this->buildCurrentBudgetRows($rows);
    }

    public function budgetByGroupPdfData(Project $project): array
    {
        $this->snapshotService->ensureForProject($project);

        $rows = $this->activeProjectItemsForLegacyBudgetPdf($project);

        $items = $rows->map(function (ProjectItem $row): array {
            $acc = $this->currentAccumulators($row);

            return [
                'id_proyecto' => $row->id_proyecto,
                'nombre_proy' => $row->project?->nombre_proyecto,
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

        $completeItems = $items
            ->filter(fn (array $item): bool => $item['materiales'] > 0 || $item['mano_obra'] > 0 || $item['herramientas'] > 0)
            ->values();

        return [
            'items_proyecto_count' => $rows->count(),
            'items' => $completeItems->all(),
            'totals' => [
                'materiales' => round($completeItems->sum('materiales'), 4),
                'mano_obra' => round($completeItems->sum('mano_obra'), 4),
                'herramientas' => round($completeItems->sum('herramientas'), 4),
            ],
        ];
    }

    public function budgetRecalculation(Project $project, CarbonInterface $date): array
    {
        $this->snapshotService->ensureForProject($project);

        $rows = $this->activeProjectItemsForLegacyBudgetPdf($project);

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

    public function generalBudgetPdfItems(Project $project, string $format, ProjectLegacyUnitPriceService $legacyUnitPriceService): array
    {
        return $this->activeProjectItems($project)
            ->sortBy(fn (ProjectItem $item) => $item->prioridad)
            ->values()
            ->map(function (ProjectItem $row) use ($format, $legacyUnitPriceService): array {
                $price = $legacyUnitPriceService->resolve($row->item, $format);

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
            })
            ->all();
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

    public function unitPricesForLegacyProjectPdf(Project $project, string $format): array
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
                'analysis' => $this->priceAnalysisService->buildLegacyProjectCurrent($projectItem->item, $this->formatToMode($format)),
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

    private function activeProjectItemsForLegacyBudgetPdf(Project $project): Collection
    {
        return ProjectItem::query()
            ->select('proyecto_item.*')
            ->join('item', 'item.id_item', '=', 'proyecto_item.id_item')
            ->join('grupo', 'grupo.id_grupo', '=', 'item.grupo')
            ->join('sub_grupo', 'sub_grupo.id_subgrupo', '=', 'item.subgrupo')
            ->with(['project', 'item.groupCatalog', 'item.subgroupCatalog', 'item.unitMeasure'])
            ->where('proyecto_item.id_proyecto', $project->id_proyecto)
            ->where('proyecto_item.estado', 'AC')
            ->whereNotNull('proyecto_item.id_item')
            ->orderBy('grupo.nombre_grupo')
            ->orderBy('sub_grupo.descripcion')
            ->orderBy('proyecto_item.id_proyecto_item')
            ->get();
    }

    private function buildCurrentBudgetRows(Collection $rows): array
    {
        $items = $rows->map(function (ProjectItem $row): array {
            $acc = $this->currentAccumulators($row);

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
            $acc = $this->historicalAccumulators($row, $date);

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

    private function currentAccumulators(ProjectItem $projectItem): array
    {
        return [
            'materiales' => $this->currentAccumulatorByType($projectItem, 1),
            'mano_obra' => $this->currentAccumulatorByType($projectItem, 2),
            'herramientas' => $this->currentAccumulatorByType($projectItem, 3),
        ];
    }

    private function currentAccumulatorByType(ProjectItem $projectItem, int $type): float
    {
        $rows = DB::table('proyecto_item_insumo_snapshot')
            ->where('id_proyecto_item', $projectItem->id_proyecto_item)
            ->where('estado', 'AC')
            ->where('tipo', $type)
            ->select(['cantidad', 'precio_unitario'])
            ->get();

        return round($rows->sum(fn ($row): float => round((float) $row->cantidad, 4) * round((float) $row->precio_unitario, 2)), 4);
    }

    private function historicalAccumulators(ProjectItem $projectItem, CarbonInterface $date): array
    {
        return [
            'materiales' => $this->historicalAccumulatorByType($projectItem, 1, $date),
            'mano_obra' => $this->historicalAccumulatorByType($projectItem, 2, $date),
            'herramientas' => $this->historicalAccumulatorByType($projectItem, 3, $date),
        ];
    }

    private function historicalAccumulatorByType(ProjectItem $projectItem, int $type, CarbonInterface $date): float
    {
        $rows = DB::table('proyecto_item_insumo_snapshot')
            ->join('log_insumo', 'log_insumo.id_insumo', '=', 'proyecto_item_insumo_snapshot.id_insumo')
            ->join('tipo_insumo', 'tipo_insumo.id_tipo', '=', 'log_insumo.tipo')
            ->where('log_insumo.fecha', '<=', $date->toDateString())
            ->where('log_insumo.tipo', $type)
            ->where('proyecto_item_insumo_snapshot.estado', 'AC')
            ->where('proyecto_item_insumo_snapshot.id_proyecto_item', $projectItem->id_proyecto_item)
            ->orderBy('log_insumo.tipo')
            ->orderBy('log_insumo.id_insumo')
            ->orderByDesc('id_log')
            ->orderBy('proyecto_item_insumo_snapshot.id_snapshot')
            ->select([
                'proyecto_item_insumo_snapshot.id_insumo',
                'proyecto_item_insumo_snapshot.cantidad',
                'log_insumo.precio',
                'log_insumo.id_log',
            ])
            ->get();

        $lastInputId = null;
        $lastLogId = 0;
        $total = 0.0;

        foreach ($rows as $row) {
            if ((int) $row->id_insumo === $lastInputId) {
                if ($type !== 3) {
                    $lastLogId = 0;
                }

                $lastInputId = (int) $row->id_insumo;

                continue;
            }

            if ((int) $row->id_log >= $lastLogId) {
                $total += round((float) $row->cantidad, 4) * round((float) $row->precio, 2);
                $lastLogId = (int) $row->id_log;
                $lastInputId = (int) $row->id_insumo;
            }
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

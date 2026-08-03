<?php

namespace App\Modules\Projects\Services;

use App\Models\Project;
use App\Models\ProjectItem;
use App\Models\ProjectItemInputSnapshot;
use Illuminate\Support\Facades\DB;

class ProjectItemInputSnapshotService
{
    public function __construct(
        private readonly ProjectPercentageSnapshotService $percentageSnapshotService,
        private readonly ProjectSnapshotAnalysisService $analysisService,
    ) {}

    public function syncForProjectItem(ProjectItem $projectItem): void
    {
        $projectItem->loadMissing(['item.groupCatalog', 'item.subgroupCatalog', 'item.unitMeasure']);
        $item = $projectItem->item;

        if (! $projectItem->id_item || ! $item) {
            $projectItem->update(['estado_catalogo_snapshot' => 'NO_DISPONIBLE']);
            ProjectItemInputSnapshot::query()
                ->where('id_proyecto_item', $projectItem->id_proyecto_item)
                ->update(['estado' => 'DP']);

            return;
        }

        $projectItem->update([
            'nombre_snapshot' => $item->item,
            'grupo_snapshot' => $item->groupCatalog?->nombre_grupo,
            'subgrupo_snapshot' => $item->subgroupCatalog?->descripcion,
            'unidad_snapshot' => $item->unitMeasure?->abreviatura,
            'estado_catalogo_snapshot' => $item->estado,
        ]);

        $rows = DB::table('item_insumo')
            ->leftJoin('insumo', 'insumo.id_insumo', '=', 'item_insumo.id_insumo')
            ->leftJoin('unidad_medida', 'unidad_medida.id_unidad_medida', '=', 'insumo.unidad_medida')
            ->where('item_insumo.id_item', $projectItem->id_item)
            ->where('item_insumo.estado', 'AC')
            ->orderBy('item_insumo.id_item_insumo')
            ->get([
                'item_insumo.id_item_insumo',
                'item_insumo.id_insumo',
                'item_insumo.cantidad',
                'insumo.descripcion',
                'insumo.tipo',
                'insumo.precio',
                'insumo.estado as insumo_estado',
                'unidad_medida.abreviatura',
            ]);

        $now = now();
        $seenSourceIds = [];

        foreach ($rows as $row) {
            $sourceId = (int) $row->id_item_insumo;
            $seenSourceIds[] = $sourceId;
            $quantity = round((float) $row->cantidad, 4);
            $unitPrice = round((float) $row->precio, 4);
            $snapshot = ProjectItemInputSnapshot::query()
                ->where('id_proyecto_item', $projectItem->id_proyecto_item)
                ->where('id_item_insumo_origen', $sourceId)
                ->first();

            $catalogStatus = $row->id_insumo === null ? 'DP' : strtoupper((string) ($row->insumo_estado ?? 'DP'));
            $status = $catalogStatus === 'AC' ? 'AC' : $catalogStatus;
            $payload = [
                'id_insumo' => $row->id_insumo !== null ? (int) $row->id_insumo : $snapshot?->id_insumo,
                'descripcion' => (string) ($row->descripcion ?? $snapshot?->descripcion ?? 'Insumo no disponible'),
                'tipo' => $row->tipo !== null ? (int) $row->tipo : $snapshot?->tipo,
                'unidad' => $row->abreviatura ?? $snapshot?->unidad,
                'cantidad' => $quantity,
                'estado' => $status,
            ];

            if ($catalogStatus === 'AC' || ! $snapshot) {
                $payload['precio_unitario'] = $unitPrice;
                $payload['parcial'] = round($quantity * $unitPrice, 4);
            }

            if ($snapshot) {
                $snapshot->update($payload);
            } else {
                ProjectItemInputSnapshot::query()->create(array_merge($payload, [
                    'id_proyecto_item' => $projectItem->id_proyecto_item,
                    'id_item_insumo_origen' => $sourceId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }

        ProjectItemInputSnapshot::query()
            ->where('id_proyecto_item', $projectItem->id_proyecto_item)
            ->when($seenSourceIds !== [], fn ($query) => $query->whereNotIn('id_item_insumo_origen', $seenSourceIds))
            ->when($seenSourceIds === [], fn ($query) => $query)
            ->update(['estado' => 'DP']);
    }

    public function ensureForProject(Project $project): void
    {
        $this->percentageSnapshotService->ensure($project);

        $items = ProjectItem::query()
            ->where('id_proyecto', $project->id_proyecto)
            ->where('estado', 'AC')
            ->whereNotNull('id_item')
            ->get();

        if (! $project->isFrozen()) {
            $this->percentageSnapshotService->synchronize($project);
            $items->each(fn (ProjectItem $projectItem) => $this->syncForProjectItem($projectItem));
            $this->recalculateProject($project, $items);

            return;
        }

        $items
            ->filter(fn (ProjectItem $projectItem): bool => ! ProjectItemInputSnapshot::query()
                ->where('id_proyecto_item', $projectItem->id_proyecto_item)
                ->exists())
            ->each(fn (ProjectItem $projectItem) => $this->syncForProjectItem($projectItem));
    }

    public function recalculateProject(Project $project, ?iterable $items = null): void
    {
        $items = collect($items ?? ProjectItem::query()
            ->where('id_proyecto', $project->id_proyecto)
            ->where('estado', 'AC')
            ->whereNotNull('id_item')
            ->get());

        $total = 0.0;

        foreach ($items as $projectItem) {
            try {
                $price = round($this->analysisService->price($projectItem, 'PCA'), 4);
            } catch (\InvalidArgumentException) {
                $price = (float) $projectItem->precio;
            }

            $projectItem->update(['precio' => $price]);
            $total += $price * (float) $projectItem->cantidad;
        }

        $project->update(['precio' => round($total, 4)]);
    }

    public function warnings(Project $project): array
    {
        $this->ensureForProject($project);

        $items = DB::table('proyecto_item')
            ->leftJoin('item', 'item.id_item', '=', 'proyecto_item.id_item')
            ->leftJoin('modulo', 'modulo.id_modulo', '=', 'proyecto_item.id_modulo')
            ->where('proyecto_item.id_proyecto', $project->id_proyecto)
            ->where('proyecto_item.estado', 'AC')
            ->whereNotNull('proyecto_item.id_item')
            ->where(function ($query): void {
                $query->whereNull('item.id_item')
                    ->orWhere('item.estado', '<>', 'AC');
            })
            ->orderBy('proyecto_item.prioridad')
            ->orderBy('proyecto_item.id_proyecto_item')
            ->get([
                'proyecto_item.id_proyecto_item',
                'proyecto_item.id_item',
                'proyecto_item.prioridad',
                'proyecto_item.nombre_snapshot',
                'proyecto_item.estado_catalogo_snapshot',
                'item.item as name',
                'item.estado as status',
                'modulo.nombre_modulo as module',
            ])
            ->map(fn ($row): array => [
                'id_proyecto_item' => (int) $row->id_proyecto_item,
                'id_item' => $row->id_item !== null ? (int) $row->id_item : null,
                'name' => $row->nombre_snapshot ?? $row->name ?? 'Item no disponible',
                'status' => $row->estado_catalogo_snapshot ?? $row->status ?? 'NO_DISPONIBLE',
                'status_label' => $this->statusLabel($row->estado_catalogo_snapshot ?? $row->status),
                'priority' => $row->prioridad !== null ? (int) $row->prioridad : null,
                'module' => $row->module,
            ])
            ->values();

        $inputs = DB::table('proyecto_item_insumo_snapshot')
            ->join('proyecto_item', 'proyecto_item.id_proyecto_item', '=', 'proyecto_item_insumo_snapshot.id_proyecto_item')
            ->leftJoin('item', 'item.id_item', '=', 'proyecto_item.id_item')
            ->leftJoin('insumo', 'insumo.id_insumo', '=', 'proyecto_item_insumo_snapshot.id_insumo')
            ->where('proyecto_item.id_proyecto', $project->id_proyecto)
            ->where('proyecto_item.estado', 'AC')
            ->where(function ($query): void {
                $query->whereIn('proyecto_item_insumo_snapshot.estado', ['DC', 'DP'])
                    ->orWhereNull('insumo.id_insumo')
                    ->orWhere('insumo.estado', '<>', 'AC');
            })
            ->orderBy('proyecto_item.prioridad')
            ->orderBy('proyecto_item_insumo_snapshot.descripcion')
            ->get([
                'proyecto_item_insumo_snapshot.id_snapshot',
                'proyecto_item_insumo_snapshot.id_proyecto_item',
                'proyecto_item_insumo_snapshot.id_insumo',
                'proyecto_item_insumo_snapshot.descripcion',
                'proyecto_item_insumo_snapshot.tipo',
                'proyecto_item_insumo_snapshot.unidad',
                'proyecto_item_insumo_snapshot.estado as snapshot_status',
                'insumo.estado as status',
                'proyecto_item.nombre_snapshot as snapshot_item_name',
                'item.item as item_name',
                'proyecto_item.prioridad',
            ])
            ->map(fn ($row): array => [
                'id_snapshot' => (int) $row->id_snapshot,
                'id_proyecto_item' => (int) $row->id_proyecto_item,
                'id_insumo' => $row->id_insumo !== null ? (int) $row->id_insumo : null,
                'description' => $row->descripcion,
                'type' => $row->tipo !== null ? (int) $row->tipo : null,
                'unit' => $row->unidad,
                'status' => $row->snapshot_status ?? $row->status ?? 'NO_DISPONIBLE',
                'status_label' => $this->statusLabel($row->snapshot_status ?? $row->status),
                'item_name' => $row->snapshot_item_name ?? $row->item_name,
                'priority' => $row->prioridad !== null ? (int) $row->prioridad : null,
            ])
            ->values();

        return [
            'items' => $items->all(),
            'inputs' => $inputs->all(),
            'summary' => [
                'items_count' => $items->count(),
                'inputs_count' => $inputs->count(),
                'has_warnings' => $items->isNotEmpty() || $inputs->isNotEmpty(),
            ],
        ];
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'AC' => 'HABILITADO',
            'DC' => 'INHABILITADO',
            'DP' => 'ELIMINADO',
            default => 'NO DISPONIBLE',
        };
    }
}

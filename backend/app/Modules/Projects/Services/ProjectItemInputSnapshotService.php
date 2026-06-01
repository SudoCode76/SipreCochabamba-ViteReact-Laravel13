<?php

namespace App\Modules\Projects\Services;

use App\Models\Project;
use App\Models\ProjectItem;
use Illuminate\Support\Facades\DB;

class ProjectItemInputSnapshotService
{
    public function syncForProjectItem(ProjectItem $projectItem): void
    {
        DB::table('proyecto_item_insumo_snapshot')
            ->where('id_proyecto_item', $projectItem->id_proyecto_item)
            ->delete();

        if (! $projectItem->id_item) {
            return;
        }

        $rows = DB::table('item_insumo')
            ->leftJoin('insumo', 'insumo.id_insumo', '=', 'item_insumo.id_insumo')
            ->leftJoin('unidad_medida', 'unidad_medida.id_unidad_medida', '=', 'insumo.unidad_medida')
            ->where('item_insumo.id_item', $projectItem->id_item)
            ->where('item_insumo.estado', 'AC')
            ->orderBy('item_insumo.id_item_insumo')
            ->get([
                'item_insumo.id_insumo',
                'item_insumo.cantidad',
                'insumo.descripcion',
                'insumo.tipo',
                'insumo.precio',
                'unidad_medida.abreviatura',
            ]);

        $now = now();
        $payload = $rows->map(function ($row) use ($projectItem, $now): array {
            $quantity = round((float) $row->cantidad, 4);
            $unitPrice = round((float) $row->precio, 4);

            return [
                'id_proyecto_item' => $projectItem->id_proyecto_item,
                'id_insumo' => $row->id_insumo !== null ? (int) $row->id_insumo : null,
                'descripcion' => (string) ($row->descripcion ?? 'Insumo no disponible'),
                'tipo' => $row->tipo !== null ? (int) $row->tipo : null,
                'unidad' => $row->abreviatura,
                'cantidad' => $quantity,
                'precio_unitario' => $unitPrice,
                'parcial' => round($quantity * $unitPrice, 4),
                'estado' => 'AC',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        })->all();

        if ($payload !== []) {
            DB::table('proyecto_item_insumo_snapshot')->insert($payload);
        }
    }

    public function ensureForProject(Project $project): void
    {
        ProjectItem::query()
            ->where('id_proyecto', $project->id_proyecto)
            ->where('estado', 'AC')
            ->whereNotNull('id_item')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('proyecto_item_insumo_snapshot')
                    ->whereColumn('proyecto_item_insumo_snapshot.id_proyecto_item', 'proyecto_item.id_proyecto_item');
            })
            ->get()
            ->each(fn (ProjectItem $projectItem) => $this->syncForProjectItem($projectItem));
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
                'item.item as name',
                'item.estado as status',
                'modulo.nombre_modulo as module',
            ])
            ->map(fn ($row): array => [
                'id_proyecto_item' => (int) $row->id_proyecto_item,
                'id_item' => $row->id_item !== null ? (int) $row->id_item : null,
                'name' => $row->name ?? 'Item no disponible',
                'status' => $row->status ?? 'NO_DISPONIBLE',
                'status_label' => $this->statusLabel($row->status),
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
            ->where('proyecto_item_insumo_snapshot.estado', 'AC')
            ->where(function ($query): void {
                $query->whereNull('insumo.id_insumo')
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
                'insumo.estado as status',
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
                'status' => $row->status ?? 'NO_DISPONIBLE',
                'status_label' => $this->statusLabel($row->status),
                'item_name' => $row->item_name,
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

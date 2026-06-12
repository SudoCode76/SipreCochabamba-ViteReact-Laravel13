<?php

namespace App\Modules\Projects\Services;

use App\Models\Project;
use App\Models\ProjectItem;
use Illuminate\Validation\ValidationException;

class ProjectVersionComparisonService
{
    public function compare(Project $context, Project $base, Project $target): array
    {
        $rootId = $context->id_proyecto_raiz ?: $context->id_proyecto;

        if (($base->id_proyecto_raiz ?: $base->id_proyecto) !== $rootId || ($target->id_proyecto_raiz ?: $target->id_proyecto) !== $rootId) {
            throw ValidationException::withMessages([
                'version' => ['Las versiones seleccionadas no pertenecen al mismo proyecto.'],
            ]);
        }

        $baseRows = $this->rows($base);
        $targetRows = $this->rows($target);
        $keys = $baseRows->keys()->merge($targetRows->keys())->unique()->sort()->values();

        $items = $keys->map(function (string $key) use ($baseRows, $targetRows): array {
            $baseRow = $baseRows->get($key);
            $targetRow = $targetRows->get($key);
            $changeType = $this->changeType($baseRow, $targetRow);

            return [
                'key' => $key,
                'change_type' => $changeType,
                'item' => $targetRow['item'] ?? $baseRow['item'] ?? '-',
                'base' => $baseRow,
                'target' => $targetRow,
                'diff' => [
                    'cantidad' => round((float) ($targetRow['cantidad'] ?? 0) - (float) ($baseRow['cantidad'] ?? 0), 4),
                    'precio' => round((float) ($targetRow['precio'] ?? 0) - (float) ($baseRow['precio'] ?? 0), 4),
                    'subtotal' => round((float) ($targetRow['subtotal'] ?? 0) - (float) ($baseRow['subtotal'] ?? 0), 4),
                    'prioridad_changed' => ($targetRow['prioridad'] ?? null) !== ($baseRow['prioridad'] ?? null),
                    'modulo_changed' => ($targetRow['modulo'] ?? null) !== ($baseRow['modulo'] ?? null),
                ],
            ];
        })->values();

        $baseTotal = round((float) $baseRows->sum('subtotal'), 4);
        $targetTotal = round((float) $targetRows->sum('subtotal'), 4);
        $difference = round($targetTotal - $baseTotal, 4);

        return [
            'base' => $this->versionMeta($base),
            'target' => $this->versionMeta($target),
            'summary' => [
                'base_total' => $baseTotal,
                'target_total' => $targetTotal,
                'difference' => $difference,
                'difference_percent' => $baseTotal !== 0.0 ? round(($difference / $baseTotal) * 100, 4) : null,
                'added_count' => $items->where('change_type', 'added')->count(),
                'removed_count' => $items->where('change_type', 'removed')->count(),
                'modified_count' => $items->where('change_type', 'modified')->count(),
                'unchanged_count' => $items->where('change_type', 'unchanged')->count(),
            ],
            'items' => $items->all(),
        ];
    }

    private function rows(Project $project)
    {
        return ProjectItem::query()
            ->with(['module', 'item'])
            ->where('id_proyecto', $project->id_proyecto)
            ->where('estado', 'AC')
            ->whereNotNull('id_item')
            ->orderByRaw('COALESCE(id_modulo, 0)')
            ->orderBy('prioridad')
            ->orderBy('id_proyecto_item')
            ->get()
            ->mapWithKeys(function (ProjectItem $item): array {
                $row = [
                    'id_proyecto_item' => $item->id_proyecto_item,
                    'id_item' => $item->id_item,
                    'item' => $item->nombre_snapshot ?? $item->item?->item ?? 'Item sin nombre',
                    'modulo' => $item->module?->nombre_modulo,
                    'id_modulo' => $item->id_modulo,
                    'prioridad' => $item->prioridad,
                    'cantidad' => round((float) $item->cantidad, 4),
                    'precio' => round((float) $item->precio, 4),
                    'subtotal' => round(((float) $item->cantidad) * ((float) $item->precio), 4),
                ];

                return [$this->rowKey($item, $row) => $row];
            });
    }

    private function rowKey(ProjectItem $item, array $row): string
    {
        if ($item->id_item) {
            return implode('|', [
                'item',
                $item->id_item,
                $item->id_modulo ?: 0,
                $item->prioridad ?: 0,
            ]);
        }

        return 'snapshot|'.mb_strtolower(trim((string) $row['item'])).'|'.($item->id_modulo ?: 0).'|'.($item->prioridad ?: 0);
    }

    private function changeType(?array $baseRow, ?array $targetRow): string
    {
        if (! $baseRow && $targetRow) {
            return 'added';
        }

        if ($baseRow && ! $targetRow) {
            return 'removed';
        }

        foreach (['cantidad', 'precio', 'subtotal', 'modulo', 'prioridad'] as $field) {
            if (($baseRow[$field] ?? null) !== ($targetRow[$field] ?? null)) {
                return 'modified';
            }
        }

        return 'unchanged';
    }

    private function versionMeta(Project $project): array
    {
        return [
            'id_proyecto' => $project->id_proyecto,
            'nombre_proyecto' => $project->nombre_proyecto,
            'version_number' => (int) ($project->numero_version ?? 1),
            'aprobado' => $project->aprobado,
            'is_current_version' => $project->es_version_actual === null ? true : (bool) $project->es_version_actual,
            'is_frozen' => $project->isFrozen(),
            'version_created_at' => $project->fecha_version?->toIso8601String(),
            'finalized_at' => $project->fecha_finalizacion?->toIso8601String(),
        ];
    }
}

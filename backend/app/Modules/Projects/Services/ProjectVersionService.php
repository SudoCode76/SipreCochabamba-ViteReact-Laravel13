<?php

namespace App\Modules\Projects\Services;

use App\Models\Project;
use App\Models\ProjectItem;
use App\Models\ProjectItemInputSnapshot;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectVersionService
{
    public function __construct(
        private readonly ProjectItemInputSnapshotService $snapshotService,
        private readonly ProjectPercentageSnapshotService $percentageSnapshotService,
        private readonly ProjectHistoryService $historyService,
    ) {}

    public function versions(Project $project): Collection
    {
        return Project::query()
            ->where('id_proyecto_raiz', $project->id_proyecto_raiz ?: $project->id_proyecto)
            ->where(function ($query): void {
                $query->where('es_plantilla', false)->orWhereNull('es_plantilla');
            })
            ->orderByDesc('numero_version')
            ->orderByDesc('id_proyecto')
            ->get();
    }

    public function synchronize(Project $project, ?User $actor = null, ?string $ip = null): Project
    {
        $this->assertEditable($project);
        $this->snapshotService->ensureForProject($project);

        if ($actor) {
            $this->historyService->recordVersionSynchronized($project->refresh(), $actor, $ip);
        }

        return $project->refresh();
    }

    public function createUpdatedVersion(Project $project, User $actor, ?string $ip = null, array $attributes = []): Project
    {
        if (! $project->isFrozen() || ! $project->isCurrentVersion()) {
            throw ValidationException::withMessages([
                'aprobado' => ['Solo una versión FINALIZADA y vigente puede generar una nueva versión ACTUALIZADA.'],
            ]);
        }

        return DB::transaction(function () use ($project, $actor, $ip, $attributes): Project {
            $locked = Project::query()->lockForUpdate()->findOrFail($project->id_proyecto);

            if (! $locked->isFrozen() || ! $locked->isCurrentVersion()) {
                throw ValidationException::withMessages([
                    'aprobado' => ['El proyecto ya fue actualizado por otro proceso.'],
                ]);
            }

            $rootId = $locked->id_proyecto_raiz ?: $locked->id_proyecto;
            $familyVersions = Project::query()
                ->where('id_proyecto_raiz', $rootId)
                ->lockForUpdate()
                ->get(['id_proyecto', 'numero_version']);
            $nextVersion = ((int) $familyVersions->max('numero_version')) + 1;

            $locked->update(['es_version_actual' => false]);

            $payload = collect($locked->getAttributes())
                ->only($locked->getFillable())
                ->except([
                    'id_proyecto_raiz',
                    'id_version_origen',
                    'numero_version',
                    'es_version_actual',
                    'fecha_version',
                    'fecha_finalizacion',
                ])
                ->merge($attributes)
                ->merge([
                    'aprobado' => 'AP',
                    'fecha_aprob' => null,
                    'id_usuario' => $actor->id_usuario,
                    'id_proyecto_raiz' => $rootId,
                    'id_version_origen' => $locked->id_proyecto,
                    'numero_version' => $nextVersion,
                    'es_version_actual' => true,
                    'fecha_version' => now(),
                    'fecha_finalizacion' => null,
                ])
                ->all();

            $newVersion = Project::query()->create($payload);
            $this->copyItemsAndSnapshots($locked, $newVersion, $actor);
            $this->percentageSnapshotService->copy($locked, $newVersion);
            $this->snapshotService->ensureForProject($newVersion);
            $this->historyService->recordVersionCreated($newVersion, $actor, $ip, $locked);

            return $newVersion->refresh();
        });
    }

    public function finalize(Project $project, User $actor, ?string $ip = null): Project
    {
        if (! $project->isCurrentVersion() || ! in_array(strtoupper((string) $project->aprobado), ['PD', 'AP'], true)) {
            throw ValidationException::withMessages([
                'aprobado' => ['Solo la versión vigente en DESARROLLO o ACTUALIZADA puede finalizarse.'],
            ]);
        }

        return DB::transaction(function () use ($project, $actor, $ip): Project {
            $locked = Project::query()->lockForUpdate()->findOrFail($project->id_proyecto);
            $this->snapshotService->ensureForProject($locked);
            $locked->update([
                'aprobado' => 'RV',
                'fecha_aprob' => now()->toDateString(),
                'fecha_finalizacion' => now(),
            ]);
            $this->historyService->recordVersionFinalized($locked->refresh(), $actor, $ip);

            return $locked->refresh();
        });
    }

    public function assertEditable(Project $project): void
    {
        if ($project->isFrozen() || ! $project->isCurrentVersion()) {
            throw ValidationException::withMessages([
                'version' => ['La versión seleccionada está congelada y es de solo lectura.'],
            ]);
        }
    }

    private function copyItemsAndSnapshots(Project $source, Project $target, User $actor): void
    {
        ProjectItem::query()
            ->where('id_proyecto', $source->id_proyecto)
            ->orderBy('id_proyecto_item')
            ->get()
            ->each(function (ProjectItem $sourceItem) use ($target, $actor): void {
                $targetItem = ProjectItem::query()->create([
                    'id_proyecto' => $target->id_proyecto,
                    'id_item' => $sourceItem->id_item,
                    'id_modulo' => $sourceItem->id_modulo,
                    'estado' => $sourceItem->estado,
                    'cantidad' => $sourceItem->cantidad,
                    'fecha' => now()->toDateString(),
                    'precio' => $sourceItem->precio,
                    'id_usuario' => $actor->id_usuario,
                    'prioridad' => $sourceItem->prioridad,
                    'nombre_snapshot' => $sourceItem->nombre_snapshot,
                    'grupo_snapshot' => $sourceItem->grupo_snapshot,
                    'subgrupo_snapshot' => $sourceItem->subgrupo_snapshot,
                    'unidad_snapshot' => $sourceItem->unidad_snapshot,
                    'estado_catalogo_snapshot' => $sourceItem->estado_catalogo_snapshot,
                ]);

                ProjectItemInputSnapshot::query()
                    ->where('id_proyecto_item', $sourceItem->id_proyecto_item)
                    ->orderBy('id_snapshot')
                    ->get()
                    ->each(function (ProjectItemInputSnapshot $snapshot) use ($targetItem): void {
                        ProjectItemInputSnapshot::query()->create([
                            'id_proyecto_item' => $targetItem->id_proyecto_item,
                            'id_insumo' => $snapshot->id_insumo,
                            'id_item_insumo_origen' => $snapshot->id_item_insumo_origen,
                            'descripcion' => $snapshot->descripcion,
                            'tipo' => $snapshot->tipo,
                            'unidad' => $snapshot->unidad,
                            'cantidad' => $snapshot->cantidad,
                            'precio_unitario' => $snapshot->precio_unitario,
                            'parcial' => $snapshot->parcial,
                            'estado' => $snapshot->estado === 'EX' ? 'EX' : $snapshot->estado,
                            'excluido_por' => $snapshot->excluido_por,
                            'excluido_en' => $snapshot->excluido_en,
                        ]);
                    });
            });
    }
}

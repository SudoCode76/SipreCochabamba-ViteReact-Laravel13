<?php

namespace App\Modules\Projects\Services;

use App\Models\Project;
use App\Models\ProjectHistory;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class ProjectHistoryService
{
    public function list(Project $project, array $filters): LengthAwarePaginator
    {
        $rootId = $project->id_proyecto_raiz ?: $project->id_proyecto;
        $projectIds = Project::query()
            ->where('id_proyecto', $rootId)
            ->orWhere('id_proyecto_raiz', $rootId)
            ->pluck('id_proyecto');

        return ProjectHistory::query()
            ->whereIn('id_proyecto', $projectIds)
            ->when($filters['action'] ?? null, fn ($query, string $action) => $query->where('accion', $action))
            ->when($filters['user'] ?? null, fn ($query, string $user) => $query->whereRaw('LOWER(usuario_nombre) LIKE ?', ['%'.mb_strtolower(trim($user)).'%']))
            ->when($filters['date_from'] ?? null, fn ($query, string $date) => $query->whereDate('fecha_hora', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, string $date) => $query->whereDate('fecha_hora', '<=', $date))
            ->orderByDesc('fecha_hora')
            ->orderByDesc('id_historial')
            ->paginate((int) ($filters['per_page'] ?? 10));
    }

    public function recordCreated(Project $project, ?User $actor, ?string $ip): void
    {
        $this->record($project, $actor, $ip, 'created', 'Se creó el proyecto', 'Se registró el proyecto '.$project->nombre_proyecto.'.', [
            'project' => [
                'name' => $project->nombre_proyecto,
                'location' => $project->ubicacion,
                'status' => $project->estado,
                'approval_status' => $project->aprobado,
                'responsible' => $project->nombre_responsable,
                'requester_id' => $project->solicitante,
            ],
        ]);
    }

    public function recordUpdated(Project $project, ?User $actor, ?string $ip, array $changes): void
    {
        $changedFields = array_keys($changes);
        $detail = count($changedFields) > 0
            ? 'Se actualizaron '.count($changedFields).' campos: '.implode(', ', $changedFields).'.'
            : 'Se guardó el proyecto sin cambios relevantes.';

        $this->record($project, $actor, $ip, 'updated', 'Se actualizaron datos del proyecto', $detail, [
            'changes' => $changes,
        ]);
    }

    public function recordItemsSynced(Project $project, ?User $actor, ?string $ip, array $summary): void
    {
        $added = count($summary['added'] ?? []);
        $updated = count($summary['updated'] ?? []);
        $removed = count($summary['removed'] ?? []);

        $parts = [];
        if ($added > 0) {
            $parts[] = 'se agregaron '.$added.' items';
        }
        if ($updated > 0) {
            $parts[] = 'se modificaron '.$updated.' items';
        }
        if ($removed > 0) {
            $parts[] = 'se quitaron '.$removed.' items';
        }

        $detail = count($parts) > 0
            ? ucfirst(implode(', ', $parts)).'.'
            : 'No se detectaron cambios en los items del proyecto.';

        $this->record($project, $actor, $ip, 'items_synced', 'Se actualizaron los items del proyecto', $detail, $summary);
    }

    public function recordBudgetRecalculated(Project $project, ?User $actor, ?string $ip, string $date, array $data): void
    {
        $totals = $data['totals'] ?? [];

        $this->record($project, $actor, $ip, 'budget_recalculated', 'Se recalculó el presupuesto del proyecto', 'Se recalculó el presupuesto con fecha de referencia '.$date.'.', [
            'reference_date' => $date,
            'totals' => $totals,
        ]);
    }

    public function recordPdfGenerated(Project $project, ?User $actor, ?string $ip, string $reportType, array $metadata = []): void
    {
        $this->record($project, $actor, $ip, 'pdf_generated', 'Se generó un reporte PDF', 'Se generó el reporte '.$reportType.'.', array_merge([
            'report_type' => $reportType,
        ], $metadata));
    }

    public function recordTemplateCreated(Project $project, ?User $actor, ?string $ip, Project $template, int $itemsCopied): void
    {
        $this->record($project, $actor, $ip, 'template_created', 'Se creó una planilla desde este proyecto', 'Se creó la planilla '.$template->nombre_proyecto.' copiando '.$itemsCopied.' items activos.', [
            'template_id' => $template->id_proyecto,
            'template_name' => $template->nombre_proyecto,
            'items_copied' => $itemsCopied,
        ]);
    }

    public function recordCreatedFromTemplate(Project $project, ?User $actor, ?string $ip, Project $template, int $itemsCopied): void
    {
        $this->record($project, $actor, $ip, 'created_from_template', 'Se creó el proyecto desde una planilla', 'Se creó el proyecto usando la planilla '.$template->nombre_proyecto.' y se copiaron '.$itemsCopied.' items.', [
            'template_id' => $template->id_proyecto,
            'template_name' => $template->nombre_proyecto,
            'items_copied' => $itemsCopied,
        ]);
    }

    public function recordVersionCreated(Project $project, ?User $actor, ?string $ip, Project $source): void
    {
        $this->record($project, $actor, $ip, 'version_created', 'Se creó una nueva versión del proyecto', 'Se creó la versión '.$project->numero_version.' desde la versión '.$source->numero_version.'.', [
            'version_number' => $project->numero_version,
            'source_project_id' => $source->id_proyecto,
            'source_version_number' => $source->numero_version,
        ]);
    }

    public function recordVersionFinalized(Project $project, ?User $actor, ?string $ip): void
    {
        $this->record($project, $actor, $ip, 'version_finalized', 'Se finalizó y congeló la versión', 'La versión '.$project->numero_version.' quedó congelada.', [
            'version_number' => $project->numero_version,
            'finalized_at' => $project->fecha_finalizacion?->toIso8601String(),
        ]);
    }

    public function recordVersionSynchronized(Project $project, ?User $actor, ?string $ip): void
    {
        $this->record($project, $actor, $ip, 'version_synchronized', 'Se sincronizó la versión', 'Se actualizaron precios, composiciones y porcentajes desde los catálogos vigentes.', [
            'version_number' => $project->numero_version,
        ]);
    }

    public function recordInputExcluded(Project $project, ?User $actor, ?string $ip, int $snapshotId): void
    {
        $this->record($project, $actor, $ip, 'version_input_excluded', 'Se excluyó un insumo de la versión', 'El insumo dejó de participar en los cálculos de esta versión.', [
            'version_number' => $project->numero_version,
            'snapshot_id' => $snapshotId,
        ]);
    }

    public function recordSignatureAccessUpdated(Project $project, ?User $actor, ?string $ip, array $changes): void
    {
        $this->record(
            $project,
            $actor,
            $ip,
            'signature_access_updated',
            'Se actualizó la configuración de firmantes',
            'Se modificó quién puede firmar documentos de la familia del proyecto.',
            $changes
        );
    }

    private function record(Project $project, ?User $actor, ?string $ip, string $action, string $title, ?string $detail, array $metadata = []): void
    {
        ProjectHistory::query()->create([
            'id_proyecto' => $project->id_proyecto,
            'id_usuario' => $actor?->id_usuario,
            'usuario_nombre' => $actor?->funcionario,
            'accion' => $action,
            'titulo' => $title,
            'detalle' => $detail,
            'metadata' => $metadata,
            'ip' => $ip,
            'fecha_hora' => Carbon::now(),
        ]);
    }
}

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
        return ProjectHistory::query()
            ->where('id_proyecto', $project->id_proyecto)
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

<?php

namespace App\Modules\Projects\Services;

use App\Http\Requests\Project\StoreProjectRequest;
use App\Models\Project;
use App\Models\ProjectItem;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProjectTemplateService
{
    public function __construct(private readonly ProjectHistoryService $projectHistoryService) {}

    public function list(array $filters): LengthAwarePaginator
    {
        $query = Project::query()
            ->with(['creator', 'requester'])
            ->where('es_plantilla', true);

        if (! empty($filters['search'])) {
            $search = Str::lower(trim((string) $filters['search']));

            $query->where(function ($query) use ($search): void {
                $query->whereRaw('LOWER(TRIM(nombre_proyecto)) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(TRIM(ubicacion)) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(TRIM(nombre_responsable)) LIKE ?', ["%{$search}%"]);
            });
        }

        if (! empty($filters['status'])) {
            $query->where('estado', strtoupper((string) $filters['status']));
        }

        return $query->orderBy('nombre_proyecto')
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();
    }

    public function createFromProject(Project $source, string $templateName, User $user, ?string $ip): Project
    {
        $this->ensureProjectNameIsUnique($templateName);

        $template = DB::transaction(function () use ($source, $templateName, $user): Project {
            $template = Project::query()->create([
                'nombre_proyecto' => $templateName,
                'fecha' => $source->fecha?->toDateString(),
                'ubicacion' => $source->ubicacion,
                'responsable' => $source->responsable,
                'solicitante' => $source->solicitante,
                'observaciones' => $source->observaciones,
                'aprobado' => $source->aprobado,
                'fecha_aprob' => $source->fecha_aprob?->toDateString(),
                'id_usuario' => $user->id_usuario,
                'estado' => 'AC',
                'es_plantilla' => true,
                'nombre_responsable' => $source->nombre_responsable,
                'latitud' => $source->latitud,
                'longitud' => $source->longitud,
                'precio' => 0,
                'distrito' => $source->distrito,
                'zona' => $source->zona,
                'otb' => $source->otb,
            ]);

            $this->copyActiveItems($source, $template, $user);
            $this->recalculateProjectPrice($template);

            return $template->refresh();
        });

        $itemsCopied = $this->activeItemsCount($template);
        $this->projectHistoryService->recordTemplateCreated($source, $user, $ip, $template, $itemsCopied);

        return $template->load(['creator', 'requester']);
    }

    public function createProjectFromTemplate(StoreProjectRequest $request, Project $template, User $user): Project
    {
        if (! (bool) $template->es_plantilla) {
            throw ValidationException::withMessages([
                'template' => ['El proyecto seleccionado no es una planilla.'],
            ]);
        }

        if (strtoupper((string) $template->estado) !== 'AC') {
            throw ValidationException::withMessages([
                'template' => ['La planilla seleccionada no está activa.'],
            ]);
        }

        $this->ensureProjectNameIsUnique($request->string('nombre_proyecto')->toString());

        $project = DB::transaction(function () use ($request, $template, $user): Project {
            $responsable = User::query()->find((int) $request->integer('responsable'));

            $project = Project::query()->create([
                'nombre_proyecto' => $request->string('nombre_proyecto')->toString(),
                'fecha' => $request->date('fecha')->toDateString(),
                'ubicacion' => $request->string('ubicacion')->toString(),
                'responsable' => (string) $request->integer('responsable'),
                'solicitante' => (int) $request->integer('solicitante'),
                'observaciones' => $request->filled('observaciones') ? $request->string('observaciones')->toString() : null,
                'aprobado' => strtoupper($request->string('aprobado')->toString()),
                'fecha_aprob' => $request->filled('fecha_aprob') ? $request->date('fecha_aprob')->toDateString() : null,
                'id_usuario' => $user->id_usuario,
                'estado' => strtoupper($request->string('estado')->toString()),
                'es_plantilla' => false,
                'nombre_responsable' => $responsable?->funcionario,
                'latitud' => $request->filled('latitud') ? $request->string('latitud')->toString() : null,
                'longitud' => $request->filled('longitud') ? $request->string('longitud')->toString() : null,
                'precio' => 0,
                'distrito' => $request->filled('distrito') ? $request->string('distrito')->toString() : null,
                'zona' => $request->filled('zona') ? $request->string('zona')->toString() : null,
                'otb' => $request->filled('otb') ? $request->string('otb')->toString() : null,
                'numero_version' => 1,
                'es_version_actual' => true,
                'fecha_version' => now(),
            ]);
            $project->update(['id_proyecto_raiz' => $project->id_proyecto]);

            $this->copyActiveItems($template, $project, $user);
            $this->recalculateProjectPrice($project);

            return $project->refresh();
        });

        $itemsCopied = $this->activeItemsCount($project);
        $this->projectHistoryService->recordCreatedFromTemplate($project, $user, $request->ip(), $template, $itemsCopied);

        return $project->load(['creator', 'requester']);
    }

    private function copyActiveItems(Project $source, Project $target, User $user): void
    {
        ProjectItem::query()
            ->where('id_proyecto', $source->id_proyecto)
            ->where('estado', 'AC')
            ->whereNotNull('id_item')
            ->orderBy('id_proyecto_item')
            ->get()
            ->each(function (ProjectItem $item) use ($target, $user): void {
                ProjectItem::query()->create([
                    'id_proyecto' => $target->id_proyecto,
                    'id_item' => $item->id_item,
                    'id_modulo' => $item->id_modulo,
                    'estado' => 'AC',
                    'cantidad' => $item->cantidad,
                    'fecha' => now()->toDateString(),
                    'precio' => $item->precio,
                    'id_usuario' => $user->id_usuario,
                    'prioridad' => $item->prioridad,
                ]);
            });
    }

    private function recalculateProjectPrice(Project $project): void
    {
        $total = ProjectItem::query()
            ->where('id_proyecto', $project->id_proyecto)
            ->where('estado', 'AC')
            ->whereNotNull('id_item')
            ->get()
            ->sum(fn (ProjectItem $item): float => ((float) $item->precio) * ((float) $item->cantidad));

        $project->update(['precio' => round((float) $total, 4)]);
    }

    private function activeItemsCount(Project $project): int
    {
        return ProjectItem::query()
            ->where('id_proyecto', $project->id_proyecto)
            ->where('estado', 'AC')
            ->whereNotNull('id_item')
            ->count();
    }

    private function ensureProjectNameIsUnique(string $projectName): void
    {
        $exists = Project::query()
            ->whereRaw('UPPER(TRIM(nombre_proyecto)) = ?', [strtoupper(trim($projectName))])
            ->where(function ($query): void {
                $query->where('es_plantilla', true)
                    ->orWhere('es_version_actual', true)
                    ->orWhereNull('es_version_actual');
            })
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'nombre_proyecto' => ['Ya existe un proyecto o planilla con el mismo nombre.'],
            ]);
        }
    }
}

<?php

namespace App\Services\Projects;

use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Models\Project;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ProjectCrudService
{
    public function create(StoreProjectRequest $request, User $user): Project
    {
        $this->ensureProjectNameIsUnique($request->string('nombre_proyecto')->toString());

        $responsable = User::query()->find((int) $request->integer('responsable'));

        return Project::query()->create([
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
            'nombre_responsable' => $responsable?->funcionario,
            'latitud' => $request->filled('latitud') ? $request->string('latitud')->toString() : null,
            'longitud' => $request->filled('longitud') ? $request->string('longitud')->toString() : null,
            'precio' => 0,
            'distrito' => $request->filled('distrito') ? $request->string('distrito')->toString() : null,
            'zona' => $request->filled('zona') ? $request->string('zona')->toString() : null,
            'otb' => $request->filled('otb') ? $request->string('otb')->toString() : null,
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project): Project
    {
        $this->ensureProjectNameIsUnique($request->string('nombre_proyecto')->toString(), $project->id_proyecto);

        $responsable = User::query()->find((int) $request->integer('responsable'));

        $project->update([
            'nombre_proyecto' => $request->string('nombre_proyecto')->toString(),
            'fecha' => $request->date('fecha')->toDateString(),
            'ubicacion' => $request->string('ubicacion')->toString(),
            'responsable' => (string) $request->integer('responsable'),
            'solicitante' => (int) $request->integer('solicitante'),
            'observaciones' => $request->string('observaciones')->toString(),
            'aprobado' => strtoupper($request->string('aprobado')->toString()),
            'fecha_aprob' => $request->filled('fecha_aprob') ? $request->date('fecha_aprob')->toDateString() : null,
            'estado' => strtoupper($request->string('estado')->toString()),
            'nombre_responsable' => $responsable?->funcionario,
            'latitud' => $request->filled('latitud') ? $request->string('latitud')->toString() : $project->latitud,
            'longitud' => $request->filled('longitud') ? $request->string('longitud')->toString() : $project->longitud,
            'distrito' => $request->filled('distrito') ? $request->string('distrito')->toString() : null,
            'zona' => $request->filled('zona') ? $request->string('zona')->toString() : null,
            'otb' => $request->filled('otb') ? $request->string('otb')->toString() : null,
        ]);

        return $project->refresh();
    }

    private function ensureProjectNameIsUnique(string $projectName, ?int $ignoredProjectId = null): void
    {
        $query = Project::query()
            ->whereRaw('UPPER(TRIM(nombre_proyecto)) = ?', [strtoupper(trim($projectName))]);

        if ($ignoredProjectId !== null) {
            $query->where('id_proyecto', '!=', $ignoredProjectId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'nombre_proyecto' => ['Ya existe un proyecto con el mismo nombre.'],
            ]);
        }
    }
}

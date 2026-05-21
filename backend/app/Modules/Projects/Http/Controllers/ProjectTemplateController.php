<?php

namespace App\Modules\Projects\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\IndexProjectRequest;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\StoreProjectTemplateRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Http\Resources\Project\ProjectResource;
use App\Models\Project;
use App\Models\User;
use App\Modules\Projects\Services\ProjectPermissionService;
use App\Modules\Projects\Services\ProjectCrudService;
use App\Modules\Projects\Services\ProjectTemplateService;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectTemplateController extends Controller
{
    public function __construct(
        private readonly ProjectTemplateService $projectTemplateService,
        private readonly ProjectCrudService $projectCrudService,
        private readonly ProjectPermissionService $projectPermissionService,
        private readonly AuditService $auditService,
    ) {}

    public function index(IndexProjectRequest $request): JsonResponse
    {
        if ($response = $this->denyIfMissingAnyPermission($request->user(), ['can_create', 'can_manage_templates'], 'No tiene permisos para ver planillas de proyecto.')) {
            return $response;
        }

        $templates = $this->projectTemplateService->list($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Planillas de proyecto obtenidas correctamente.',
            'data' => [
                'items' => ProjectResource::collection($templates->getCollection())->resolve(),
                'meta' => [
                    'current_page' => $templates->currentPage(),
                    'per_page' => $templates->perPage(),
                    'total' => $templates->total(),
                ],
            ],
        ]);
    }

    public function store(StoreProjectTemplateRequest $request, Project $project): JsonResponse
    {
        if ($response = $this->denyIfMissingAnyPermission($request->user(), ['can_create', 'can_edit'], 'No tiene permisos para crear planillas de proyecto.')) {
            return $response;
        }

        $template = $this->projectTemplateService->createFromProject($project, $request->string('nombre_proyecto')->toString(), $request->user(), $request->ip());
        $this->auditService->record($request->user(), $request->ip(), 'PROYECTOS: se creo la planilla '.$template->nombre_proyecto.' desde el proyecto '.$project->nombre_proyecto);

        return response()->json([
            'success' => true,
            'message' => 'Planilla creada correctamente.',
            'data' => [
                'template' => ProjectResource::make($template)->resolve(),
            ],
        ], 201);
    }

    public function show(Request $request, Project $template): JsonResponse
    {
        if ($response = $this->denyIfMissingAnyPermission($request->user(), ['can_create', 'can_manage_templates'], 'No tiene permisos para ver planillas de proyecto.')) {
            return $response;
        }

        if (! (bool) $template->es_plantilla) {
            return response()->json([
                'success' => false,
                'message' => 'La planilla solicitada no existe.',
                'errors' => null,
            ], 404);
        }

        $template->load(['creator', 'requester']);

        return response()->json([
            'success' => true,
            'message' => 'Planilla de proyecto obtenida correctamente.',
            'data' => [
                'template' => ProjectResource::make($template)->resolve(),
            ],
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        if ($response = $this->denyIfMissingPermission($request->user(), 'can_manage_templates', 'No tiene permisos para administrar planillas de proyecto.')) {
            return $response;
        }

        if (! (bool) $project->es_plantilla) {
            return response()->json([
                'success' => false,
                'message' => 'La planilla solicitada no existe.',
                'errors' => null,
            ], 404);
        }

        $template = $this->projectCrudService->update($request, $project, $request->user());
        $template->load(['creator', 'requester']);
        $this->auditService->record($request->user(), $request->ip(), 'PROYECTOS: se actualizo la planilla '.$template->nombre_proyecto);

        return response()->json([
            'success' => true,
            'message' => 'Planilla actualizada correctamente.',
            'data' => [
                'template' => ProjectResource::make($template)->resolve(),
            ],
        ]);
    }

    public function createProject(StoreProjectRequest $request, Project $template): JsonResponse
    {
        if ($response = $this->denyIfMissingPermission($request->user(), 'can_create', 'No tiene permisos para crear proyectos.')) {
            return $response;
        }

        $project = $this->projectTemplateService->createProjectFromTemplate($request, $template, $request->user());
        $this->auditService->record($request->user(), $request->ip(), 'PROYECTOS: se creo el proyecto '.$project->nombre_proyecto.' desde la planilla '.$template->nombre_proyecto);

        return response()->json([
            'success' => true,
            'message' => 'Proyecto creado desde planilla correctamente.',
            'data' => [
                'project' => ProjectResource::make($project)->resolve(),
            ],
        ], 201);
    }

    private function denyIfMissingPermission(?User $user, string $permission, string $message): ?JsonResponse
    {
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado.',
                'errors' => null,
            ], 401);
        }

        $permissions = $this->projectPermissionService->resolve($user);

        if (($permissions[$permission] ?? false) !== true) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene permisos para acceder a este recurso.',
                'errors' => [
                    'authorization' => [$message],
                ],
            ], 403);
        }

        return null;
    }

    private function denyIfMissingAnyPermission(?User $user, array $permissionsToCheck, string $message): ?JsonResponse
    {
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado.',
                'errors' => null,
            ], 401);
        }

        $permissions = $this->projectPermissionService->resolve($user);

        foreach ($permissionsToCheck as $permission) {
            if (($permissions[$permission] ?? false) === true) {
                return null;
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'No tiene permisos para acceder a este recurso.',
            'errors' => [
                'authorization' => [$message],
            ],
        ], 403);
    }
}

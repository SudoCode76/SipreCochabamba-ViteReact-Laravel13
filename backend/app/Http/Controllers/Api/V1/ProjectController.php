<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\BudgetRecalculationRequest;
use App\Http\Requests\Project\IncidencePriceRequest;
use App\Http\Requests\Project\IndexProjectRequest;
use App\Http\Requests\Project\ProjectFormatRequest;
use App\Http\Requests\Project\ShowProjectItemsRequest;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\SyncProjectItemsRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Models\Item;
use App\Models\Project;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Projects\ProjectBudgetService;
use App\Services\Projects\ProjectContextService;
use App\Services\Projects\ProjectCrudService;
use App\Services\Projects\ProjectItemService;
use App\Services\Projects\ProjectListService;
use App\Services\Projects\ProjectPermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function __construct(
        private readonly ProjectContextService $projectContextService,
        private readonly ProjectListService $projectListService,
        private readonly ProjectCrudService $projectCrudService,
        private readonly ProjectItemService $projectItemService,
        private readonly ProjectBudgetService $projectBudgetService,
        private readonly ProjectPermissionService $projectPermissionService,
        private readonly AuditService $auditService,
    ) {}

    public function context(Request $request): JsonResponse
    {
        if ($response = $this->denyIfMissingAnyPermission($request->user(), ['can_view', 'can_create'], 'No tiene permisos para acceder al contexto de proyectos.')) {
            return $response;
        }

        return response()->json([
            'success' => true,
            'message' => 'Contexto de proyectos obtenido correctamente.',
            'data' => $this->projectContextService->execute($request->user()),
        ]);
    }

    public function index(IndexProjectRequest $request): JsonResponse
    {
        if ($response = $this->denyIfMissingPermission($request->user(), 'can_view', 'No tiene permisos para listar proyectos.')) {
            return $response;
        }

        $projects = $this->projectListService->execute($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Proyectos obtenidos correctamente.',
            'data' => [
                'items' => $projects->getCollection()->map(fn (Project $project): array => $this->serializeProject($project))->values(),
                'meta' => [
                    'current_page' => $projects->currentPage(),
                    'per_page' => $projects->perPage(),
                    'total' => $projects->total(),
                ],
            ],
        ]);
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        if ($response = $this->denyIfMissingPermission($request->user(), 'can_create', 'No tiene permisos para crear proyectos.')) {
            return $response;
        }

        $project = $this->projectCrudService->create($request, $request->user());
        $project->load(['creator', 'requester']);
        $this->auditService->record($request->user(), $request->ip(), 'PROYECTOS: se creo el proyecto '.$project->nombre_proyecto);

        return response()->json([
            'success' => true,
            'message' => 'Proyecto creado correctamente.',
            'data' => [
                'project' => $this->serializeProject($project, true),
            ],
        ], 201);
    }

    public function show(Project $project): JsonResponse
    {
        if ($response = $this->denyIfMissingPermission(request()->user(), 'can_view', 'No tiene permisos para ver proyectos.')) {
            return $response;
        }

        $project->load(['creator', 'requester']);

        return response()->json([
            'success' => true,
            'message' => 'Proyecto obtenido correctamente.',
            'data' => [
                'project' => $this->serializeProject($project, true),
            ],
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        if ($response = $this->denyIfMissingPermission($request->user(), 'can_edit', 'No tiene permisos para editar proyectos.')) {
            return $response;
        }

        $project = $this->projectCrudService->update($request, $project);
        $project->load(['creator', 'requester']);
        $this->auditService->record($request->user(), $request->ip(), 'PROYECTOS: se actualizo el proyecto '.$project->nombre_proyecto);

        return response()->json([
            'success' => true,
            'message' => 'Proyecto actualizado correctamente.',
            'data' => [
                'project' => $this->serializeProject($project, true),
            ],
        ]);
    }

    public function syncItems(SyncProjectItemsRequest $request, Project $project): JsonResponse
    {
        if ($response = $this->denyIfMissingPermission($request->user(), 'can_sync_items', 'No tiene permisos para sincronizar items del proyecto.')) {
            return $response;
        }

        $project = $this->projectItemService->sync($project, $request->validated('items'), $request->user());
        $this->auditService->record($request->user(), $request->ip(), 'PROYECTOS: se sincronizaron los items del proyecto '.$project->nombre_proyecto);

        return response()->json([
            'success' => true,
            'message' => 'Items del proyecto sincronizados correctamente.',
            'data' => [
                'project' => $this->serializeProject($project, true),
            ],
        ]);
    }

    public function items(ShowProjectItemsRequest $request, Project $project): JsonResponse
    {
        if ($response = $this->denyIfMissingPermission($request->user(), 'can_view', 'No tiene permisos para listar items del proyecto.')) {
            return $response;
        }

        $format = $request->validated('format', 'PCA');

        return response()->json([
            'success' => true,
            'message' => 'Items del proyecto obtenidos correctamente.',
            'data' => [
                'items' => $this->projectItemService->listProjectItems($project, $format),
            ],
        ]);
    }

    public function incidencePrice(IncidencePriceRequest $request, Item $item): JsonResponse
    {
        if ($response = $this->denyIfMissingPermission($request->user(), 'can_view_reports', 'No tiene permisos para consultar precios por incidencia.')) {
            return $response;
        }

        return response()->json([
            'success' => true,
            'message' => 'Precio por incidencia del item obtenido correctamente.',
            'data' => [
                'item' => $this->projectItemService->incidenceItemDetail($item, $request->validated('format')),
            ],
        ]);
    }

    public function budgetRecalculation(BudgetRecalculationRequest $request, Project $project): JsonResponse
    {
        if ($response = $this->denyIfMissingPermission($request->user(), 'can_recalculate_budget', 'No tiene permisos para recalcular el presupuesto del proyecto.')) {
            return $response;
        }

        $data = $this->projectBudgetService->budgetRecalculation($project, $request->date('fecha'));
        $this->auditService->record($request->user(), $request->ip(), 'PROYECTOS: se recalculo el presupuesto del proyecto '.$project->nombre_proyecto);

        return response()->json([
            'success' => true,
            'message' => 'Presupuesto del proyecto recalculado correctamente.',
            'data' => $data,
        ]);
    }

    public function budgetByGroup(Project $project): JsonResponse
    {
        if ($response = $this->denyIfMissingPermission(request()->user(), 'can_view_reports', 'No tiene permisos para consultar presupuesto por rubros.')) {
            return $response;
        }

        return response()->json([
            'success' => true,
            'message' => 'Presupuesto por rubros obtenido correctamente.',
            'data' => $this->projectBudgetService->budgetByGroup($project),
        ]);
    }

    public function incidenceSummary(ProjectFormatRequest $request, Project $project): JsonResponse
    {
        if ($response = $this->denyIfMissingPermission($request->user(), 'can_view_reports', 'No tiene permisos para consultar el resumen de incidencia.')) {
            return $response;
        }

        return response()->json([
            'success' => true,
            'message' => 'Resumen de incidencia obtenido correctamente.',
            'data' => $this->projectBudgetService->incidenceSummary($project, $request->validated('format')),
        ]);
    }

    public function breakdownCalculation(ProjectFormatRequest $request, Project $project): JsonResponse
    {
        if ($response = $this->denyIfMissingPermission($request->user(), 'can_view_reports', 'No tiene permisos para calcular el desglose del proyecto.')) {
            return $response;
        }

        return response()->json([
            'success' => true,
            'message' => 'Desglose del proyecto calculado correctamente.',
            'data' => $this->projectBudgetService->breakdownCalculation($project, $request->validated('format')),
        ]);
    }

    public function unitPrices(ProjectFormatRequest $request, Project $project): JsonResponse
    {
        if ($response = $this->denyIfMissingPermission($request->user(), 'can_view_reports', 'No tiene permisos para consultar precios unitarios del proyecto.')) {
            return $response;
        }

        return response()->json([
            'success' => true,
            'message' => 'Precios unitarios del proyecto obtenidos correctamente.',
            'data' => [
                'items' => $this->projectBudgetService->unitPrices($project, $request->validated('format')),
            ],
        ]);
    }

    private function serializeProject(Project $project, bool $full = false): array
    {
        $data = [
            'id_proyecto' => $project->id_proyecto,
            'nombre_proyecto' => $project->nombre_proyecto,
            'ubicacion' => $project->ubicacion,
            'fecha' => $project->fecha?->toDateString(),
            'responsable' => $project->responsable,
            'solicitante' => $project->solicitante,
            'solicitante_nombre' => $project->requester?->funcionario,
            'observaciones' => $project->observaciones,
            'aprobado' => $project->aprobado,
            'estado' => $project->estado,
            'id_usuario' => $project->id_usuario,
        ];

        if (! $full) {
            return $data;
        }

        return array_merge($data, [
            'fecha_aprob' => $project->fecha_aprob?->toDateString(),
            'nombre_responsable' => $project->nombre_responsable,
            'latitud' => $project->latitud,
            'longitud' => $project->longitud,
            'precio' => $project->precio,
            'distrito' => $project->distrito,
            'zona' => $project->zona,
            'otb' => $project->otb,
        ]);
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

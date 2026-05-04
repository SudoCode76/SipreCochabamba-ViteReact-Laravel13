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
    ) {}

    public function context(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Contexto de proyectos obtenido correctamente.',
            'data' => $this->projectContextService->execute($request->user()),
        ]);
    }

    public function index(IndexProjectRequest $request): JsonResponse
    {
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
        $project = $this->projectCrudService->create($request, $request->user());
        $project->load(['creator', 'requester']);

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
        $project = $this->projectCrudService->update($request, $project);
        $project->load(['creator', 'requester']);

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
        $project = $this->projectItemService->sync($project, $request->validated('items'), $request->user());

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
        return response()->json([
            'success' => true,
            'message' => 'Presupuesto del proyecto recalculado correctamente.',
            'data' => $this->projectBudgetService->budgetRecalculation($project, $request->date('fecha')),
        ]);
    }

    public function budgetByGroup(Project $project): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Presupuesto por rubros obtenido correctamente.',
            'data' => $this->projectBudgetService->budgetByGroup($project),
        ]);
    }

    public function incidenceSummary(ProjectFormatRequest $request, Project $project): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Resumen de incidencia obtenido correctamente.',
            'data' => $this->projectBudgetService->incidenceSummary($project, $request->validated('format')),
        ]);
    }

    public function breakdownCalculation(ProjectFormatRequest $request, Project $project): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Desglose del proyecto calculado correctamente.',
            'data' => $this->projectBudgetService->breakdownCalculation($project, $request->validated('format')),
        ]);
    }

    public function unitPrices(ProjectFormatRequest $request, Project $project): JsonResponse
    {
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
}

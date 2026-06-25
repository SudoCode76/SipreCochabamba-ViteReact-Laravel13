<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\BudgetRecalculationRequest;
use App\Http\Requests\Project\IncidencePriceRequest;
use App\Http\Requests\Project\IndexProjectHistoryRequest;
use App\Http\Requests\Project\IndexProjectRequest;
use App\Http\Requests\Project\ProjectFormatRequest;
use App\Http\Requests\Project\ProjectInputBreakdownRequest;
use App\Http\Requests\Project\ShowProjectItemsRequest;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\SyncProjectItemsRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Http\Resources\Project\ProjectHistoryResource;
use App\Models\Item;
use App\Models\Project;
use App\Models\ProjectItemInputSnapshot;
use App\Models\User;
use App\Services\AuditService;
use App\Modules\Projects\Services\ProjectBudgetService;
use App\Modules\Projects\Services\ProjectBudgetXlsxService;
use App\Modules\Projects\Services\ProjectBudgetByGroupPdfService;
use App\Modules\Projects\Services\ProjectContextService;
use App\Modules\Projects\Services\ProjectCrudService;
use App\Modules\Projects\Services\ProjectIncidenceSummaryPdfService;
use App\Modules\Projects\Services\ProjectInputBreakdownPdfService;
use App\Modules\Projects\Services\ProjectInputsGroupedReportPdfService;
use App\Modules\Projects\Services\ProjectInputsReportPdfService;
use App\Modules\Projects\Services\ProjectGeneralBudgetPdfService;
use App\Modules\Projects\Services\ProjectHistoryService;
use App\Modules\Projects\Services\ProjectItemInputSnapshotService;
use App\Modules\Projects\Services\ProjectItemService;
use App\Modules\Projects\Services\ProjectListService;
use App\Modules\Projects\Services\ProjectMapService;
use App\Modules\Projects\Services\ProjectPermissionService;
use App\Modules\Projects\Services\ProjectSpecificationsPdfMergeService;
use App\Modules\Projects\Services\ProjectUnitPricesPdfService;
use App\Modules\Projects\Services\ProjectVersionComparisonService;
use App\Modules\Projects\Services\ProjectVersionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProjectController extends Controller
{
    public function __construct(
        private readonly ProjectContextService $projectContextService,
        private readonly ProjectListService $projectListService,
        private readonly ProjectMapService $projectMapService,
        private readonly ProjectCrudService $projectCrudService,
        private readonly ProjectItemService $projectItemService,
        private readonly ProjectBudgetService $projectBudgetService,
        private readonly ProjectBudgetXlsxService $projectBudgetXlsxService,
        private readonly ProjectBudgetByGroupPdfService $projectBudgetByGroupPdfService,
        private readonly ProjectIncidenceSummaryPdfService $projectIncidenceSummaryPdfService,
        private readonly ProjectGeneralBudgetPdfService $projectGeneralBudgetPdfService,
        private readonly ProjectInputBreakdownPdfService $projectInputBreakdownPdfService,
        private readonly ProjectInputsReportPdfService $projectInputsReportPdfService,
        private readonly ProjectInputsGroupedReportPdfService $projectInputsGroupedReportPdfService,
        private readonly ProjectUnitPricesPdfService $projectUnitPricesPdfService,
        private readonly ProjectSpecificationsPdfMergeService $projectSpecificationsPdfMergeService,
        private readonly ProjectPermissionService $projectPermissionService,
        private readonly ProjectHistoryService $projectHistoryService,
        private readonly ProjectItemInputSnapshotService $snapshotService,
        private readonly AuditService $auditService,
        private readonly ProjectVersionService $projectVersionService,
        private readonly ProjectVersionComparisonService $projectVersionComparisonService,
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

    public function map(Request $request): JsonResponse
    {
        if ($response = $this->denyIfMissingAnyPermission($request->user(), ['can_create', 'can_view'], 'No tiene permisos para consultar proyectos en el mapa.')) {
            return $response;
        }

        $filters = $request->validate([
            'bbox' => ['nullable', 'string'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'radius' => ['nullable', 'numeric', 'min:1', 'max:5000'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Proyectos del mapa obtenidos correctamente.',
            'data' => $this->projectMapService->execute($filters),
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

        if (! $project->isFrozen()) {
            $this->snapshotService->ensureForProject($project);
            $project->refresh();
        }

        $project->loadCount('versions');
        $project->load(['creator', 'requester']);

        return response()->json([
            'success' => true,
            'message' => 'Proyecto obtenido correctamente.',
            'data' => [
                'project' => $this->serializeProject($project, true),
            ],
        ]);
    }

    public function versions(Project $project): JsonResponse
    {
        if ($response = $this->denyIfMissingPermission(request()->user(), 'can_view', 'No tiene permisos para ver versiones del proyecto.')) {
            return $response;
        }

        return response()->json([
            'success' => true,
            'message' => 'Versiones del proyecto obtenidas correctamente.',
            'data' => [
                'items' => $this->projectVersionService->versions($project)
                    ->map(fn (Project $version): array => $this->serializeProject($version, true))
                    ->values(),
            ],
        ]);
    }

    public function createUpdatedVersion(Request $request, Project $project): JsonResponse
    {
        if ($response = $this->denyIfMissingPermission($request->user(), 'can_edit', 'No tiene permisos para crear versiones del proyecto.')) {
            return $response;
        }

        $version = $this->projectVersionService->createUpdatedVersion($project, $request->user(), $request->ip());

        return response()->json([
            'success' => true,
            'message' => 'Nueva versión ACTUALIZADA creada correctamente.',
            'data' => ['project' => $this->serializeProject($version, true)],
        ], 201);
    }

    public function compareVersions(Request $request, Project $project): JsonResponse
    {
        if ($response = $this->denyIfMissingPermission($request->user(), 'can_view', 'No tiene permisos para comparar versiones del proyecto.')) {
            return $response;
        }

        $validated = $request->validate([
            'base' => ['required', 'integer', 'exists:proyecto,id_proyecto'],
            'target' => ['required', 'integer', 'exists:proyecto,id_proyecto'],
            'format' => ['nullable', 'string', 'in:PCA,PC_FPS,PC_UPRE,PC_FNDR,PC_OBRAS'],
        ]);

        $base = Project::query()->findOrFail((int) $validated['base']);
        $target = Project::query()->findOrFail((int) $validated['target']);

        return response()->json([
            'success' => true,
            'message' => 'Comparación de versiones obtenida correctamente.',
            'data' => $this->projectVersionComparisonService->compare($project, $base, $target),
        ]);
    }

    public function finalizeVersion(Request $request, Project $project): JsonResponse
    {
        if ($response = $this->denyIfMissingPermission($request->user(), 'can_edit', 'No tiene permisos para finalizar versiones del proyecto.')) {
            return $response;
        }

        $version = $this->projectVersionService->finalize($project, $request->user(), $request->ip());

        return response()->json([
            'success' => true,
            'message' => 'Versión finalizada y congelada correctamente.',
            'data' => ['project' => $this->serializeProject($version, true)],
        ]);
    }

    public function synchronizeVersion(Request $request, Project $project): JsonResponse
    {
        if ($response = $this->denyIfMissingPermission($request->user(), 'can_edit', 'No tiene permisos para sincronizar versiones del proyecto.')) {
            return $response;
        }

        $version = $this->projectVersionService->synchronize($project, $request->user(), $request->ip());

        return response()->json([
            'success' => true,
            'message' => 'Versión sincronizada correctamente.',
            'data' => ['project' => $this->serializeProject($version, true)],
        ]);
    }

    public function excludeVersionInput(Request $request, Project $project, ProjectItemInputSnapshot $snapshot): JsonResponse
    {
        if ($response = $this->denyIfMissingPermission($request->user(), 'can_edit', 'No tiene permisos para modificar la versión del proyecto.')) {
            return $response;
        }

        $snapshot = $this->snapshotService->exclude($project, $snapshot, $request->user());
        $this->projectHistoryService->recordInputExcluded($project, $request->user(), $request->ip(), $snapshot->id_snapshot);

        return response()->json([
            'success' => true,
            'message' => 'Insumo excluido de la versión correctamente.',
            'data' => ['snapshot' => $snapshot],
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        if ($response = $this->denyIfMissingPermission($request->user(), 'can_edit', 'No tiene permisos para editar proyectos.')) {
            return $response;
        }

        $project = $this->projectCrudService->update($request, $project, $request->user());
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

        $project = $this->projectItemService->sync($project, $request->validated('items'), $request->user(), $request->ip());
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

    public function reportWarnings(Request $request, Project $project): JsonResponse
    {
        if ($response = $this->denyIfMissingAnyPermission($request->user(), [
            'can_view_budget_by_group',
            'can_recalculate_budget',
            'can_view_incidence_summary',
            'can_view_general_budget',
            'can_view_input_breakdown',
            'can_view_inputs_report',
            'can_view_unit_prices',
        ], 'No tiene permisos para consultar advertencias de reportes del proyecto.')) {
            return $response;
        }

        return response()->json([
            'success' => true,
            'message' => 'Advertencias del proyecto obtenidas correctamente.',
            'data' => $this->snapshotService->warnings($project),
        ]);
    }

    public function history(IndexProjectHistoryRequest $request, Project $project): JsonResponse
    {
        if ($response = $this->denyIfMissingPermission($request->user(), 'can_view', 'No tiene permisos para ver el historial del proyecto.')) {
            return $response;
        }

        $history = $this->projectHistoryService->list($project, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Historial del proyecto obtenido correctamente.',
            'data' => [
                'items' => ProjectHistoryResource::collection($history->getCollection())->resolve(),
                'meta' => [
                    'current_page' => $history->currentPage(),
                    'per_page' => $history->perPage(),
                    'total' => $history->total(),
                ],
            ],
        ]);
    }

    public function incidencePrice(IncidencePriceRequest $request, Item $item): JsonResponse
    {
        if ($response = $this->denyIfMissingPermission($request->user(), 'can_view_incidence_summary', 'No tiene permisos para consultar precios por incidencia.')) {
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
        $this->projectHistoryService->recordBudgetRecalculated($project, $request->user(), $request->ip(), $request->date('fecha')->toDateString(), $data);
        $this->auditService->record($request->user(), $request->ip(), 'PROYECTOS: se recalculo el presupuesto del proyecto '.$project->nombre_proyecto);

        return response()->json([
            'success' => true,
            'message' => 'Presupuesto del proyecto recalculado correctamente.',
            'data' => $data,
        ]);
    }

    public function budgetRecalculationPdf(BudgetRecalculationRequest $request, Project $project): \Illuminate\Http\Response
    {
        if ($response = $this->denyIfMissingPermission($request->user(), 'can_recalculate_budget', 'No tiene permisos para recalcular el presupuesto del proyecto.')) {
            abort(403, $response->getData()->message ?? 'No tiene permisos para recalcular el presupuesto del proyecto.');
        }

        $data = $this->projectBudgetService->budgetRecalculation($project, $request->date('fecha'));
        $this->projectHistoryService->recordBudgetRecalculated($project, $request->user(), $request->ip(), $request->date('fecha')->toDateString(), $data);
        $this->auditService->record($request->user(), $request->ip(), 'PROYECTOS: se recalculo el presupuesto del proyecto '.$project->nombre_proyecto);

        return $this->projectBudgetByGroupPdfService->streamHistorical($project, $data);
    }

    public function budgetRecalculationXlsx(BudgetRecalculationRequest $request, Project $project): \Illuminate\Http\Response
    {
        if ($response = $this->denyIfMissingPermission($request->user(), 'can_recalculate_budget', 'No tiene permisos para recalcular el presupuesto del proyecto.')) {
            abort(403, $response->getData()->message ?? 'No tiene permisos para recalcular el presupuesto del proyecto.');
        }

        $this->projectHistoryService->recordBudgetRecalculated($project, $request->user(), $request->ip(), $request->date('fecha')->toDateString(), [
            'format' => 'xlsx',
        ]);
        $this->auditService->record($request->user(), $request->ip(), 'PROYECTOS: se exporto XLSX del presupuesto recalculado del proyecto '.$project->nombre_proyecto);

        return $this->projectBudgetXlsxService->budgetRecalculation($project, $request->date('fecha'));
    }

    public function budgetByGroup(Project $project): JsonResponse
    {
        if ($response = $this->denyIfMissingPermission(request()->user(), 'can_view_budget_by_group', 'No tiene permisos para consultar presupuesto por rubros.')) {
            return $response;
        }

        return response()->json([
            'success' => true,
            'message' => 'Presupuesto por rubros obtenido correctamente.',
            'data' => $this->projectBudgetService->budgetByGroup($project),
        ]);
    }

    public function budgetByGroupPdf(Project $project): \Illuminate\Http\Response
    {
        if ($response = $this->denyIfMissingPermission(request()->user(), 'can_view_budget_by_group', 'No tiene permisos para consultar presupuesto por rubros.')) {
            abort(403, $response->getData()->message ?? 'No tiene permisos para consultar presupuesto por rubros.');
        }

        $this->projectHistoryService->recordPdfGenerated($project, request()->user(), request()->ip(), 'Presupuesto por rubros');

        return $this->projectBudgetByGroupPdfService->stream($project);
    }

    public function budgetByGroupXlsx(Project $project): \Illuminate\Http\Response
    {
        if ($response = $this->denyIfMissingPermission(request()->user(), 'can_view_budget_by_group', 'No tiene permisos para consultar presupuesto por rubros.')) {
            abort(403, $response->getData()->message ?? 'No tiene permisos para consultar presupuesto por rubros.');
        }

        $this->projectHistoryService->recordPdfGenerated($project, request()->user(), request()->ip(), 'Presupuesto por rubros XLSX');

        return $this->projectBudgetXlsxService->budgetByGroup($project);
    }

    public function incidenceSummary(ProjectFormatRequest $request, Project $project): JsonResponse
    {
        if ($response = $this->denyIfMissingPermission($request->user(), 'can_view_incidence_summary', 'No tiene permisos para consultar el resumen de incidencia.')) {
            return $response;
        }

        return response()->json([
            'success' => true,
            'message' => 'Resumen de incidencia obtenido correctamente.',
            'data' => $this->projectBudgetService->incidenceSummary($project, $request->validated('format')),
        ]);
    }

    public function incidenceSummaryPdf(ProjectFormatRequest $request, Project $project): \Illuminate\Http\Response
    {
        if ($response = $this->denyIfMissingPermission($request->user(), 'can_view_incidence_summary', 'No tiene permisos para consultar el resumen de incidencia.')) {
            abort(403, $response->getData()->message ?? 'No tiene permisos para consultar el resumen de incidencia.');
        }

        $this->projectHistoryService->recordPdfGenerated($project, $request->user(), $request->ip(), 'Resumen por incidencia', [
            'format' => $request->validated('format'),
        ]);

        return $this->projectIncidenceSummaryPdfService->stream($project, $request->validated('format'));
    }

    public function incidenceSummaryXlsx(ProjectFormatRequest $request, Project $project): \Illuminate\Http\Response
    {
        if ($response = $this->denyIfMissingPermission($request->user(), 'can_view_incidence_summary', 'No tiene permisos para consultar el resumen de incidencia.')) {
            abort(403, $response->getData()->message ?? 'No tiene permisos para consultar el resumen de incidencia.');
        }

        $this->projectHistoryService->recordPdfGenerated($project, $request->user(), $request->ip(), 'Resumen por incidencia XLSX', [
            'format' => $request->validated('format'),
            'export' => 'xlsx',
        ]);

        return $this->projectBudgetXlsxService->incidenceSummary($project, $request->validated('format'));
    }

    public function generalBudgetPdf(ProjectFormatRequest $request, Project $project): \Illuminate\Http\Response
    {
        if ($response = $this->denyIfMissingPermission($request->user(), 'can_view_general_budget', 'No tiene permisos para consultar el presupuesto general.')) {
            abort(403, $response->getData()->message ?? 'No tiene permisos para consultar el presupuesto general.');
        }

        $this->projectHistoryService->recordPdfGenerated($project, $request->user(), $request->ip(), 'Presupuesto general', [
            'format' => $request->validated('format'),
        ]);

        return $this->projectGeneralBudgetPdfService->stream($project, $request->validated('format'));
    }

    public function generalBudgetXlsx(ProjectFormatRequest $request, Project $project): \Illuminate\Http\Response
    {
        if ($response = $this->denyIfMissingPermission($request->user(), 'can_view_general_budget', 'No tiene permisos para consultar el presupuesto general.')) {
            abort(403, $response->getData()->message ?? 'No tiene permisos para consultar el presupuesto general.');
        }

        $this->projectHistoryService->recordPdfGenerated($project, $request->user(), $request->ip(), 'Presupuesto general XLSX', [
            'format' => $request->validated('format'),
            'export' => 'xlsx',
        ]);

        return $this->projectBudgetXlsxService->generalBudget($project, $request->validated('format'));
    }

    public function inputBreakdownPdf(ProjectInputBreakdownRequest $request, Project $project): \Illuminate\Http\Response
    {
        if ($response = $this->denyIfMissingPermission($request->user(), 'can_view_input_breakdown', 'No tiene permisos para consultar el desglose de insumos del proyecto.')) {
            abort(403, $response->getData()->message ?? 'No tiene permisos para consultar el desglose de insumos del proyecto.');
        }

        $this->projectHistoryService->recordPdfGenerated($project, $request->user(), $request->ip(), 'Desglose de insumos del proyecto', [
            'type' => (int) $request->validated('type'),
        ]);

        return $this->projectInputBreakdownPdfService->stream($project, (int) $request->validated('type'));
    }

    public function inputBreakdownXlsx(ProjectInputBreakdownRequest $request, Project $project): \Illuminate\Http\Response
    {
        if ($response = $this->denyIfMissingPermission($request->user(), 'can_view_input_breakdown', 'No tiene permisos para consultar el desglose de insumos del proyecto.')) {
            abort(403, $response->getData()->message ?? 'No tiene permisos para consultar el desglose de insumos del proyecto.');
        }

        $this->projectHistoryService->recordPdfGenerated($project, $request->user(), $request->ip(), 'Desglose de insumos del proyecto XLSX', [
            'type' => (int) $request->validated('type'),
            'export' => 'xlsx',
        ]);

        return $this->projectBudgetXlsxService->inputBreakdown($project, (int) $request->validated('type'));
    }

    public function inputsReportPdf(Request $request, Project $project): \Illuminate\Http\Response
    {
        if ($response = $this->denyIfMissingPermission($request->user(), 'can_view_inputs_report', 'No tiene permisos para consultar el reporte de insumos del proyecto.')) {
            abort(403, $response->getData()->message ?? 'No tiene permisos para consultar el reporte de insumos del proyecto.');
        }

        $this->projectHistoryService->recordPdfGenerated($project, $request->user(), $request->ip(), 'Reporte consolidado de insumos');

        return $this->projectInputsReportPdfService->stream($project);
    }

    public function inputsReportXlsx(Request $request, Project $project): \Illuminate\Http\Response
    {
        if ($response = $this->denyIfMissingPermission($request->user(), 'can_view_inputs_report', 'No tiene permisos para consultar el reporte de insumos del proyecto.')) {
            abort(403, $response->getData()->message ?? 'No tiene permisos para consultar el reporte de insumos del proyecto.');
        }

        $this->projectHistoryService->recordPdfGenerated($project, $request->user(), $request->ip(), 'Reporte consolidado de insumos XLSX', [
            'export' => 'xlsx',
        ]);

        return $this->projectBudgetXlsxService->inputsReport($project);
    }

    public function groupedInputsReportPdf(Request $request, Project $project): \Illuminate\Http\Response
    {
        if ($response = $this->denyIfMissingPermission($request->user(), 'can_view_inputs_report', 'No tiene permisos para consultar el reporte de insumos del proyecto.')) {
            abort(403, $response->getData()->message ?? 'No tiene permisos para consultar el reporte de insumos del proyecto.');
        }

        $this->projectHistoryService->recordPdfGenerated($project, $request->user(), $request->ip(), 'Proyecto agrupado por insumos');

        return $this->projectInputsGroupedReportPdfService->stream($project);
    }

    public function groupedInputsReportXlsx(Request $request, Project $project): \Illuminate\Http\Response
    {
        if ($response = $this->denyIfMissingPermission($request->user(), 'can_view_inputs_report', 'No tiene permisos para consultar el reporte de insumos del proyecto.')) {
            abort(403, $response->getData()->message ?? 'No tiene permisos para consultar el reporte de insumos del proyecto.');
        }

        $this->projectHistoryService->recordPdfGenerated($project, $request->user(), $request->ip(), 'Proyecto agrupado por insumos XLSX', [
            'export' => 'xlsx',
        ]);

        return $this->projectBudgetXlsxService->groupedInputsReport($project);
    }

    public function breakdownCalculation(ProjectFormatRequest $request, Project $project): JsonResponse
    {
        if ($response = $this->denyIfMissingPermission($request->user(), 'can_view_input_breakdown', 'No tiene permisos para calcular el desglose del proyecto.')) {
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
        if ($response = $this->denyIfMissingPermission($request->user(), 'can_view_unit_prices', 'No tiene permisos para consultar precios unitarios del proyecto.')) {
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

    public function unitPricesPdf(ProjectFormatRequest $request, Project $project): Response|JsonResponse
    {
        if ($response = $this->denyIfMissingPermission($request->user(), 'can_view_unit_prices', 'No tiene permisos para consultar precios unitarios del proyecto.')) {
            return $response;
        }

        if ($request->boolean('validate_only')) {
            $this->projectUnitPricesPdfService->validate($project, $request->validated('format'));

            return response()->json([
                'success' => true,
                'message' => 'El reporte de precios unitarios puede generarse correctamente.',
            ]);
        }

        $this->projectHistoryService->recordPdfGenerated($project, $request->user(), $request->ip(), 'Análisis de precios unitarios del proyecto', [
            'format' => $request->validated('format'),
        ]);

        return $this->projectUnitPricesPdfService->stream($project, $request->validated('format'));
    }

    public function specificationsPdf(Request $request, Project $project): Response|JsonResponse
    {
        if ($response = $this->denyIfMissingPermission($request->user(), 'can_view', 'No tiene permisos para ver especificaciones del proyecto.')) {
            return $response;
        }

        if ($request->boolean('validate_only')) {
            $this->projectSpecificationsPdfMergeService->validate($project);

            return response()->json([
                'success' => true,
                'message' => 'El reporte de especificaciones puede generarse correctamente.',
            ]);
        }

        $this->projectHistoryService->recordPdfGenerated($project, $request->user(), $request->ip(), 'Especificaciones técnicas del proyecto');

        return $this->projectSpecificationsPdfMergeService->stream($project);
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
            'es_plantilla' => (bool) $project->es_plantilla,
            'id_usuario' => $project->id_usuario,
            'version_number' => (int) ($project->numero_version ?? 1),
            'version_count' => (int) ($project->version_count ?? 1),
            'root_project_id' => (int) ($project->id_proyecto_raiz ?: $project->id_proyecto),
            'source_version_id' => $project->id_version_origen ? (int) $project->id_version_origen : null,
            'is_current_version' => $project->es_version_actual === null ? true : (bool) $project->es_version_actual,
            'is_frozen' => $project->isFrozen(),
            'is_editable' => $project->isCurrentVersion() && ! $project->isFrozen(),
            'version_created_at' => $project->fecha_version?->toIso8601String(),
            'finalized_at' => $project->fecha_finalizacion?->toIso8601String(),
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

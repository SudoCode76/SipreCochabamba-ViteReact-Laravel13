<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Item\IndexFndrItemRequest;
use App\Http\Requests\Item\RecalculateItemPriceRequest;
use App\Http\Requests\Item\ShowItemPriceAnalysisRequest;
use App\Http\Requests\Item\StoreItemCompositionInputRequest;
use App\Http\Requests\Item\StoreItemRequest;
use App\Http\Requests\Item\SyncItemCompositionInputsRequest;
use App\Http\Requests\Item\UpdateItemCompositionInputRequest;
use App\Http\Requests\Item\UpdateItemFilesRequest;
use App\Http\Requests\Item\UpdateItemRequest;
use App\Http\Resources\Item\ItemFndrListResource;
use App\Models\Item;
use App\Models\ItemInput;
use App\Modules\Items\Services\Analysis\BuildItemAnalysisContextService;
use App\Modules\Items\Services\Analysis\CreateAnalysisItemService;
use App\Modules\Items\Services\Analysis\ItemAnalysisPermissionService;
use App\Modules\Items\Services\Analysis\ItemPriceAnalysisService;
use App\Modules\Items\Services\Analysis\ListAnalysisItemsService;
use App\Modules\Items\Services\HistoricalBreakdownPdfService;
use App\Modules\Items\Services\ItemBudgetXlsxService;
use App\Modules\Items\Services\ItemCompositionService;
use App\Modules\Items\Services\LaborBreakdownPdfService;
use App\Modules\Items\Services\LegacyUnitPriceAnalysisPdfService;
use App\Modules\Items\Services\LegacyUnitPriceAnalysisService;
use App\Modules\Items\Services\MachineryBreakdownPdfService;
use App\Modules\Items\Services\MaterialBreakdownPdfService;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class ItemController extends Controller
{
    public function __construct(
        private readonly BuildItemAnalysisContextService $buildItemAnalysisContextService,
        private readonly ListAnalysisItemsService $listAnalysisItemsService,
        private readonly CreateAnalysisItemService $createAnalysisItemService,
        private readonly ItemPriceAnalysisService $itemPriceAnalysisService,
        private readonly ItemAnalysisPermissionService $itemAnalysisPermissionService,
        private readonly ItemCompositionService $itemCompositionService,
        private readonly LegacyUnitPriceAnalysisService $legacyUnitPriceAnalysisService,
        private readonly LegacyUnitPriceAnalysisPdfService $legacyUnitPriceAnalysisPdfService,
        private readonly MaterialBreakdownPdfService $materialBreakdownPdfService,
        private readonly LaborBreakdownPdfService $laborBreakdownPdfService,
        private readonly MachineryBreakdownPdfService $machineryBreakdownPdfService,
        private readonly HistoricalBreakdownPdfService $historicalBreakdownPdfService,
        private readonly ItemBudgetXlsxService $itemBudgetXlsxService,
        private readonly AuditService $auditService,
    ) {}

    public function fndrContext(Request $request): JsonResponse
    {
        $permissions = $this->itemAnalysisPermissionService->resolve($request->user(), 'fndr');

        if (! $permissions['can_view']) {
            return $this->forbiddenResponse('No tiene permisos para acceder a la pantalla FNDR.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Contexto FNDR obtenido correctamente.',
            'data' => $this->buildItemAnalysisContextService->execute($request->user(), 'fndr'),
        ]);
    }

    public function context(Request $request): JsonResponse
    {
        $permissions = $this->itemAnalysisPermissionService->resolve($request->user(), 'general');

        if (! $permissions['can_view']) {
            return $this->forbiddenResponse('No tiene permisos para acceder a la pantalla de items.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Contexto de items obtenido correctamente.',
            'data' => $this->buildItemAnalysisContextService->execute($request->user(), 'general'),
        ]);
    }

    public function upreContext(Request $request): JsonResponse
    {
        $permissions = $this->itemAnalysisPermissionService->resolve($request->user(), 'upre');

        if (! $permissions['can_view']) {
            return $this->forbiddenResponse('No tiene permisos para acceder a la pantalla UPRE.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Contexto UPRE obtenido correctamente.',
            'data' => $this->buildItemAnalysisContextService->execute($request->user(), 'upre'),
        ]);
    }

    public function fpsContext(Request $request): JsonResponse
    {
        $permissions = $this->itemAnalysisPermissionService->resolve($request->user(), 'fps');

        if (! $permissions['can_view']) {
            return $this->forbiddenResponse('No tiene permisos para acceder a la pantalla FPS.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Contexto FPS obtenido correctamente.',
            'data' => $this->buildItemAnalysisContextService->execute($request->user(), 'fps'),
        ]);
    }

    public function obrasContext(Request $request): JsonResponse
    {
        $permissions = $this->itemAnalysisPermissionService->resolve($request->user(), 'obras');

        if (! $permissions['can_view']) {
            return $this->forbiddenResponse('No tiene permisos para acceder a la pantalla OBRAS.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Contexto OBRAS obtenido correctamente.',
            'data' => $this->buildItemAnalysisContextService->execute($request->user(), 'obras'),
        ]);
    }

    public function promanContext(Request $request): JsonResponse
    {
        $permissions = $this->itemAnalysisPermissionService->resolve($request->user(), 'proman');

        if (! $permissions['can_view']) {
            return $this->forbiddenResponse('No tiene permisos para acceder a la pantalla PROMAN.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Contexto PROMAN obtenido correctamente.',
            'data' => $this->buildItemAnalysisContextService->execute($request->user(), 'proman'),
        ]);
    }

    public function fndrIndex(IndexFndrItemRequest $request): JsonResponse
    {
        $permissions = $this->itemAnalysisPermissionService->resolve($request->user(), 'fndr');

        if (! $permissions['can_view']) {
            return $this->forbiddenResponse('No tiene permisos para listar items FNDR.');
        }

        try {
            $items = $this->listAnalysisItemsService->execute($request->validated(), 'fndr');
        } catch (InvalidArgumentException $exception) {
            return $this->validationFailureResponse($exception->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Items FNDR obtenidos correctamente.',
            'data' => [
                'items' => ItemFndrListResource::collection($items->getCollection())->resolve(),
                'meta' => [
                    'current_page' => $items->currentPage(),
                    'per_page' => $items->perPage(),
                    'total' => $items->total(),
                ],
            ],
        ]);
    }

    public function index(IndexFndrItemRequest $request): JsonResponse
    {
        $permissions = $this->itemAnalysisPermissionService->resolve($request->user(), 'general');

        if (! $permissions['can_view']) {
            return $this->forbiddenResponse('No tiene permisos para listar items.');
        }

        try {
            $items = $this->listAnalysisItemsService->execute($request->validated(), 'general');
        } catch (InvalidArgumentException $exception) {
            return $this->validationFailureResponse($exception->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Items obtenidos correctamente.',
            'data' => [
                'items' => ItemFndrListResource::collection($items->getCollection())->resolve(),
                'meta' => [
                    'current_page' => $items->currentPage(),
                    'per_page' => $items->perPage(),
                    'total' => $items->total(),
                ],
            ],
        ]);
    }

    public function upreIndex(IndexFndrItemRequest $request): JsonResponse
    {
        $permissions = $this->itemAnalysisPermissionService->resolve($request->user(), 'upre');

        if (! $permissions['can_view']) {
            return $this->forbiddenResponse('No tiene permisos para listar items UPRE.');
        }

        try {
            $items = $this->listAnalysisItemsService->execute($request->validated(), 'upre');
        } catch (InvalidArgumentException $exception) {
            return $this->validationFailureResponse($exception->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Items UPRE obtenidos correctamente.',
            'data' => [
                'items' => ItemFndrListResource::collection($items->getCollection())->resolve(),
                'meta' => [
                    'current_page' => $items->currentPage(),
                    'per_page' => $items->perPage(),
                    'total' => $items->total(),
                ],
            ],
        ]);
    }

    public function fpsIndex(IndexFndrItemRequest $request): JsonResponse
    {
        $permissions = $this->itemAnalysisPermissionService->resolve($request->user(), 'fps');

        if (! $permissions['can_view']) {
            return $this->forbiddenResponse('No tiene permisos para listar items FPS.');
        }

        try {
            $items = $this->listAnalysisItemsService->execute($request->validated(), 'fps');
        } catch (InvalidArgumentException $exception) {
            return $this->validationFailureResponse($exception->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Items FPS obtenidos correctamente.',
            'data' => [
                'items' => ItemFndrListResource::collection($items->getCollection())->resolve(),
                'meta' => [
                    'current_page' => $items->currentPage(),
                    'per_page' => $items->perPage(),
                    'total' => $items->total(),
                ],
            ],
        ]);
    }

    public function obrasIndex(IndexFndrItemRequest $request): JsonResponse
    {
        $permissions = $this->itemAnalysisPermissionService->resolve($request->user(), 'obras');

        if (! $permissions['can_view']) {
            return $this->forbiddenResponse('No tiene permisos para listar items OBRAS.');
        }

        try {
            $items = $this->listAnalysisItemsService->execute($request->validated(), 'obras');
        } catch (InvalidArgumentException $exception) {
            return $this->validationFailureResponse($exception->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Items OBRAS obtenidos correctamente.',
            'data' => [
                'items' => ItemFndrListResource::collection($items->getCollection())->resolve(),
                'meta' => [
                    'current_page' => $items->currentPage(),
                    'per_page' => $items->perPage(),
                    'total' => $items->total(),
                ],
            ],
        ]);
    }

    public function promanIndex(IndexFndrItemRequest $request): JsonResponse
    {
        $permissions = $this->itemAnalysisPermissionService->resolve($request->user(), 'proman');

        if (! $permissions['can_view']) {
            return $this->forbiddenResponse('No tiene permisos para listar items PROMAN.');
        }

        try {
            $items = $this->listAnalysisItemsService->execute($request->validated(), 'proman');
        } catch (InvalidArgumentException $exception) {
            return $this->validationFailureResponse($exception->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Items PROMAN obtenidos correctamente.',
            'data' => [
                'items' => ItemFndrListResource::collection($items->getCollection())->resolve(),
                'meta' => [
                    'current_page' => $items->currentPage(),
                    'per_page' => $items->perPage(),
                    'total' => $items->total(),
                ],
            ],
        ]);
    }

    public function store(StoreItemRequest $request): JsonResponse
    {
        $permissions = $this->itemAnalysisPermissionService->resolve($request->user(), 'general');

        if (! $permissions['can_create']) {
            return $this->forbiddenResponse('No tiene permisos para crear items.');
        }

        $item = $this->createAnalysisItemService->execute($request, $request->user());
        $item->load(['groupCatalog', 'subgroupCatalog', 'unitMeasure', 'creator']);
        $this->auditService->record($request->user(), $request->ip(), 'ITEMS: se creo el item '.$item->item);

        return response()->json([
            'success' => true,
            'message' => 'Item creado correctamente.',
            'data' => [
                'item' => [
                    'id_item' => $item->id_item,
                    'name' => $item->item,
                    'status' => $item->estado,
                    'date' => $item->fecha_item?->toDateString(),
                    'group' => $item->groupCatalog ? [
                        'id' => $item->groupCatalog->id_grupo,
                        'name' => $item->groupCatalog->nombre_grupo,
                        'code' => $item->groupCatalog->codigo_grupo,
                    ] : null,
                    'subgroup' => $item->subgroupCatalog ? [
                        'id' => $item->subgroupCatalog->id_subgrupo,
                        'description' => $item->subgroupCatalog->descripcion,
                        'code' => $item->subgroupCatalog->codigo,
                    ] : null,
                    'unit_measure' => $item->unitMeasure ? [
                        'id' => $item->unitMeasure->id_unidad_medida,
                        'description' => $item->unitMeasure->descripcion,
                        'abbreviation' => $item->unitMeasure->abreviatura,
                    ] : null,
                    'user' => $item->creator ? [
                        'id' => $item->creator->id_usuario,
                        'full_name' => $item->creator->funcionario,
                        'username' => $item->creator->username,
                    ] : null,
                ],
            ],
        ], 201);
    }

    public function update(Item $item, UpdateItemRequest $request): JsonResponse
    {
        $permissions = $this->itemAnalysisPermissionService->resolve($request->user(), 'general');

        if (! $permissions['can_edit']) {
            return $this->forbiddenResponse('No tiene permisos para editar items.');
        }

        if ($request->filled('group_id') && $request->filled('subgroup_id') && $request->filled('status')) {
            $item->grupo = (int) $request->integer('group_id');
            $item->subgrupo = (int) $request->integer('subgroup_id');
            $item->item = strtoupper(trim($request->string('item')->toString()));
            $item->id_unidad = $request->filled('unit_measure_id')
                ? (int) $request->integer('unit_measure_id')
                : $item->id_unidad;
            $item->estado = strtoupper(trim((string) $request->input('status')));
            $item->id_usuario = $request->user()->id_usuario;
        }

        if ($request->hasFile('specification_file')) {
            $item->especificacion = $request->file('specification_file')->store('archivos/items/especificaciones', 'public');
        } elseif ($request->filled('specification')) {
            $item->especificacion = trim((string) $request->input('specification'));
        }

        if ($request->hasFile('sheet_file')) {
            $item->ficha = $request->file('sheet_file')->store('archivos/items/fichas', 'public');
        } elseif ($request->filled('sheet')) {
            $item->ficha = trim((string) $request->input('sheet'));
        }

        $item->save();
        $item->load(['groupCatalog', 'subgroupCatalog', 'unitMeasure']);
        $this->auditService->record($request->user(), $request->ip(), 'ITEMS: se actualizo el item '.$item->item);

        return response()->json([
            'success' => true,
            'message' => 'Item actualizado correctamente.',
            'data' => [
                'item' => [
                    'id_item' => $item->id_item,
                    'name' => $item->item,
                    'price' => $item->precio,
                    'status' => $item->estado,
                    'specification' => $item->especificacion,
                    'specification_url' => $item->especificacion ? Storage::disk('public')->url($item->especificacion) : null,
                    'sheet' => $item->ficha,
                    'sheet_url' => $item->ficha ? Storage::disk('public')->url($item->ficha) : null,
                    'group' => $item->groupCatalog ? [
                        'id' => $item->groupCatalog->id_grupo,
                        'name' => $item->groupCatalog->nombre_grupo,
                    ] : null,
                    'subgroup' => $item->subgroupCatalog ? [
                        'id' => $item->subgroupCatalog->id_subgrupo,
                        'description' => $item->subgroupCatalog->descripcion,
                    ] : null,
                    'unit_measure' => $item->unitMeasure ? [
                        'id' => $item->unitMeasure->id_unidad_medida,
                        'description' => $item->unitMeasure->descripcion,
                        'abbreviation' => $item->unitMeasure->abreviatura,
                    ] : null,
                ],
            ],
        ]);
    }

    public function show(Item $item, Request $request): JsonResponse
    {
        $permissions = $this->itemAnalysisPermissionService->resolve($request->user(), 'general');

        if (! $permissions['can_view']) {
            return $this->forbiddenResponse('No tiene permisos para consultar items.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Item obtenido correctamente.',
            'data' => [
                'item' => $this->serializeFileItem($item),
            ],
        ]);
    }

    public function deactivateImpact(Item $item, Request $request): JsonResponse
    {
        $permissions = $this->itemAnalysisPermissionService->resolve($request->user(), 'general');

        if (! $permissions['can_edit']) {
            return $this->forbiddenResponse('No tiene permisos para editar items.');
        }

        $projects = DB::table('proyecto_item')
            ->join('proyecto', 'proyecto.id_proyecto', '=', 'proyecto_item.id_proyecto')
            ->leftJoin('modulo', 'modulo.id_modulo', '=', 'proyecto_item.id_modulo')
            ->where('proyecto_item.id_item', $item->id_item)
            ->where('proyecto_item.estado', 'AC')
            ->where('proyecto.estado', 'AC')
            ->orderBy('proyecto.nombre_proyecto')
            ->orderBy('proyecto_item.id_proyecto_item')
            ->get([
                'proyecto.id_proyecto',
                'proyecto.nombre_proyecto as name',
                'proyecto.aprobado as approval_status',
                'proyecto.estado as status',
                'proyecto_item.cantidad as quantity',
                'modulo.nombre_modulo as module',
            ])
            ->map(fn ($project): array => [
                'id_proyecto' => (int) $project->id_proyecto,
                'name' => $project->name,
                'approval_status' => $project->approval_status,
                'approval_label' => match ($project->approval_status) {
                    'AP' => 'APROBADO',
                    'RC' => 'RECHAZADO',
                    default => 'PENDIENTE',
                },
                'status' => $project->status,
                'quantity' => round((float) $project->quantity, 4),
                'module' => $project->module ?: 'General',
            ])
            ->values();

        $inputs = DB::table('item_insumo')
            ->join('insumo', 'insumo.id_insumo', '=', 'item_insumo.id_insumo')
            ->leftJoin('tipo_insumo', 'tipo_insumo.id_tipo', '=', 'insumo.tipo')
            ->leftJoin('unidad_medida', 'unidad_medida.id_unidad_medida', '=', 'insumo.unidad_medida')
            ->where('item_insumo.id_item', $item->id_item)
            ->where('item_insumo.estado', 'AC')
            ->where('insumo.estado', 'AC')
            ->orderBy('insumo.tipo')
            ->orderBy('insumo.descripcion')
            ->get([
                'insumo.id_insumo',
                'insumo.descripcion as description',
                'insumo.tipo',
                'tipo_insumo.descripcion as type_description',
                'unidad_medida.abreviatura',
                'unidad_medida.descripcion as unit_description',
                'item_insumo.cantidad as quantity',
                'insumo.precio as unit_price',
            ])
            ->map(fn ($input): array => [
                'id_insumo' => (int) $input->id_insumo,
                'description' => $input->description,
                'type' => $input->type_description ?: match ((int) $input->tipo) {
                    1 => 'Material',
                    2 => 'Mano de Obra',
                    3 => 'Maquinaria y Herramientas',
                    default => 'Insumo',
                },
                'unit' => $input->abreviatura ?: $input->unit_description,
                'quantity' => round((float) $input->quantity, 4),
                'unit_price' => round((float) $input->unit_price, 4),
                'partial' => round(((float) $input->quantity) * ((float) $input->unit_price), 4),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Impacto de desactivacion del item obtenido correctamente.',
            'data' => [
                'projects' => $projects->all(),
                'inputs' => $inputs->all(),
                'summary' => [
                    'projects_count' => $projects->count(),
                    'inputs_count' => $inputs->count(),
                ],
            ],
        ]);
    }

    public function updateFiles(Item $item, UpdateItemFilesRequest $request): JsonResponse
    {
        $permissions = $this->itemAnalysisPermissionService->resolve($request->user(), 'general');

        if (! $permissions['can_edit']) {
            return $this->forbiddenResponse('No tiene permisos para adjuntar archivos al item.');
        }

        if ($request->hasFile('specification_file')) {
            $item->especificacion = $request->file('specification_file')->store('archivos/items/especificaciones', 'public');
        }

        if ($request->hasFile('sheet_file')) {
            $item->ficha = $request->file('sheet_file')->store('archivos/items/fichas', 'public');
        }

        $item->save();
        $this->auditService->record($request->user(), $request->ip(), 'ITEMS: se actualizaron los archivos del item '.$item->item);

        return response()->json([
            'success' => true,
            'message' => 'Archivos del item actualizados correctamente.',
            'data' => [
                'item' => $this->serializeFileItem($item),
            ],
        ]);
    }

    public function priceAnalysis(ShowItemPriceAnalysisRequest $request, Item $item): JsonResponse
    {
        $mode = strtolower((string) $request->input('mode', 'general'));
        $permissions = $this->itemAnalysisPermissionService->resolve($request->user(), $mode);

        if (! $permissions['can_view_price_analysis']) {
            return $this->forbiddenResponse('No tiene permisos para ver el analisis '.strtoupper($mode).' del item.');
        }

        try {
            $analysis = $this->itemPriceAnalysisService->buildCurrent($item, $mode);
        } catch (InvalidArgumentException $exception) {
            return $this->validationFailureResponse($exception->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Analisis de precio '.strtoupper($mode).' obtenido correctamente.',
            'data' => $analysis,
        ]);
    }

    public function legacyUnitPriceAnalysis(HttpRequest $request, Item $item): JsonResponse
    {
        $permissions = $this->itemAnalysisPermissionService->resolve($request->user(), 'general');

        if (! $permissions['can_view_price_analysis']) {
            return $this->forbiddenResponse('No tiene permisos para ver el analisis de precios unitarios del item.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Analisis de precios unitarios obtenido correctamente.',
            'data' => $this->legacyUnitPriceAnalysisService->build($item),
        ]);
    }

    public function legacyUnitPriceAnalysisPdf(ShowItemPriceAnalysisRequest $request, Item $item): Response
    {
        $mode = strtolower((string) $request->input('mode', 'general'));
        $permissions = $this->itemAnalysisPermissionService->resolve($request->user(), $mode);

        if (! $permissions['can_view_price_analysis']) {
            abort(403, 'No tiene permisos para ver el analisis de precios unitarios del item.');
        }

        return $this->legacyUnitPriceAnalysisPdfService->stream($item, $mode);
    }

    public function legacyUnitPriceAnalysisXlsx(ShowItemPriceAnalysisRequest $request, Item $item): Response
    {
        $mode = strtolower((string) $request->input('mode', 'general'));
        $permissions = $this->itemAnalysisPermissionService->resolve($request->user(), $mode);

        if (! $permissions['can_view_price_analysis']) {
            abort(403, 'No tiene permisos para ver el analisis de precios unitarios del item.');
        }

        $this->auditService->record($request->user(), $request->ip(), 'ITEMS: se exporto XLSX del analisis '.$mode.' del item '.$item->item);

        return $this->itemBudgetXlsxService->priceAnalysis($item, $mode);
    }

    public function priceRecalculation(RecalculateItemPriceRequest $request, Item $item): JsonResponse
    {
        $mode = strtolower((string) $request->input('mode', 'general'));
        $permissions = $this->itemAnalysisPermissionService->resolve($request->user(), $mode);

        if (! $permissions['can_recalculate']) {
            return $this->forbiddenResponse('No tiene permisos para recalcular el analisis '.strtoupper($mode).' del item.');
        }

        try {
            $analysis = $this->itemPriceAnalysisService->buildRecalculated($item, $request->date('fecha'), $mode);
        } catch (InvalidArgumentException $exception) {
            return $this->validationFailureResponse($exception->getMessage());
        }
        $this->auditService->record($request->user(), $request->ip(), 'ITEMS: se recalculo el analisis '.$mode.' del item '.$item->item);

        return response()->json([
            'success' => true,
            'message' => 'Analisis de precio '.strtoupper($mode).' recalculado correctamente.',
            'data' => $analysis,
        ]);
    }

    public function priceRecalculationPdf(RecalculateItemPriceRequest $request, Item $item): Response
    {
        $mode = strtolower((string) $request->input('mode', 'general'));
        $permissions = $this->itemAnalysisPermissionService->resolve($request->user(), $mode);

        if (! $permissions['can_recalculate']) {
            abort(403, 'No tiene permisos para recalcular el analisis '.strtoupper($mode).' del item.');
        }

        try {
            $response = $this->legacyUnitPriceAnalysisPdfService->streamRecalculated($item, $request->date('fecha'), $mode);
        } catch (InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }

        $this->auditService->record($request->user(), $request->ip(), 'ITEMS: se genero el reporte recalculado '.$mode.' del item '.$item->item);

        return $response;
    }

    public function priceRecalculationXlsx(RecalculateItemPriceRequest $request, Item $item): Response
    {
        $mode = strtolower((string) $request->input('mode', 'general'));
        $permissions = $this->itemAnalysisPermissionService->resolve($request->user(), $mode);

        if (! $permissions['can_recalculate']) {
            abort(403, 'No tiene permisos para recalcular el analisis '.strtoupper($mode).' del item.');
        }

        try {
            $response = $this->itemBudgetXlsxService->priceAnalysis($item, $mode, $request->date('fecha'));
        } catch (InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }

        $this->auditService->record($request->user(), $request->ip(), 'ITEMS: se exporto XLSX del reporte recalculado '.$mode.' del item '.$item->item);

        return $response;
    }

    public function compositionContext(Item $item, Request $request): JsonResponse
    {
        $permissions = $this->itemAnalysisPermissionService->resolve($request->user(), 'general');

        if (! $permissions['can_view']) {
            return $this->forbiddenResponse('No tiene permisos para acceder a la composicion del item.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Contexto de composicion del item obtenido correctamente.',
            'data' => $this->itemCompositionService->context($item, $permissions),
        ]);
    }

    public function composition(Item $item, Request $request): JsonResponse
    {
        $permissions = $this->itemAnalysisPermissionService->resolve($request->user(), 'general');

        if (! $permissions['can_view']) {
            return $this->forbiddenResponse('No tiene permisos para consultar la composicion del item.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Composicion del item obtenida correctamente.',
            'data' => $this->itemCompositionService->composition($item),
        ]);
    }

    public function materials(Item $item, Request $request): JsonResponse
    {
        return $this->compositionListResponse($item, 1, $request, 'Materiales del item obtenidos correctamente.');
    }

    public function materialsPdf(Item $item, Request $request): Response
    {
        $permissions = $this->itemAnalysisPermissionService->resolve($request->user(), 'general');

        if (! $permissions['can_view']) {
            abort(403, 'No tiene permisos para consultar el desglose de materiales del item.');
        }

        return $this->materialBreakdownPdfService->stream($item);
    }

    public function materialsXlsx(Item $item, Request $request): Response
    {
        $permissions = $this->itemAnalysisPermissionService->resolve($request->user(), 'general');

        if (! $permissions['can_view']) {
            abort(403, 'No tiene permisos para consultar el desglose de materiales del item.');
        }

        return $this->itemBudgetXlsxService->currentBreakdown($item, 1);
    }

    public function storeMaterial(Item $item, StoreItemCompositionInputRequest $request): JsonResponse
    {
        return $this->compositionStoreResponse($item, 1, $request, 'Material agregado correctamente al item.');
    }

    public function syncMaterials(Item $item, SyncItemCompositionInputsRequest $request): JsonResponse
    {
        $permissions = $this->itemAnalysisPermissionService->resolve($request->user(), 'general');

        if (! $permissions['can_create']) {
            return $this->forbiddenResponse('No tiene permisos para modificar la composicion del item.');
        }

        try {
            $data = $this->itemCompositionService->syncType(
                $item,
                1,
                $request->validated('items'),
                $request->validated('deleted_input_ids') ?? [],
                $request->user(),
            );
        } catch (InvalidArgumentException $exception) {
            return $this->validationFailureResponse($exception->getMessage());
        }
        $this->auditService->record($request->user(), $request->ip(), 'ITEMS: se sincronizaron materiales del item '.$item->item);

        return response()->json([
            'success' => true,
            'message' => 'Materiales del item sincronizados correctamente.',
            'data' => $data,
        ]);
    }

    public function updateMaterial(Item $item, ItemInput $itemInput, UpdateItemCompositionInputRequest $request): JsonResponse
    {
        return $this->compositionUpdateResponse($item, $itemInput, 1, $request, 'Material actualizado correctamente.');
    }

    public function deleteMaterial(Item $item, ItemInput $itemInput, Request $request): JsonResponse
    {
        return $this->compositionDeleteResponse($item, $itemInput, 1, $request, 'Material retirado correctamente del item.');
    }

    public function materialsTotal(Item $item, Request $request): JsonResponse
    {
        return $this->compositionTotalResponse($item, 1, $request, 'Total de materiales obtenido correctamente.');
    }

    public function labor(Item $item, Request $request): JsonResponse
    {
        return $this->compositionListResponse($item, 2, $request, 'Mano de obra del item obtenida correctamente.');
    }

    public function laborPdf(Item $item, Request $request): Response
    {
        $permissions = $this->itemAnalysisPermissionService->resolve($request->user(), 'general');

        if (! $permissions['can_view_price_analysis']) {
            abort(403, 'No tiene permisos para consultar el desglose de mano de obra del item.');
        }

        return $this->laborBreakdownPdfService->stream($item);
    }

    public function laborXlsx(Item $item, Request $request): Response
    {
        $permissions = $this->itemAnalysisPermissionService->resolve($request->user(), 'general');

        if (! $permissions['can_view_price_analysis']) {
            abort(403, 'No tiene permisos para consultar el desglose de mano de obra del item.');
        }

        return $this->itemBudgetXlsxService->currentBreakdown($item, 2);
    }

    public function storeLabor(Item $item, StoreItemCompositionInputRequest $request): JsonResponse
    {
        return $this->compositionStoreResponse($item, 2, $request, 'Mano de obra agregada correctamente al item.');
    }

    public function syncLabor(Item $item, SyncItemCompositionInputsRequest $request): JsonResponse
    {
        $permissions = $this->itemAnalysisPermissionService->resolve($request->user(), 'general');

        if (! $permissions['can_create']) {
            return $this->forbiddenResponse('No tiene permisos para modificar la composicion del item.');
        }

        try {
            $data = $this->itemCompositionService->syncType(
                $item,
                2,
                $request->validated('items'),
                $request->validated('deleted_input_ids') ?? [],
                $request->user(),
            );
        } catch (InvalidArgumentException $exception) {
            return $this->validationFailureResponse($exception->getMessage());
        }
        $this->auditService->record($request->user(), $request->ip(), 'ITEMS: se sincronizo mano de obra del item '.$item->item);

        return response()->json([
            'success' => true,
            'message' => 'Mano de obra del item sincronizada correctamente.',
            'data' => $data,
        ]);
    }

    public function updateLabor(Item $item, ItemInput $itemInput, UpdateItemCompositionInputRequest $request): JsonResponse
    {
        return $this->compositionUpdateResponse($item, $itemInput, 2, $request, 'Mano de obra actualizada correctamente.');
    }

    public function deleteLabor(Item $item, ItemInput $itemInput, Request $request): JsonResponse
    {
        return $this->compositionDeleteResponse($item, $itemInput, 2, $request, 'Mano de obra retirada correctamente del item.');
    }

    public function laborTotal(Item $item, Request $request): JsonResponse
    {
        return $this->compositionTotalResponse($item, 2, $request, 'Total de mano de obra obtenido correctamente.');
    }

    public function machinery(Item $item, Request $request): JsonResponse
    {
        return $this->compositionListResponse($item, 3, $request, 'Maquinaria del item obtenida correctamente.');
    }

    public function machineryPdf(Item $item, Request $request): Response
    {
        $permissions = $this->itemAnalysisPermissionService->resolve($request->user(), 'general');

        if (! $permissions['can_view_price_analysis']) {
            abort(403, 'No tiene permisos para consultar el desglose de maquinaria y herramientas del item.');
        }

        return $this->machineryBreakdownPdfService->stream($item);
    }

    public function machineryXlsx(Item $item, Request $request): Response
    {
        $permissions = $this->itemAnalysisPermissionService->resolve($request->user(), 'general');

        if (! $permissions['can_view_price_analysis']) {
            abort(403, 'No tiene permisos para consultar el desglose de maquinaria y herramientas del item.');
        }

        return $this->itemBudgetXlsxService->currentBreakdown($item, 3);
    }

    public function storeMachinery(Item $item, StoreItemCompositionInputRequest $request): JsonResponse
    {
        return $this->compositionStoreResponse($item, 3, $request, 'Maquinaria agregada correctamente al item.');
    }

    public function syncMachinery(Item $item, SyncItemCompositionInputsRequest $request): JsonResponse
    {
        $permissions = $this->itemAnalysisPermissionService->resolve($request->user(), 'general');

        if (! $permissions['can_create']) {
            return $this->forbiddenResponse('No tiene permisos para modificar la composicion del item.');
        }

        try {
            $data = $this->itemCompositionService->syncType(
                $item,
                3,
                $request->validated('items'),
                $request->validated('deleted_input_ids') ?? [],
                $request->user(),
            );
        } catch (InvalidArgumentException $exception) {
            return $this->validationFailureResponse($exception->getMessage());
        }
        $this->auditService->record($request->user(), $request->ip(), 'ITEMS: se sincronizo maquinaria del item '.$item->item);

        return response()->json([
            'success' => true,
            'message' => 'Maquinaria del item sincronizada correctamente.',
            'data' => $data,
        ]);
    }

    public function updateMachinery(Item $item, ItemInput $itemInput, UpdateItemCompositionInputRequest $request): JsonResponse
    {
        return $this->compositionUpdateResponse($item, $itemInput, 3, $request, 'Maquinaria actualizada correctamente.');
    }

    public function deleteMachinery(Item $item, ItemInput $itemInput, Request $request): JsonResponse
    {
        return $this->compositionDeleteResponse($item, $itemInput, 3, $request, 'Maquinaria retirada correctamente del item.');
    }

    public function machineryTotal(Item $item, Request $request): JsonResponse
    {
        return $this->compositionTotalResponse($item, 3, $request, 'Total de maquinaria obtenido correctamente.');
    }

    public function globalTotal(Item $item, Request $request): JsonResponse
    {
        $permissions = $this->itemAnalysisPermissionService->resolve($request->user(), 'general');

        if (! $permissions['can_view']) {
            return $this->forbiddenResponse('No tiene permisos para consultar el total global del item.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Total global del item obtenido correctamente.',
            'data' => [
                'global' => $this->itemCompositionService->globalTotal($item),
            ],
        ]);
    }

    public function breakdownRecalculation(Item $item, Request $request): Response|JsonResponse
    {
        $permissions = $this->itemAnalysisPermissionService->resolve($request->user(), 'general');

        if (! $permissions['can_recalculate']) {
            return $this->forbiddenResponse('No tiene permisos para recalcular desgloses del item.');
        }

        $validated = $request->validate([
            'fecha' => ['required', 'date'],
            'tipo' => ['nullable'],
            'tipo_desglose' => ['nullable'],
        ]);

        $type = $validated['tipo'] ?? $validated['tipo_desglose'] ?? null;

        if ($type === null || $type === '') {
            return $this->validationFailureResponse('El tipo de desglose es obligatorio.');
        }

        try {
            $response = $this->historicalBreakdownPdfService->stream($item, $type, $request->date('fecha'));
            $this->auditService->record($request->user(), $request->ip(), 'ITEMS: se recalculo el desglose historico del item '.$item->item);

            return $response;
        } catch (InvalidArgumentException $exception) {
            return $this->validationFailureResponse($exception->getMessage());
        }
    }

    public function breakdownRecalculationXlsx(Item $item, Request $request): Response|JsonResponse
    {
        $permissions = $this->itemAnalysisPermissionService->resolve($request->user(), 'general');

        if (! $permissions['can_recalculate']) {
            return $this->forbiddenResponse('No tiene permisos para recalcular desgloses del item.');
        }

        $validated = $request->validate([
            'fecha' => ['required', 'date'],
            'tipo' => ['nullable'],
            'tipo_desglose' => ['nullable'],
        ]);

        $type = $validated['tipo'] ?? $validated['tipo_desglose'] ?? null;

        if ($type === null || $type === '') {
            return $this->validationFailureResponse('El tipo de desglose es obligatorio.');
        }

        try {
            $response = $this->itemBudgetXlsxService->historicalBreakdown($item, $type, $request->date('fecha'));
            $this->auditService->record($request->user(), $request->ip(), 'ITEMS: se exporto XLSX del desglose historico del item '.$item->item);

            return $response;
        } catch (InvalidArgumentException $exception) {
            return $this->validationFailureResponse($exception->getMessage());
        }
    }

    private function compositionListResponse(Item $item, int $type, Request $request, string $message): JsonResponse
    {
        $permissions = $this->itemAnalysisPermissionService->resolve($request->user(), 'general');

        if (! $permissions['can_view']) {
            return $this->forbiddenResponse('No tiene permisos para consultar la composicion del item.');
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'items' => $this->itemCompositionService->listByType($item, $type),
            ],
        ]);
    }

    private function compositionStoreResponse(Item $item, int $type, StoreItemCompositionInputRequest $request, string $message): JsonResponse
    {
        $permissions = $this->itemAnalysisPermissionService->resolve($request->user(), 'general');

        if (! $permissions['can_create']) {
            return $this->forbiddenResponse('No tiene permisos para modificar la composicion del item.');
        }

        try {
            $record = $this->itemCompositionService->create(
                $item,
                $type,
                (int) $request->integer('id_insumo'),
                (float) $request->input('cantidad'),
                $request->user(),
            );
        } catch (InvalidArgumentException $exception) {
            return $this->validationFailureResponse($exception->getMessage());
        }
        $this->auditService->record($request->user(), $request->ip(), 'ITEMS: se agrego un insumo tipo '.$type.' al item '.$item->item);

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'item_input' => $record,
                'totals' => [
                    'block' => $this->itemCompositionService->totalByType($item, $type),
                    'global' => $this->itemCompositionService->globalTotal($item),
                ],
            ],
        ], 201);
    }

    private function compositionUpdateResponse(Item $item, ItemInput $itemInput, int $type, UpdateItemCompositionInputRequest $request, string $message): JsonResponse
    {
        $permissions = $this->itemAnalysisPermissionService->resolve($request->user(), 'general');

        if (! $permissions['can_create']) {
            return $this->forbiddenResponse('No tiene permisos para modificar la composicion del item.');
        }

        try {
            $record = $this->itemCompositionService->update(
                $item,
                $type,
                $itemInput,
                (float) $request->input('cantidad'),
            );
        } catch (InvalidArgumentException $exception) {
            return $this->validationFailureResponse($exception->getMessage());
        }
        $this->auditService->record($request->user(), $request->ip(), 'ITEMS: se actualizo un insumo tipo '.$type.' del item '.$item->item);

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'item_input' => $record,
                'totals' => [
                    'block' => $this->itemCompositionService->totalByType($item, $type),
                    'global' => $this->itemCompositionService->globalTotal($item),
                ],
            ],
        ]);
    }

    private function compositionDeleteResponse(Item $item, ItemInput $itemInput, int $type, Request $request, string $message): JsonResponse
    {
        $permissions = $this->itemAnalysisPermissionService->resolve($request->user(), 'general');

        if (! $permissions['can_create']) {
            return $this->forbiddenResponse('No tiene permisos para modificar la composicion del item.');
        }

        try {
            $this->itemCompositionService->delete($item, $type, $itemInput);
        } catch (InvalidArgumentException $exception) {
            return $this->validationFailureResponse($exception->getMessage());
        }
        $this->auditService->record($request->user(), $request->ip(), 'ITEMS: se retiro un insumo tipo '.$type.' del item '.$item->item);

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'totals' => [
                    'block' => $this->itemCompositionService->totalByType($item, $type),
                    'global' => $this->itemCompositionService->globalTotal($item),
                ],
            ],
        ]);
    }

    private function compositionTotalResponse(Item $item, int $type, Request $request, string $message): JsonResponse
    {
        $permissions = $this->itemAnalysisPermissionService->resolve($request->user(), 'general');

        if (! $permissions['can_view']) {
            return $this->forbiddenResponse('No tiene permisos para consultar la composicion del item.');
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'total' => $this->itemCompositionService->totalByType($item, $type),
            ],
        ]);
    }

    private function forbiddenResponse(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => null,
        ], 403);
    }

    private function validationFailureResponse(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => null,
        ], 422);
    }

    private function serializeFileItem(Item $item): array
    {
        return [
            'id_item' => $item->id_item,
            'name' => $item->item,
            'specification' => $item->especificacion,
            'specification_url' => $item->especificacion ? Storage::disk('public')->url($item->especificacion) : null,
            'sheet' => $item->ficha,
            'sheet_url' => $item->ficha ? Storage::disk('public')->url($item->ficha) : null,
        ];
    }
}

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
use App\Services\Items\Analysis\BuildItemAnalysisContextService;
use App\Services\Items\Analysis\CreateAnalysisItemService;
use App\Services\Items\Analysis\ItemAnalysisPermissionService;
use App\Services\Items\Analysis\ItemPriceAnalysisService;
use App\Services\Items\Analysis\ListAnalysisItemsService;
use App\Services\Items\HistoricalBreakdownPdfService;
use App\Services\Items\ItemCompositionService;
use App\Services\Items\LaborBreakdownPdfService;
use App\Services\Items\LegacyUnitPriceAnalysisPdfService;
use App\Services\Items\LegacyUnitPriceAnalysisService;
use App\Services\Items\MachineryBreakdownPdfService;
use App\Services\Items\MaterialBreakdownPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Http\Response;
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

        if (! $permissions['can_view']) {
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

    public function updateFiles(Item $item, UpdateItemFilesRequest $request): JsonResponse
    {
        $permissions = $this->itemAnalysisPermissionService->resolve($request->user(), 'general');

        if (! $permissions['can_view']) {
            return $this->forbiddenResponse('No tiene permisos para adjuntar archivos al item.');
        }

        if ($request->hasFile('specification_file')) {
            $item->especificacion = $request->file('specification_file')->store('archivos/items/especificaciones', 'public');
        }

        if ($request->hasFile('sheet_file')) {
            $item->ficha = $request->file('sheet_file')->store('archivos/items/fichas', 'public');
        }

        $item->save();

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

        return response()->json([
            'success' => true,
            'message' => 'Analisis de precio '.strtoupper($mode).' recalculado correctamente.',
            'data' => $analysis,
        ]);
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
            return $this->historicalBreakdownPdfService->stream($item, $type, $request->date('fecha'));
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

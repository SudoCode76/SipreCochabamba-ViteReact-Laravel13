<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Item\IndexFndrItemRequest;
use App\Http\Requests\Item\RecalculateItemPriceRequest;
use App\Http\Requests\Item\ShowItemPriceAnalysisRequest;
use App\Http\Requests\Item\StoreItemRequest;
use App\Http\Resources\Item\ItemFndrListResource;
use App\Models\Item;
use App\Services\Items\Fndr\BuildFndrItemContextService;
use App\Services\Items\Fndr\CreateItemService;
use App\Services\Items\Fndr\FndrPermissionService;
use App\Services\Items\Fndr\FndrPriceAnalysisService;
use App\Services\Items\Fndr\ListFndrItemsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ItemController extends Controller
{
    public function __construct(
        private readonly BuildFndrItemContextService $buildFndrItemContextService,
        private readonly ListFndrItemsService $listFndrItemsService,
        private readonly CreateItemService $createItemService,
        private readonly FndrPriceAnalysisService $fndrPriceAnalysisService,
        private readonly FndrPermissionService $fndrPermissionService,
    ) {}

    public function fndrContext(Request $request): JsonResponse
    {
        $permissions = $this->fndrPermissionService->resolve($request->user(), 'fndr');

        if (! $permissions['can_view']) {
            return $this->forbiddenResponse('No tiene permisos para acceder a la pantalla FNDR.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Contexto FNDR obtenido correctamente.',
            'data' => $this->buildFndrItemContextService->execute($request->user(), 'fndr'),
        ]);
    }

    public function upreContext(Request $request): JsonResponse
    {
        $permissions = $this->fndrPermissionService->resolve($request->user(), 'upre');

        if (! $permissions['can_view']) {
            return $this->forbiddenResponse('No tiene permisos para acceder a la pantalla UPRE.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Contexto UPRE obtenido correctamente.',
            'data' => $this->buildFndrItemContextService->execute($request->user(), 'upre'),
        ]);
    }

    public function fndrIndex(IndexFndrItemRequest $request): JsonResponse
    {
        $permissions = $this->fndrPermissionService->resolve($request->user(), 'fndr');

        if (! $permissions['can_view']) {
            return $this->forbiddenResponse('No tiene permisos para listar items FNDR.');
        }

        try {
            $items = $this->listFndrItemsService->execute($request->validated(), 'fndr');
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

    public function upreIndex(IndexFndrItemRequest $request): JsonResponse
    {
        $permissions = $this->fndrPermissionService->resolve($request->user(), 'upre');

        if (! $permissions['can_view']) {
            return $this->forbiddenResponse('No tiene permisos para listar items UPRE.');
        }

        try {
            $items = $this->listFndrItemsService->execute($request->validated(), 'upre');
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

    public function store(StoreItemRequest $request): JsonResponse
    {
        $permissions = $this->fndrPermissionService->resolve($request->user());

        if (! $permissions['can_create']) {
            return $this->forbiddenResponse('No tiene permisos para crear items en FNDR.');
        }

        $item = $this->createItemService->execute($request, $request->user());
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

    public function priceAnalysis(ShowItemPriceAnalysisRequest $request, Item $item): JsonResponse
    {
        $mode = strtolower($request->string('mode')->toString());
        $permissions = $this->fndrPermissionService->resolve($request->user(), $mode);

        if (! $permissions['can_view_price_analysis']) {
            return $this->forbiddenResponse('No tiene permisos para ver el analisis '.strtoupper($mode).' del item.');
        }

        try {
            $analysis = $this->fndrPriceAnalysisService->buildCurrent($item, $mode);
        } catch (InvalidArgumentException $exception) {
            return $this->validationFailureResponse($exception->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Analisis de precio '.strtoupper($mode).' obtenido correctamente.',
            'data' => $analysis,
        ]);
    }

    public function priceRecalculation(RecalculateItemPriceRequest $request, Item $item): JsonResponse
    {
        $mode = strtolower($request->string('mode')->toString());
        $permissions = $this->fndrPermissionService->resolve($request->user(), $mode);

        if (! $permissions['can_recalculate']) {
            return $this->forbiddenResponse('No tiene permisos para recalcular el analisis '.strtoupper($mode).' del item.');
        }

        try {
            $analysis = $this->fndrPriceAnalysisService->buildRecalculated($item, $request->date('fecha'), $mode);
        } catch (InvalidArgumentException $exception) {
            return $this->validationFailureResponse($exception->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Analisis de precio '.strtoupper($mode).' recalculado correctamente.',
            'data' => $analysis,
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
}

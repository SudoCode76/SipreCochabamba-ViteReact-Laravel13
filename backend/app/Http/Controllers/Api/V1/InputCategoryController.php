<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Input\StoreInputCategoryRequest;
use App\Http\Requests\Input\UpdateInputCategoryRequest;
use App\Http\Resources\Input\InputCategoryResource;
use App\Models\InputCategory;
use App\Services\Inputs\InputCategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InputCategoryController extends Controller
{
    public function __construct(
        private readonly InputCategoryService $inputCategoryService,
    ) {}

    public function index(): JsonResponse
    {
        $items = InputCategory::query()
            ->orderByDesc('id_categoria')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Categorias de insumo obtenidas correctamente.',
            'data' => [
                'items' => InputCategoryResource::collection($items)->resolve(),
            ],
        ]);
    }

    public function context(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Contexto de categorias de insumo obtenido correctamente.',
            'data' => $this->inputCategoryService->context($request->user()),
        ]);
    }

    public function store(StoreInputCategoryRequest $request): JsonResponse
    {
        $category = $this->inputCategoryService->create($request, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Categoria de insumo creada correctamente.',
            'data' => [
                'input_category' => InputCategoryResource::make($category)->resolve(),
            ],
        ], 201);
    }

    public function show(InputCategory $category): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Categoria de insumo obtenida correctamente.',
            'data' => [
                'input_category' => InputCategoryResource::make($category)->resolve(),
            ],
        ]);
    }

    public function update(UpdateInputCategoryRequest $request, InputCategory $category): JsonResponse
    {
        $category = $this->inputCategoryService->update($request, $category, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Categoria de insumo actualizada correctamente.',
            'data' => [
                'input_category' => InputCategoryResource::make($category)->resolve(),
            ],
        ]);
    }
}

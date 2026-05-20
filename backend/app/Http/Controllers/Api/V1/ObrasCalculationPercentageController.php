<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Item\IndexObrasCalculationPercentageRequest;
use App\Http\Requests\Item\StoreCalculationPercentageRequest;
use App\Http\Requests\Item\UpdateCalculationPercentageRequest;
use App\Http\Resources\Item\CalculationPercentageResource;
use App\Models\ObrasCalculationPercentage;
use App\Modules\Items\Services\ObrasCalculationPercentageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ObrasCalculationPercentageController extends Controller
{
    public function __construct(
        private readonly ObrasCalculationPercentageService $obrasCalculationPercentageService,
    ) {}

    public function index(IndexObrasCalculationPercentageRequest $request): JsonResponse
    {
        $items = $this->obrasCalculationPercentageService->list($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Porcentajes de calculo Obras obtenidos correctamente.',
            'data' => [
                'items' => CalculationPercentageResource::collection($items->getCollection())->resolve(),
                'meta' => [
                    'current_page' => $items->currentPage(),
                    'per_page' => $items->perPage(),
                    'total' => $items->total(),
                    'from' => $items->firstItem(),
                    'to' => $items->lastItem(),
                    'last_page' => $items->lastPage(),
                    'has_more_pages' => $items->hasMorePages(),
                ],
            ],
        ]);
    }

    public function context(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Contexto de porcentajes de calculo Obras obtenido correctamente.',
            'data' => $this->obrasCalculationPercentageService->context($request->user()),
        ]);
    }

    public function store(StoreCalculationPercentageRequest $request): JsonResponse
    {
        $percentage = $this->obrasCalculationPercentageService->create($request, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Porcentaje de calculo Obras creado correctamente.',
            'data' => [
                'calculation_percentage' => CalculationPercentageResource::make($percentage)->resolve(),
            ],
        ], 201);
    }

    public function show(ObrasCalculationPercentage $calculationPercentage): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Porcentaje de calculo Obras obtenido correctamente.',
            'data' => [
                'calculation_percentage' => CalculationPercentageResource::make($calculationPercentage)->resolve(),
            ],
        ]);
    }

    public function update(UpdateCalculationPercentageRequest $request, ObrasCalculationPercentage $calculationPercentage): JsonResponse
    {
        $calculationPercentage = $this->obrasCalculationPercentageService->update($request, $calculationPercentage, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Porcentaje de calculo Obras actualizado correctamente.',
            'data' => [
                'calculation_percentage' => CalculationPercentageResource::make($calculationPercentage)->resolve(),
            ],
        ]);
    }
}

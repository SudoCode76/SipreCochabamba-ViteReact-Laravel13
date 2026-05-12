<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Item\IndexUpreCalculationPercentageRequest;
use App\Http\Requests\Item\StoreCalculationPercentageRequest;
use App\Http\Requests\Item\UpdateCalculationPercentageRequest;
use App\Http\Resources\Item\CalculationPercentageResource;
use App\Models\UpreCalculationPercentage;
use App\Services\Items\UpreCalculationPercentageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UpreCalculationPercentageController extends Controller
{
    public function __construct(
        private readonly UpreCalculationPercentageService $upreCalculationPercentageService,
    ) {}

    public function index(IndexUpreCalculationPercentageRequest $request): JsonResponse
    {
        $items = $this->upreCalculationPercentageService->list($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Porcentajes de calculo UPRE obtenidos correctamente.',
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
            'message' => 'Contexto de porcentajes de calculo UPRE obtenido correctamente.',
            'data' => $this->upreCalculationPercentageService->context($request->user()),
        ]);
    }

    public function store(StoreCalculationPercentageRequest $request): JsonResponse
    {
        $percentage = $this->upreCalculationPercentageService->create($request, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Porcentaje de calculo UPRE creado correctamente.',
            'data' => [
                'calculation_percentage' => CalculationPercentageResource::make($percentage)->resolve(),
            ],
        ], 201);
    }

    public function show(UpreCalculationPercentage $calculationPercentage): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Porcentaje de calculo UPRE obtenido correctamente.',
            'data' => [
                'calculation_percentage' => CalculationPercentageResource::make($calculationPercentage)->resolve(),
            ],
        ]);
    }

    public function update(UpdateCalculationPercentageRequest $request, UpreCalculationPercentage $calculationPercentage): JsonResponse
    {
        $calculationPercentage = $this->upreCalculationPercentageService->update($request, $calculationPercentage, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Porcentaje de calculo UPRE actualizado correctamente.',
            'data' => [
                'calculation_percentage' => CalculationPercentageResource::make($calculationPercentage)->resolve(),
            ],
        ]);
    }
}

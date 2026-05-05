<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Item\StoreCalculationPercentageRequest;
use App\Http\Requests\Item\UpdateCalculationPercentageRequest;
use App\Http\Resources\Item\CalculationPercentageResource;
use App\Models\FpsCalculationPercentage;
use App\Services\Items\FpsCalculationPercentageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FpsCalculationPercentageController extends Controller
{
    public function __construct(
        private readonly FpsCalculationPercentageService $fpsCalculationPercentageService,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Porcentajes de calculo FPS obtenidos correctamente.',
            'data' => [
                'items' => CalculationPercentageResource::collection($this->fpsCalculationPercentageService->list())->resolve(),
            ],
        ]);
    }

    public function context(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Contexto de porcentajes de calculo FPS obtenido correctamente.',
            'data' => $this->fpsCalculationPercentageService->context($request->user()),
        ]);
    }

    public function store(StoreCalculationPercentageRequest $request): JsonResponse
    {
        $percentage = $this->fpsCalculationPercentageService->create($request, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Porcentaje de calculo FPS creado correctamente.',
            'data' => [
                'calculation_percentage' => CalculationPercentageResource::make($percentage)->resolve(),
            ],
        ], 201);
    }

    public function show(FpsCalculationPercentage $calculationPercentage): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Porcentaje de calculo FPS obtenido correctamente.',
            'data' => [
                'calculation_percentage' => CalculationPercentageResource::make($calculationPercentage)->resolve(),
            ],
        ]);
    }

    public function update(UpdateCalculationPercentageRequest $request, FpsCalculationPercentage $calculationPercentage): JsonResponse
    {
        $calculationPercentage = $this->fpsCalculationPercentageService->update($request, $calculationPercentage, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Porcentaje de calculo FPS actualizado correctamente.',
            'data' => [
                'calculation_percentage' => CalculationPercentageResource::make($calculationPercentage)->resolve(),
            ],
        ]);
    }
}

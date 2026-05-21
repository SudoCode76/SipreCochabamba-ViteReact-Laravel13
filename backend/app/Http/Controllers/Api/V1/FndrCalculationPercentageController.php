<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Item\IndexFndrCalculationPercentageRequest;
use App\Http\Requests\Item\StoreCalculationPercentageRequest;
use App\Http\Requests\Item\UpdateCalculationPercentageRequest;
use App\Http\Resources\Item\CalculationPercentageResource;
use App\Models\FndrCalculationPercentage;
use App\Modules\Items\Services\FndrCalculationPercentageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FndrCalculationPercentageController extends Controller
{
    public function __construct(
        private readonly FndrCalculationPercentageService $fndrCalculationPercentageService,
    ) {}

    public function index(IndexFndrCalculationPercentageRequest $request): JsonResponse
    {
        $items = $this->fndrCalculationPercentageService->list($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Porcentajes de calculo FNDR obtenidos correctamente.',
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
            'message' => 'Contexto de porcentajes de calculo FNDR obtenido correctamente.',
            'data' => $this->fndrCalculationPercentageService->context($request->user()),
        ]);
    }

    public function store(StoreCalculationPercentageRequest $request): JsonResponse
    {
        $percentage = $this->fndrCalculationPercentageService->create($request, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Porcentaje de calculo FNDR creado correctamente.',
            'data' => [
                'calculation_percentage' => CalculationPercentageResource::make($percentage)->resolve(),
            ],
        ], 201);
    }

    public function show(FndrCalculationPercentage $calculationPercentage): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Porcentaje de calculo FNDR obtenido correctamente.',
            'data' => [
                'calculation_percentage' => CalculationPercentageResource::make($calculationPercentage)->resolve(),
            ],
        ]);
    }

    public function update(UpdateCalculationPercentageRequest $request, FndrCalculationPercentage $calculationPercentage): JsonResponse
    {
        $calculationPercentage = $this->fndrCalculationPercentageService->update($request, $calculationPercentage, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Porcentaje de calculo FNDR actualizado correctamente.',
            'data' => [
                'calculation_percentage' => CalculationPercentageResource::make($calculationPercentage)->resolve(),
            ],
        ]);
    }
}

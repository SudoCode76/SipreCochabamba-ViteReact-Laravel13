<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Item\IndexPromanCalculationPercentageRequest;
use App\Http\Requests\Item\StoreCalculationPercentageRequest;
use App\Http\Requests\Item\UpdateCalculationPercentageRequest;
use App\Http\Resources\Item\CalculationPercentageResource;
use App\Models\PromanCalculationPercentage;
use App\Modules\Items\Services\PromanCalculationPercentageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromanCalculationPercentageController extends Controller
{
    public function __construct(
        private readonly PromanCalculationPercentageService $promanCalculationPercentageService,
    ) {}

    public function index(IndexPromanCalculationPercentageRequest $request): JsonResponse
    {
        $items = $this->promanCalculationPercentageService->list($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Porcentajes de calculo PROMAN obtenidos correctamente.',
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
            'message' => 'Contexto de porcentajes de calculo PROMAN obtenido correctamente.',
            'data' => $this->promanCalculationPercentageService->context($request->user()),
        ]);
    }

    public function store(StoreCalculationPercentageRequest $request): JsonResponse
    {
        $percentage = $this->promanCalculationPercentageService->create($request, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Porcentaje de calculo PROMAN creado correctamente.',
            'data' => [
                'calculation_percentage' => CalculationPercentageResource::make($percentage)->resolve(),
            ],
        ], 201);
    }

    public function show(PromanCalculationPercentage $calculationPercentage): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Porcentaje de calculo PROMAN obtenido correctamente.',
            'data' => [
                'calculation_percentage' => CalculationPercentageResource::make($calculationPercentage)->resolve(),
            ],
        ]);
    }

    public function update(UpdateCalculationPercentageRequest $request, PromanCalculationPercentage $calculationPercentage): JsonResponse
    {
        $calculationPercentage = $this->promanCalculationPercentageService->update($request, $calculationPercentage, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Porcentaje de calculo PROMAN actualizado correctamente.',
            'data' => [
                'calculation_percentage' => CalculationPercentageResource::make($calculationPercentage)->resolve(),
            ],
        ]);
    }
}

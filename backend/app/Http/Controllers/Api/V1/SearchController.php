<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\InputType;
use App\Services\Inputs\InputSearchService;
use App\Services\Projects\ProjectItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(
        private readonly ProjectItemService $projectItemService,
        private readonly InputSearchService $inputSearchService,
    ) {}

    public function items(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Items encontrados correctamente.',
            'data' => [
                'items' => $this->projectItemService->searchItems((string) $request->query('search', '')),
            ],
        ]);
    }

    public function inputs(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Insumos encontrados correctamente.',
            'data' => [
                'items' => $this->inputSearchService->searchInputs((string) $request->query('search', '')),
            ],
        ]);
    }

    public function unitMeasures(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Unidades de medida encontradas correctamente.',
            'data' => [
                'items' => $this->inputSearchService->searchUnitMeasures((string) $request->query('search', '')),
            ],
        ]);
    }

    public function inputTypes(Request $request): JsonResponse
    {
        $search = strtolower(trim((string) $request->query('search', '')));

        $items = InputType::query()
            ->where('estado', 'AC')
            ->when($search !== '', fn ($query) => $query->whereRaw('LOWER(TRIM(descripcion)) LIKE ?', ["%{$search}%"]))
            ->orderBy('descripcion')
            ->limit(20)
            ->get(['id_tipo', 'descripcion'])
            ->map(fn (InputType $type): array => [
                'id' => $type->id_tipo,
                'text' => $type->descripcion,
            ])
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'message' => 'Tipos de insumo encontrados correctamente.',
            'data' => [
                'items' => $items,
            ],
        ]);
    }
}

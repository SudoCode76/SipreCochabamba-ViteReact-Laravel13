<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Input;
use App\Services\Inputs\InputListService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly InputListService $inputListService,
    ) {}

    public function inputs(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'size:2', 'in:AC,DC,DP'],
            'order' => ['nullable', 'string', 'in:legacy,recent,oldest'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $inputs = $this->inputListService->execute($filters);

        return response()->json([
            'success' => true,
            'message' => 'Insumos del dashboard obtenidos correctamente.',
            'data' => [
                'items' => $inputs->getCollection()
                    ->map(fn (Input $input): array => [
                        'id' => $input->id_insumo,
                        'id_insumo' => $input->id_insumo,
                        'description' => $input->descripcion,
                        'descripcion' => $input->descripcion,
                        'price' => $input->precio !== null ? (float) $input->precio : null,
                        'precio' => $input->precio !== null ? (float) $input->precio : null,
                        'status' => $input->estado,
                        'estado' => $input->estado,
                        'quote_date' => $input->fecha_cotiz?->toDateString(),
                        'fecha_cotiz' => $input->fecha_cotiz?->toDateString(),
                        'type' => $input->type ? [
                            'id' => $input->type->id_tipo,
                            'description' => $input->type->descripcion,
                        ] : null,
                        'unit_measure' => $input->unitMeasure ? [
                            'id' => $input->unitMeasure->id_unidad_medida,
                            'description' => $input->unitMeasure->descripcion,
                            'abbreviation' => $input->unitMeasure->abreviatura,
                        ] : null,
                    ])
                    ->values()
                    ->all(),
                'meta' => [
                    'current_page' => $inputs->currentPage(),
                    'per_page' => $inputs->perPage(),
                    'total' => $inputs->total(),
                ],
            ],
        ]);
    }
}

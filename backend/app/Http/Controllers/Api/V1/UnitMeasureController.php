<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Input\DeleteUnitMeasureRequest;
use App\Http\Requests\Input\StoreUnitMeasureDeleteAuthorizationRequest;
use App\Http\Requests\Input\StoreUnitMeasureRequest;
use App\Http\Requests\Input\UpdateUnitMeasureRequest;
use App\Http\Resources\Input\UnitMeasureResource;
use App\Models\UnitMeasure;
use App\Services\Inputs\UnitMeasureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnitMeasureController extends Controller
{
    public function __construct(
        private readonly UnitMeasureService $unitMeasureService,
    ) {}

    public function index(): JsonResponse
    {
        $items = UnitMeasure::query()
            ->where('estado', '!=', 'DP')
            ->orderByDesc('id_unidad_medida')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Unidades de medida obtenidas correctamente.',
            'data' => [
                'items' => UnitMeasureResource::collection($items)->resolve(),
            ],
        ]);
    }

    public function context(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Contexto de unidades de medida obtenido correctamente.',
            'data' => $this->unitMeasureService->context($request->user()),
        ]);
    }

    public function store(StoreUnitMeasureRequest $request): JsonResponse
    {
        $unitMeasure = $this->unitMeasureService->create($request, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Unidad de medida creada correctamente.',
            'data' => [
                'unit_measure' => UnitMeasureResource::make($unitMeasure)->resolve(),
            ],
        ], 201);
    }

    public function show(UnitMeasure $unitMeasure): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Unidad de medida obtenida correctamente.',
            'data' => [
                'unit_measure' => UnitMeasureResource::make($unitMeasure)->resolve(),
            ],
        ]);
    }

    public function update(UpdateUnitMeasureRequest $request, UnitMeasure $unitMeasure): JsonResponse
    {
        $unitMeasure = $this->unitMeasureService->update($request, $unitMeasure, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Unidad de medida actualizada correctamente.',
            'data' => [
                'unit_measure' => UnitMeasureResource::make($unitMeasure)->resolve(),
            ],
        ]);
    }

    public function destroy(DeleteUnitMeasureRequest $request, UnitMeasure $unitMeasure): JsonResponse
    {
        $unitMeasure = $this->unitMeasureService->delete($unitMeasure, $request, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Unidad de medida eliminada logicamente correctamente.',
            'data' => [
                'unit_measure' => [
                    'id_unidad_medida' => $unitMeasure->id_unidad_medida,
                    'estado' => $unitMeasure->estado,
                ],
            ],
        ]);
    }

    public function requestDeleteAuthorization(StoreUnitMeasureDeleteAuthorizationRequest $request, UnitMeasure $unitMeasure): JsonResponse
    {
        $authorization = $this->unitMeasureService->requestAuthorization($unitMeasure, $request, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Solicitud de autorizacion creada correctamente.',
            'data' => [
                'authorization' => [
                    'id_autorizacion' => $authorization->id_autorizacion,
                    'id_elemento' => $authorization->id_elemento,
                    'elemento' => $authorization->elemento,
                    'tipo_elemento' => $authorization->tipo_elemento,
                    'tabla' => $authorization->tabla,
                    'solicitante' => $authorization->solicitante,
                    'estado' => $authorization->estado,
                    'nro_autorizacion' => $authorization->nro_autorizacion,
                ],
            ],
        ], 201);
    }

    public function deleteAuthorizationStatus(UnitMeasure $unitMeasure): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Estado de autorizacion obtenido correctamente.',
            'data' => $this->unitMeasureService->authorizationStatus($unitMeasure),
        ]);
    }
}

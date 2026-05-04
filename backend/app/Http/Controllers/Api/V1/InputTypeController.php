<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Input\StoreInputTypeRequest;
use App\Http\Requests\Input\UpdateInputTypeRequest;
use App\Http\Resources\Input\InputTypeResource;
use App\Models\InputType;
use App\Services\Inputs\InputTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InputTypeController extends Controller
{
    public function __construct(
        private readonly InputTypeService $inputTypeService,
    ) {}

    public function index(): JsonResponse
    {
        $items = InputType::query()
            ->orderByDesc('id_tipo')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Tipos de insumo obtenidos correctamente.',
            'data' => [
                'items' => InputTypeResource::collection($items)->resolve(),
            ],
        ]);
    }

    public function context(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Contexto de tipos de insumo obtenido correctamente.',
            'data' => $this->inputTypeService->context($request->user()),
        ]);
    }

    public function store(StoreInputTypeRequest $request): JsonResponse
    {
        $inputType = $this->inputTypeService->create($request, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Tipo de insumo creado correctamente.',
            'data' => [
                'input_type' => InputTypeResource::make($inputType)->resolve(),
            ],
        ], 201);
    }

    public function show(InputType $inputType): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Tipo de insumo obtenido correctamente.',
            'data' => [
                'input_type' => InputTypeResource::make($inputType)->resolve(),
            ],
        ]);
    }

    public function update(UpdateInputTypeRequest $request, InputType $inputType): JsonResponse
    {
        $inputType = $this->inputTypeService->update($request, $inputType, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Tipo de insumo actualizado correctamente.',
            'data' => [
                'input_type' => InputTypeResource::make($inputType)->resolve(),
            ],
        ]);
    }
}

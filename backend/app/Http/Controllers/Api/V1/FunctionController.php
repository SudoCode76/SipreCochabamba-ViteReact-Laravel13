<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Function\StoreFunctionRequest;
use App\Http\Requests\Function\UpdateFunctionRequest;
use App\Http\Requests\Function\UpdateFunctionStatusRequest;
use App\Http\Resources\Function\FunctionResource;
use App\Models\SystemFunction;
use Illuminate\Http\JsonResponse;

class FunctionController extends Controller
{
    public function index(): JsonResponse
    {
        $functions = SystemFunction::query()
            ->orderBy('clase')
            ->orderBy('nombre_funcion')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Funciones del sistema obtenidas correctamente.',
            'data' => [
                'items' => FunctionResource::collection($functions)->resolve(),
            ],
        ]);
    }

    public function store(StoreFunctionRequest $request): JsonResponse
    {
        $function = SystemFunction::query()->create([
            'nombre_funcion' => trim($request->string('nombre_funcion')->toString()),
            'descripcion' => trim($request->string('descripcion')->toString()),
            'clase' => trim($request->string('clase')->toString()),
            'estado' => strtoupper($request->string('estado')->toString()),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Funcion del sistema creada correctamente.',
            'data' => [
                'function' => FunctionResource::make($function)->resolve(),
            ],
        ], 201);
    }

    public function show(SystemFunction $function): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Funcion del sistema obtenida correctamente.',
            'data' => [
                'function' => FunctionResource::make($function)->resolve(),
            ],
        ]);
    }

    public function update(UpdateFunctionRequest $request, SystemFunction $function): JsonResponse
    {
        $function->update([
            'nombre_funcion' => trim($request->string('nombre_funcion')->toString()),
            'descripcion' => trim($request->string('descripcion')->toString()),
            'clase' => trim($request->string('clase')->toString()),
            'estado' => strtoupper($request->string('estado')->toString()),
        ]);

        $function->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Funcion del sistema actualizada correctamente.',
            'data' => [
                'function' => FunctionResource::make($function)->resolve(),
            ],
        ]);
    }

    public function updateStatus(UpdateFunctionStatusRequest $request, SystemFunction $function): JsonResponse
    {
        $function->update([
            'estado' => strtoupper($request->string('estado')->toString()),
        ]);

        $function->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Estado de la funcion del sistema actualizado correctamente.',
            'data' => [
                'function' => FunctionResource::make($function)->resolve(),
            ],
        ]);
    }
}

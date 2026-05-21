<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreModuleRequest;
use App\Http\Requests\Project\UpdateModuleRequest;
use App\Http\Resources\Project\ModuleResource;
use App\Models\ModuleCatalog;
use App\Modules\Parameters\Services\ModuleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModuleController extends Controller
{
    public function __construct(private readonly ModuleService $moduleService) {}

    public function index(): JsonResponse
    {
        $this->moduleService->ensureGeneral();

        $items = ModuleCatalog::query()
            ->where('estado', '!=', 'DP')
            ->orderByRaw("CASE WHEN LOWER(TRIM(nombre_modulo)) = 'general' THEN 0 ELSE 1 END")
            ->orderBy('nombre_modulo')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Modulos obtenidos correctamente.',
            'data' => [
                'items' => ModuleResource::collection($items)->resolve(),
            ],
        ]);
    }

    public function active(): JsonResponse
    {
        $this->moduleService->ensureGeneral();

        $items = ModuleCatalog::query()
            ->where('estado', 'AC')
            ->orderByRaw("CASE WHEN LOWER(TRIM(nombre_modulo)) = 'general' THEN 0 ELSE 1 END")
            ->orderBy('nombre_modulo')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Modulos activos obtenidos correctamente.',
            'data' => [
                'items' => ModuleResource::collection($items)->resolve(),
            ],
        ]);
    }

    public function context(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Contexto de modulos obtenido correctamente.',
            'data' => $this->moduleService->context($request->user()),
        ]);
    }

    public function store(StoreModuleRequest $request): JsonResponse
    {
        $module = $this->moduleService->create($request, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Modulo creado correctamente.',
            'data' => [
                'module' => ModuleResource::make($module)->resolve(),
            ],
        ], 201);
    }

    public function show(ModuleCatalog $module): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Modulo obtenido correctamente.',
            'data' => [
                'module' => ModuleResource::make($module)->resolve(),
            ],
        ]);
    }

    public function update(UpdateModuleRequest $request, ModuleCatalog $module): JsonResponse
    {
        $module = $this->moduleService->update($request, $module, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Modulo actualizado correctamente.',
            'data' => [
                'module' => ModuleResource::make($module)->resolve(),
            ],
        ]);
    }

    public function destroy(Request $request, ModuleCatalog $module): JsonResponse
    {
        $module = $this->moduleService->delete($module, $request->user(), $request->ip());

        return response()->json([
            'success' => true,
            'message' => 'Modulo eliminado logicamente correctamente.',
            'data' => [
                'module' => ModuleResource::make($module)->resolve(),
            ],
        ]);
    }
}

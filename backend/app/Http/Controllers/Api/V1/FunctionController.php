<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Function\IndexFunctionRequest;
use App\Http\Requests\Function\StoreFunctionRequest;
use App\Http\Requests\Function\UpdateFunctionRequest;
use App\Http\Requests\Function\UpdateFunctionStatusRequest;
use App\Http\Resources\Function\FunctionResource;
use App\Models\SystemFunction;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;

class FunctionController extends Controller
{
    public function __construct(private readonly AuditService $auditService) {}

    public function context(): JsonResponse
    {
        $classes = SystemFunction::query()
            ->select('clase')
            ->whereNotNull('clase')
            ->distinct()
            ->orderBy('clase')
            ->pluck('clase')
            ->filter(fn (?string $value): bool => filled(trim((string) $value)))
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Contexto de funciones obtenido correctamente.',
            'data' => [
                'statuses' => [
                    ['code' => 'AC', 'label' => 'ACTIVO'],
                    ['code' => 'DC', 'label' => 'INACTIVO'],
                ],
                'available_classes' => $classes->map(fn (string $class): array => [
                    'value' => $class,
                    'label' => $class,
                ])->all(),
                'filters' => ['search', 'class', 'status', 'page', 'per_page'],
                'endpoints' => [
                    'list' => '/api/v1/functions',
                    'create' => '/api/v1/functions',
                    'show' => '/api/v1/functions/{id}',
                    'update' => '/api/v1/functions/{id}',
                    'update_status' => '/api/v1/functions/{id}/status',
                ],
            ],
        ]);
    }

    public function index(IndexFunctionRequest $request): JsonResponse
    {
        $query = SystemFunction::query();

        if ($request->filled('search')) {
            $search = trim($request->string('search')->toString());

            $query->where(function ($query) use ($search): void {
                $query->where('nombre_funcion', 'like', "%{$search}%")
                    ->orWhere('descripcion', 'like', "%{$search}%")
                    ->orWhere('clase', 'like', "%{$search}%");
            });
        }

        if ($request->filled('class')) {
            $query->where('clase', trim($request->string('class')->toString()));
        }

        if ($request->filled('status')) {
            $query->where('estado', strtoupper($request->string('status')->toString()));
        }

        $functions = $query
            ->orderBy('clase')
            ->orderBy('nombre_funcion')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return response()->json([
            'success' => true,
            'message' => 'Funciones del sistema obtenidas correctamente.',
            'data' => [
                'items' => FunctionResource::collection($functions->getCollection())->resolve(),
                'meta' => [
                    'current_page' => $functions->currentPage(),
                    'per_page' => $functions->perPage(),
                    'total' => $functions->total(),
                    'from' => $functions->firstItem(),
                    'to' => $functions->lastItem(),
                    'last_page' => $functions->lastPage(),
                    'has_more_pages' => $functions->hasMorePages(),
                ],
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
        $this->auditService->record($request->user(), $request->ip(), 'ADMINISTRACION: se creo la funcion '.$function->nombre_funcion);

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
        $this->auditService->record($request->user(), $request->ip(), 'ADMINISTRACION: se actualizo la funcion '.$function->nombre_funcion);

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
        $this->auditService->record($request->user(), $request->ip(), 'ADMINISTRACION: se cambio el estado de la funcion '.$function->nombre_funcion);

        return response()->json([
            'success' => true,
            'message' => 'Estado de la funcion del sistema actualizado correctamente.',
            'data' => [
                'function' => FunctionResource::make($function)->resolve(),
            ],
        ]);
    }
}

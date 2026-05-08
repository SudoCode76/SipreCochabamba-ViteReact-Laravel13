<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Role\IndexRoleRequest;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Http\Requests\Role\UpdateRoleStatusRequest;
use App\Http\Resources\Role\RoleResource;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class RoleController extends Controller
{
    public function context(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Contexto de roles obtenido correctamente.',
            'data' => [
                'statuses' => [
                    ['code' => 'AC', 'label' => 'ACTIVO'],
                    ['code' => 'DC', 'label' => 'INACTIVO'],
                ],
                'permissions' => [
                    'can_view' => true,
                    'can_create' => true,
                    'can_update' => true,
                    'can_assign_functions' => true,
                ],
                'filters' => ['search', 'status', 'page', 'per_page'],
                'endpoints' => [
                    'list' => '/api/v1/roles',
                    'create' => '/api/v1/roles',
                    'show' => '/api/v1/roles/{id}',
                    'update' => '/api/v1/roles/{id}',
                    'update_status' => '/api/v1/roles/{id}/status',
                    'permissions' => '/api/v1/roles/{id}/permissions',
                    'permissions_context' => '/api/v1/roles/{id}/permissions/context',
                ],
            ],
        ]);
    }

    public function index(IndexRoleRequest $request): JsonResponse
    {
        $query = Role::query();

        if ($request->filled('search')) {
            $search = trim($request->string('search')->toString());

            $query->where('nombre_rol', 'like', "%{$search}%");
        }

        if ($request->filled('status')) {
            $query->where('estado', strtoupper($request->string('status')->toString()));
        }

        $roles = $query
            ->orderBy('id_rol')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return response()->json([
            'success' => true,
            'message' => 'Roles obtenidos correctamente.',
            'data' => [
                'items' => RoleResource::collection($roles->getCollection())->resolve(),
                'meta' => [
                    'current_page' => $roles->currentPage(),
                    'per_page' => $roles->perPage(),
                    'total' => $roles->total(),
                    'from' => $roles->firstItem(),
                    'to' => $roles->lastItem(),
                    'last_page' => $roles->lastPage(),
                    'has_more_pages' => $roles->hasMorePages(),
                ],
            ],
        ]);
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = Role::query()->create([
            'nombre_rol' => trim($request->string('nombre_rol')->toString()),
            'estado' => strtoupper($request->string('estado')->toString()),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Rol creado correctamente.',
            'data' => [
                'role' => $this->serializeRole($role),
            ],
        ], 201);
    }

    public function show(Role $role): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Rol obtenido correctamente.',
            'data' => [
                'role' => $this->serializeRole($role),
            ],
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $previousName = $role->nombre_rol;
        $newName = trim($request->string('nombre_rol')->toString());
        $newStatus = strtoupper($request->string('estado')->toString());

        DB::transaction(function () use ($role, $newName, $newStatus): void {
            $role->update([
                'nombre_rol' => $newName,
                'estado' => $newStatus,
            ]);

            Permission::query()
                ->where('id_rol', $role->id_rol)
                ->update([
                    'nombre_rol' => $newName,
                ]);
        });

        $role->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Rol actualizado correctamente.',
            'data' => [
                'role' => $this->serializeRole($role),
                'previous_name' => $previousName,
            ],
        ]);
    }

    public function updateStatus(UpdateRoleStatusRequest $request, Role $role): JsonResponse
    {
        $role->update([
            'estado' => strtoupper($request->string('estado')->toString()),
        ]);

        $role->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Estado del rol actualizado correctamente.',
            'data' => [
                'role' => $this->serializeRole($role),
            ],
        ]);
    }

    private function serializeRole(Role $role): array
    {
        return RoleResource::make($role)->resolve();
    }
}

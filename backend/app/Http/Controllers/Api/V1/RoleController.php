<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Http\Requests\Role\UpdateRoleStatusRequest;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        $roles = Role::query()
            ->orderBy('id_rol')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Roles obtenidos correctamente.',
            'data' => [
                'items' => $roles->map(fn (Role $role): array => $this->serializeRole($role))->values(),
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
        return [
            'id' => $role->id_rol,
            'name' => $role->nombre_rol,
            'status' => $role->estado,
        ];
    }
}

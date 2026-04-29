<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\RolePermission\SyncRolePermissionsRequest;
use App\Http\Requests\RolePermission\UpdateRolePermissionRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemFunction;
use App\Services\RolePermission\RolePermissionSyncService;
use Illuminate\Http\JsonResponse;

class RolePermissionController extends Controller
{
    public function __construct(
        private readonly RolePermissionSyncService $rolePermissionSyncService,
    ) {}

    public function show(Role $role): JsonResponse
    {
        $role->load([
            'permissions' => fn ($query) => $query->active()
                ->with('systemFunction')
                ->orderBy('id_funcion'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Permisos del rol obtenidos correctamente.',
            'data' => [
                'role' => [
                    'id' => $role->id_rol,
                    'name' => $role->nombre_rol,
                    'status' => $role->estado,
                ],
                'permissions' => $role->permissions
                    ->map(fn (Permission $permission): array => [
                        'id' => $permission->id_permiso,
                        'description' => $permission->descripcion,
                        'status' => $permission->estado,
                        'function' => $permission->systemFunction ? [
                            'id' => $permission->systemFunction->id_funcion,
                            'name' => $permission->systemFunction->nombre_funcion,
                            'description' => $permission->systemFunction->descripcion,
                            'class' => $permission->systemFunction->clase,
                            'status' => $permission->systemFunction->estado,
                        ] : null,
                    ])
                    ->values()
                    ->all(),
            ],
        ]);
    }

    public function update(SyncRolePermissionsRequest $request, Role $role): JsonResponse
    {
        $functionIds = $request->validated('function_ids', []);

        $this->rolePermissionSyncService->sync($role, $functionIds);

        return $this->buildSyncResponse($role, 'Permisos del rol actualizados correctamente.');
    }

    public function sync(SyncRolePermissionsRequest $request, Role $role): JsonResponse
    {
        $functionIds = $request->validated('function_ids', []);

        $this->rolePermissionSyncService->sync($role, $functionIds);

        return $this->buildSyncResponse($role, 'Permisos del rol sincronizados correctamente.');
    }

    public function cloneFrom(Role $role, int $sourceRoleId): JsonResponse
    {
        $sourceRole = Role::query()->findOrFail($sourceRoleId);

        $functionIds = Permission::query()
            ->active()
            ->where('id_rol', $sourceRole->id_rol)
            ->orderBy('id_funcion')
            ->pluck('id_funcion')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();

        $this->rolePermissionSyncService->sync($role, $functionIds);

        $role->load([
            'permissions' => fn ($query) => $query->active()
                ->with('systemFunction')
                ->orderBy('id_funcion'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Permisos del rol clonados correctamente.',
            'data' => [
                'role' => $this->serializeRole($role),
                'source_role' => $this->serializeRole($sourceRole),
                'function_ids' => $role->permissions
                    ->pluck('id_funcion')
                    ->values()
                    ->all(),
                'permissions_count' => $role->permissions->count(),
            ],
        ]);
    }

    public function matrix(): JsonResponse
    {
        $roles = Role::query()
            ->active()
            ->orderBy('id_rol')
            ->get();

        $functions = SystemFunction::query()
            ->active()
            ->orderBy('clase')
            ->orderBy('nombre_funcion')
            ->get();

        $permissions = Permission::query()
            ->active()
            ->whereIn('id_rol', $roles->pluck('id_rol'))
            ->whereIn('id_funcion', $functions->pluck('id_funcion'))
            ->get()
            ->groupBy('id_funcion');

        return response()->json([
            'success' => true,
            'message' => 'Matriz de permisos obtenida correctamente.',
            'data' => [
                'roles' => $roles->map(fn (Role $role): array => [
                    'id' => $role->id_rol,
                    'name' => $role->nombre_rol,
                    'status' => $role->estado,
                ])->values()->all(),
                'functions' => $functions->map(function (SystemFunction $function) use ($permissions, $roles): array {
                    $assignedPermissions = $permissions->get($function->id_funcion, collect());
                    $assignedRoleIds = $assignedPermissions
                        ->pluck('id_rol')
                        ->unique()
                        ->values();

                    return [
                        'id' => $function->id_funcion,
                        'name' => $function->nombre_funcion,
                        'description' => $function->descripcion,
                        'class' => $function->clase,
                        'status' => $function->estado,
                        'assigned_role_ids' => $assignedRoleIds->all(),
                        'permissions' => $roles->map(fn (Role $role): array => [
                            'role_id' => $role->id_rol,
                            'allowed' => $assignedRoleIds->contains($role->id_rol),
                        ])->values()->all(),
                    ];
                })->values()->all(),
            ],
        ]);
    }

    public function attach(UpdateRolePermissionRequest $request, Role $role): JsonResponse
    {
        $functionId = (int) $request->validated('function_id');

        $this->rolePermissionSyncService->attach($role, $functionId);

        return response()->json([
            'success' => true,
            'message' => 'Permiso agregado correctamente al rol.',
            'data' => [
                'role' => $this->serializeRole($role),
                'function_id' => $functionId,
            ],
        ]);
    }

    public function detach(UpdateRolePermissionRequest $request, Role $role): JsonResponse
    {
        $functionId = (int) $request->validated('function_id');

        $this->rolePermissionSyncService->detach($role, $functionId);

        return response()->json([
            'success' => true,
            'message' => 'Permiso quitado correctamente del rol.',
            'data' => [
                'role' => $this->serializeRole($role),
                'function_id' => $functionId,
            ],
        ]);
    }

    private function buildSyncResponse(Role $role, string $message): JsonResponse
    {
        $role->load([
            'permissions' => fn ($query) => $query->active()
                ->with('systemFunction')
                ->orderBy('id_funcion'),
        ]);

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'role' => $this->serializeRole($role),
                'function_ids' => $role->permissions
                    ->pluck('id_funcion')
                    ->values()
                    ->all(),
                'permissions_count' => $role->permissions->count(),
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

<?php

namespace App\Services\RolePermission;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemFunction;
use Illuminate\Support\Facades\DB;

class RolePermissionSyncService
{
    public function sync(Role $role, array $functionIds): void
    {
        $normalizedFunctionIds = collect($functionIds)
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        $functions = SystemFunction::query()
            ->whereIn('id_funcion', $normalizedFunctionIds)
            ->get()
            ->keyBy('id_funcion');

        DB::transaction(function () use ($role, $normalizedFunctionIds, $functions): void {
            Permission::query()
                ->where('id_rol', $role->id_rol)
                ->when(
                    $normalizedFunctionIds->isNotEmpty(),
                    fn ($query) => $query->whereNotIn('id_funcion', $normalizedFunctionIds),
                    fn ($query) => $query,
                )
                ->where('estado', 'AC')
                ->update([
                    'estado' => 'DC',
                    'nombre_rol' => $role->nombre_rol,
                ]);

            if ($normalizedFunctionIds->isEmpty()) {
                return;
            }

            $existingPermissions = Permission::query()
                ->where('id_rol', $role->id_rol)
                ->whereIn('id_funcion', $normalizedFunctionIds)
                ->get()
                ->groupBy('id_funcion');

            $normalizedFunctionIds->each(function (int $functionId) use ($existingPermissions, $functions, $role): void {
                $permission = $existingPermissions->get($functionId)?->sortByDesc('id_permiso')->first();
                $function = $functions->get($functionId);

                if ($permission instanceof Permission) {
                    $permission->forceFill([
                        'nombre_rol' => $role->nombre_rol,
                        'descripcion' => $function?->descripcion,
                        'estado' => 'AC',
                    ])->save();

                    return;
                }

                Permission::query()->create([
                    'id_rol' => $role->id_rol,
                    'nombre_rol' => $role->nombre_rol,
                    'id_funcion' => $functionId,
                    'descripcion' => $function?->descripcion,
                    'estado' => 'AC',
                ]);
            });
        });
    }

    public function attach(Role $role, int $functionId): void
    {
        $currentFunctionIds = Permission::query()
            ->active()
            ->where('id_rol', $role->id_rol)
            ->pluck('id_funcion')
            ->map(static fn (mixed $id): int => (int) $id)
            ->push($functionId)
            ->unique()
            ->values()
            ->all();

        $this->sync($role, $currentFunctionIds);
    }

    public function detach(Role $role, int $functionId): void
    {
        $currentFunctionIds = Permission::query()
            ->active()
            ->where('id_rol', $role->id_rol)
            ->pluck('id_funcion')
            ->map(static fn (mixed $id): int => (int) $id)
            ->reject(static fn (int $id): bool => $id === $functionId)
            ->values()
            ->all();

        $this->sync($role, $currentFunctionIds);
    }
}

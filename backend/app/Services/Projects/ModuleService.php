<?php

namespace App\Services\Projects;

use App\Http\Requests\Project\StoreModuleRequest;
use App\Http\Requests\Project\UpdateModuleRequest;
use App\Models\ModuleCatalog;
use App\Models\ProjectItem;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Parameters\ParameterPermissionService;
use Illuminate\Validation\ValidationException;

class ModuleService
{
    public function __construct(private readonly ParameterPermissionService $permissions) {}

    public function context(User $user): array
    {
        return [
            'statuses' => [
                ['code' => 'AC', 'label' => 'ACTIVO'],
                ['code' => 'DC', 'label' => 'INACTIVO'],
            ],
            'permissions' => $this->permissions->resolve($user, 'MODULOS', canDelete: true),
        ];
    }

    public function ensureGeneral(): ModuleCatalog
    {
        $module = ModuleCatalog::query()
            ->whereRaw('LOWER(TRIM(nombre_modulo)) = ?', ['general'])
            ->first();

        if ($module instanceof ModuleCatalog) {
            if (strtoupper((string) $module->estado) !== 'AC') {
                $module->update(['estado' => 'AC']);
            }

            return $module->refresh();
        }

        return ModuleCatalog::query()->create([
            'nombre_modulo' => 'General',
            'estado' => 'AC',
            'id_usuario' => null,
            'fecha' => now()->toDateString(),
        ]);
    }

    public function create(StoreModuleRequest $request, User $user): ModuleCatalog
    {
        $name = trim($request->string('nombre_modulo')->toString());
        $this->ensureUniqueName($name);

        $module = ModuleCatalog::query()->create([
            'nombre_modulo' => $name,
            'estado' => strtoupper($request->string('estado')->toString()),
            'id_usuario' => $user->id_usuario,
            'fecha' => now()->toDateString(),
        ]);

        $this->registerAudit($user, $request->ip(), 'Registro de modulo '.$module->nombre_modulo);

        return $module;
    }

    public function update(UpdateModuleRequest $request, ModuleCatalog $module, User $user): ModuleCatalog
    {
        $name = trim($request->string('nombre_modulo')->toString());

        if ($name !== trim((string) $module->nombre_modulo)) {
            $this->ensureUniqueName($name, $module->id_modulo);
        }

        $module->update([
            'nombre_modulo' => $name,
            'estado' => strtoupper($request->string('estado')->toString()),
            'id_usuario' => $user->id_usuario,
        ]);

        $this->registerAudit($user, $request->ip(), 'Actualizacion de modulo '.$module->nombre_modulo);

        return $module->refresh();
    }

    public function delete(ModuleCatalog $module, User $user, ?string $ip): ModuleCatalog
    {
        $hasActiveItems = ProjectItem::query()
            ->where('id_modulo', $module->id_modulo)
            ->where('estado', 'AC')
            ->exists();

        if ($hasActiveItems) {
            throw ValidationException::withMessages([
                'module' => ['El modulo no puede eliminarse porque tiene items activos asociados.'],
            ]);
        }

        $module->update([
            'estado' => 'DP',
            'id_usuario' => $user->id_usuario,
        ]);

        $this->registerAudit($user, $ip, 'Eliminacion logica de modulo '.$module->nombre_modulo);

        return $module->refresh();
    }

    private function ensureUniqueName(string $name, ?int $ignoreId = null): void
    {
        $query = ModuleCatalog::query()
            ->where('estado', '!=', 'DP')
            ->whereRaw('LOWER(TRIM(nombre_modulo)) = ?', [mb_strtolower($name)]);

        if ($ignoreId !== null) {
            $query->where('id_modulo', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'nombre_modulo' => ['Ya existe un modulo con el mismo nombre.'],
            ]);
        }
    }

    private function registerAudit(User $user, ?string $ip, string $process): void
    {
        app(AuditService::class)->record($user, $ip, $process);
    }
}

<?php

namespace App\Services\Items;

use App\Http\Requests\Item\DeleteGroupRequest;
use App\Http\Requests\Item\StoreGroupDeleteAuthorizationRequest;
use App\Http\Requests\Item\StoreGroupRequest;
use App\Http\Requests\Item\UpdateGroupRequest;
use App\Models\Authorization;
use App\Models\GroupCatalog;
use App\Models\Item;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Parameters\ParameterPermissionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GroupService
{
    public function __construct(private readonly ParameterPermissionService $permissions) {}

    public function context(User $user): array
    {
        return [
            'statuses' => [
                ['code' => 'AC', 'label' => 'ACTIVO'],
                ['code' => 'DC', 'label' => 'INACTIVO'],
            ],
            'permissions' => $this->permissions->resolve($user, ['GRUPOS', 'GRUPO'], canDelete: true),
        ];
    }

    public function create(StoreGroupRequest $request, User $user): GroupCatalog
    {
        $code = trim($request->string('codigo_grupo')->toString());
        $name = trim($request->string('nombre_grupo')->toString());

        $this->ensureUniqueValues($code, $name);

        $group = GroupCatalog::query()->create([
            'codigo_grupo' => $code,
            'nombre_grupo' => $name,
            'estado' => strtoupper($request->string('estado')->toString()),
        ]);

        $this->registerAudit($user, $request->ip(), 'Registro de grupo '.$group->nombre_grupo);

        return $group;
    }

    public function update(UpdateGroupRequest $request, GroupCatalog $group, User $user): GroupCatalog
    {
        $code = trim($request->string('codigo_grupo')->toString());
        $name = trim($request->string('nombre_grupo')->toString());

        if ($name !== trim((string) $group->nombre_grupo)) {
            $this->ensureUniqueName($name, $group->id_grupo);
        }

        if ($code !== trim((string) $group->codigo_grupo)) {
            $this->ensureUniqueCode($code, $group->id_grupo);
        }

        $group->update([
            'codigo_grupo' => $code,
            'nombre_grupo' => $name,
            'estado' => strtoupper($request->string('estado')->toString()),
        ]);

        $this->registerAudit($user, $request->ip(), 'Actualizacion de grupo '.$group->nombre_grupo);

        return $group->refresh();
    }

    public function requestAuthorization(GroupCatalog $group, StoreGroupDeleteAuthorizationRequest $request, User $user): Authorization
    {
        return Authorization::query()->create([
            'id_elemento' => $group->id_grupo,
            'elemento' => $group->nombre_grupo,
            'tipo_elemento' => 'grupo',
            'tabla' => 'grupo',
            'solicitante' => $user->id_usuario,
            'estado' => 'PE',
            'nro_autorizacion' => $request->filled('nro_autorizacion') ? trim($request->string('nro_autorizacion')->toString()) : null,
            'fecha' => now(),
        ]);
    }

    public function authorizationStatus(GroupCatalog $group): array
    {
        $authorization = Authorization::query()
            ->where('tabla', 'grupo')
            ->where('id_elemento', $group->id_grupo)
            ->orderByDesc('id_autorizacion')
            ->first();

        if (! $authorization) {
            return [
                'status' => 'not_found',
                'usable' => false,
                'authorization' => null,
            ];
        }

        return [
            'status' => match ($authorization->estado) {
                'AP' => 'approved',
                'PE' => 'pending',
                default => 'not_usable',
            },
            'usable' => $authorization->estado === 'AP',
            'authorization' => [
                'id_autorizacion' => $authorization->id_autorizacion,
                'nro_autorizacion' => $authorization->nro_autorizacion,
                'estado' => $authorization->estado,
            ],
        ];
    }

    public function delete(GroupCatalog $group, DeleteGroupRequest $request, User $user): GroupCatalog
    {
        return DB::transaction(function () use ($group, $request, $user): GroupCatalog {
            $hasActiveItems = Item::query()
                ->where('grupo', $group->id_grupo)
                ->where('estado', 'AC')
                ->exists();

            if ($hasActiveItems) {
                throw ValidationException::withMessages([
                    'group' => ['El grupo no puede eliminarse porque tiene items activos asociados.'],
                ]);
            }

            $authorizationExists = Authorization::query()
                ->where('id_elemento', $group->id_grupo)
                ->where('nro_autorizacion', trim($request->string('autorizacion')->toString()))
                ->where('tabla', 'grupo')
                ->where('estado', 'AP')
                ->exists();

            if (! $authorizationExists) {
                throw ValidationException::withMessages([
                    'autorizacion' => ['No existe una autorizacion aprobada valida para eliminar este grupo.'],
                ]);
            }

            $group->update([
                'estado' => 'DP',
            ]);

            $this->registerAudit($user, $request->ip(), 'Eliminacion logica de grupo '.$group->nombre_grupo);

            return $group->refresh();
        });
    }

    private function ensureUniqueValues(string $code, string $name): void
    {
        $this->ensureUniqueCode($code);
        $this->ensureUniqueName($name);
    }

    private function ensureUniqueCode(string $code, ?int $ignoreId = null): void
    {
        $query = GroupCatalog::query()
            ->where('estado', '!=', 'DP')
            ->where('codigo_grupo', $code);

        if ($ignoreId !== null) {
            $query->where('id_grupo', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'codigo_grupo' => ['Ya existe un grupo con el mismo codigo.'],
            ]);
        }
    }

    private function ensureUniqueName(string $name, ?int $ignoreId = null): void
    {
        $query = GroupCatalog::query()
            ->where('estado', '!=', 'DP')
            ->where('nombre_grupo', $name);

        if ($ignoreId !== null) {
            $query->where('id_grupo', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'nombre_grupo' => ['Ya existe un grupo con el mismo nombre.'],
            ]);
        }
    }

    private function registerAudit(User $user, ?string $ip, string $process): void
    {
        app(AuditService::class)->record($user, $ip, $process);
    }
}

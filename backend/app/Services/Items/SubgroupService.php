<?php

namespace App\Services\Items;

use App\Http\Requests\Item\DeleteSubgroupRequest;
use App\Http\Requests\Item\StoreSubgroupDeleteAuthorizationRequest;
use App\Http\Requests\Item\StoreSubgroupRequest;
use App\Http\Requests\Item\UpdateSubgroupRequest;
use App\Models\AuditLog;
use App\Models\Authorization;
use App\Models\GroupCatalog;
use App\Models\Item;
use App\Models\SubgroupCatalog;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubgroupService
{
    public function context(User $user): array
    {
        return [
            'groups' => GroupCatalog::query()
                ->active()
                ->orderBy('nombre_grupo')
                ->get(['id_grupo', 'nombre_grupo'])
                ->map(fn (GroupCatalog $group): array => [
                    'id_grupo' => $group->id_grupo,
                    'nombre_grupo' => $group->nombre_grupo,
                ])
                ->values()
                ->all(),
            'statuses' => [
                ['code' => 'AC', 'label' => 'ACTIVO'],
                ['code' => 'DC', 'label' => 'INACTIVO'],
            ],
            'permissions' => [
                'can_view' => $user->isAdministrator(),
                'can_create' => $user->isAdministrator(),
                'can_update' => $user->isAdministrator(),
                'can_delete' => $user->isAdministrator(),
            ],
        ];
    }

    public function listForManagement(): Collection
    {
        return SubgroupCatalog::query()
            ->select('sub_grupo.*', 'grupo.nombre_grupo')
            ->join('grupo', 'grupo.id_grupo', '=', 'sub_grupo.id_grupo')
            ->where('sub_grupo.estado', '!=', 'DP')
            ->orderBy('sub_grupo.id_subgrupo')
            ->get();
    }

    public function listByGroup(int $groupId): Collection
    {
        return SubgroupCatalog::query()
            ->active()
            ->where('id_grupo', $groupId)
            ->orderBy('descripcion')
            ->get();
    }

    public function show(SubgroupCatalog $subgroup): SubgroupCatalog
    {
        return SubgroupCatalog::query()
            ->select('sub_grupo.*', 'grupo.nombre_grupo')
            ->join('grupo', 'grupo.id_grupo', '=', 'sub_grupo.id_grupo')
            ->where('sub_grupo.id_subgrupo', $subgroup->id_subgrupo)
            ->where('sub_grupo.estado', '!=', 'DP')
            ->firstOrFail();
    }

    public function create(StoreSubgroupRequest $request, User $user): SubgroupCatalog
    {
        $code = trim($request->string('codigo')->toString());
        $description = trim($request->string('descripcion')->toString());

        $this->ensureUniqueCode($code);
        $this->ensureUniqueDescription($description);

        $subgroup = SubgroupCatalog::query()->create([
            'codigo' => $code,
            'descripcion' => $description,
            'id_grupo' => $request->integer('id_grupo'),
            'estado' => strtoupper($request->string('estado')->toString()),
        ]);

        $this->registerAudit($user, $request->ip(), 'Registro de subgrupo '.$subgroup->descripcion);

        return $this->show($subgroup);
    }

    public function update(UpdateSubgroupRequest $request, SubgroupCatalog $subgroup, User $user): SubgroupCatalog
    {
        $currentSubgroup = $this->show($subgroup);
        $code = trim($request->string('codigo')->toString());
        $description = trim($request->string('descripcion')->toString());

        if ($code !== trim((string) $currentSubgroup->codigo)) {
            $this->ensureUniqueCode($code, $currentSubgroup->id_subgrupo);
        }

        if ($description !== trim((string) $currentSubgroup->descripcion)) {
            $this->ensureUniqueDescription($description, $currentSubgroup->id_subgrupo);
        }

        SubgroupCatalog::query()
            ->whereKey($currentSubgroup->id_subgrupo)
            ->update([
                'codigo' => $code,
                'descripcion' => $description,
                'id_grupo' => $request->integer('id_grupo'),
                'estado' => strtoupper($request->string('estado')->toString()),
            ]);

        $this->registerAudit($user, $request->ip(), 'Actualizacion de subgrupo '.$description);

        return $this->show($subgroup->refresh());
    }

    public function requestAuthorization(SubgroupCatalog $subgroup, StoreSubgroupDeleteAuthorizationRequest $request, User $user): Authorization
    {
        $currentSubgroup = $this->show($subgroup);

        return Authorization::query()->create([
            'id_elemento' => $currentSubgroup->id_subgrupo,
            'elemento' => $currentSubgroup->descripcion,
            'tipo_elemento' => 'subgrupo',
            'tabla' => 'sub_grupo',
            'solicitante' => $user->id_usuario,
            'estado' => 'PE',
            'nro_autorizacion' => $request->filled('nro_autorizacion') ? trim($request->string('nro_autorizacion')->toString()) : null,
            'fecha' => now(),
        ]);
    }

    public function authorizationStatus(SubgroupCatalog $subgroup): array
    {
        $currentSubgroup = $this->show($subgroup);

        $authorization = Authorization::query()
            ->where('tabla', 'sub_grupo')
            ->where('id_elemento', $currentSubgroup->id_subgrupo)
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

    public function delete(SubgroupCatalog $subgroup, DeleteSubgroupRequest $request, User $user): SubgroupCatalog
    {
        $currentSubgroup = $this->show($subgroup);

        return DB::transaction(function () use ($currentSubgroup, $request, $user): SubgroupCatalog {
            $hasActiveItems = Item::query()
                ->where('subgrupo', $currentSubgroup->id_subgrupo)
                ->where('estado', 'AC')
                ->exists();

            if ($hasActiveItems) {
                throw ValidationException::withMessages([
                    'subgroup' => ['El subgrupo no puede eliminarse porque tiene items activos asociados.'],
                ]);
            }

            $authorizationExists = Authorization::query()
                ->where('id_elemento', $currentSubgroup->id_subgrupo)
                ->where('nro_autorizacion', trim($request->string('autorizacion')->toString()))
                ->where('tabla', 'sub_grupo')
                ->where('estado', 'AP')
                ->exists();

            if (! $authorizationExists) {
                throw ValidationException::withMessages([
                    'autorizacion' => ['No existe una autorizacion aprobada valida para eliminar este subgrupo.'],
                ]);
            }

            SubgroupCatalog::query()
                ->whereKey($currentSubgroup->id_subgrupo)
                ->update([
                    'estado' => 'DP',
                ]);

            $this->registerAudit($user, $request->ip(), 'Eliminacion logica de subgrupo '.$currentSubgroup->descripcion);

            return SubgroupCatalog::query()->findOrFail($currentSubgroup->id_subgrupo);
        });
    }

    private function ensureUniqueCode(string $code, ?int $ignoreId = null): void
    {
        $query = SubgroupCatalog::query()
            ->where('estado', '!=', 'DP')
            ->where('codigo', $code);

        if ($ignoreId !== null) {
            $query->where('id_subgrupo', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'codigo' => ['Ya existe un subgrupo con el mismo codigo.'],
            ]);
        }
    }

    private function ensureUniqueDescription(string $description, ?int $ignoreId = null): void
    {
        $query = SubgroupCatalog::query()
            ->where('estado', '!=', 'DP')
            ->where('descripcion', $description);

        if ($ignoreId !== null) {
            $query->where('id_subgrupo', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'descripcion' => ['Ya existe un subgrupo con la misma descripcion.'],
            ]);
        }
    }

    private function registerAudit(User $user, ?string $ip, string $process): void
    {
        try {
            AuditLog::query()->create([
                'nombre_completo' => $user->funcionario,
                'fecha_hora' => now(),
                'ip' => $ip,
                'proceso' => $process,
            ]);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}

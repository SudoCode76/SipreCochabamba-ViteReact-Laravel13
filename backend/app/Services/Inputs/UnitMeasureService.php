<?php

namespace App\Services\Inputs;

use App\Http\Requests\Input\DeleteUnitMeasureRequest;
use App\Http\Requests\Input\StoreUnitMeasureDeleteAuthorizationRequest;
use App\Http\Requests\Input\StoreUnitMeasureRequest;
use App\Http\Requests\Input\UpdateUnitMeasureRequest;
use App\Models\Authorization;
use App\Models\UnitMeasure;
use App\Models\User;
use App\Services\AuditService;
use App\Modules\Parameters\Services\ParameterPermissionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UnitMeasureService
{
    public function __construct(private readonly ParameterPermissionService $permissions) {}

    public function context(User $user): array
    {
        return [
            'statuses' => [
                ['code' => 'AC', 'label' => 'ACTIVO'],
                ['code' => 'DC', 'label' => 'INACTIVO'],
            ],
            'permissions' => $this->permissions->resolve($user, ['UNIDAD_MEDIDA', 'UNIDAD'], canDelete: true),
        ];
    }

    public function create(StoreUnitMeasureRequest $request, User $user): UnitMeasure
    {
        $description = trim($request->string('descripcion')->toString());
        $abbreviation = trim($request->string('abreviatura')->toString());

        $this->ensureUniqueValues($description, $abbreviation);

        $unitMeasure = UnitMeasure::query()->create([
            'descripcion' => $description,
            'abreviatura' => $abbreviation,
            'estado' => strtoupper($request->string('estado')->toString()),
            'usuario' => (string) $user->id_usuario,
        ]);

        $this->registerAudit($user, $request->ip(), 'Registro de unidad de medida '.$unitMeasure->descripcion);

        return $unitMeasure;
    }

    public function update(UpdateUnitMeasureRequest $request, UnitMeasure $unitMeasure, User $user): UnitMeasure
    {
        $description = trim($request->string('descripcion')->toString());
        $abbreviation = trim($request->string('abreviatura')->toString());

        if ($description !== trim((string) $unitMeasure->descripcion)) {
            $this->ensureUniqueDescription($description, $unitMeasure->id_unidad_medida);
        }

        if ($abbreviation !== trim((string) $unitMeasure->abreviatura)) {
            $this->ensureUniqueAbbreviation($abbreviation, $unitMeasure->id_unidad_medida);
        }

        $unitMeasure->update([
            'descripcion' => $description,
            'abreviatura' => $abbreviation,
            'estado' => strtoupper($request->string('estado')->toString()),
        ]);

        $this->registerAudit($user, $request->ip(), 'Actualizacion de unidad de medida '.$unitMeasure->descripcion);

        return $unitMeasure->refresh();
    }

    public function requestAuthorization(UnitMeasure $unitMeasure, StoreUnitMeasureDeleteAuthorizationRequest $request, User $user): Authorization
    {
        return Authorization::query()->create([
            'id_elemento' => $unitMeasure->id_unidad_medida,
            'elemento' => $unitMeasure->descripcion,
            'tipo_elemento' => 'unidad_medida',
            'tabla' => 'unidad_medida',
            'solicitante' => $user->id_usuario,
            'estado' => 'PE',
            'nro_autorizacion' => $request->filled('nro_autorizacion') ? trim($request->string('nro_autorizacion')->toString()) : null,
            'fecha' => now(),
        ]);
    }

    public function authorizationStatus(UnitMeasure $unitMeasure): array
    {
        $authorization = Authorization::query()
            ->where('tabla', 'unidad_medida')
            ->where('id_elemento', $unitMeasure->id_unidad_medida)
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

    public function delete(UnitMeasure $unitMeasure, DeleteUnitMeasureRequest $request, User $user): UnitMeasure
    {
        return DB::transaction(function () use ($unitMeasure, $request, $user): UnitMeasure {
            $authorizationCode = trim((string) $request->input('autorizacion', ''));

            if ($authorizationCode !== '') {
                $authorizationExists = Authorization::query()
                    ->where('id_elemento', $unitMeasure->id_unidad_medida)
                    ->where('nro_autorizacion', $authorizationCode)
                    ->where('tabla', 'unidad_medida')
                    ->where('estado', 'AP')
                    ->exists();

                if (! $authorizationExists) {
                    throw ValidationException::withMessages([
                        'autorizacion' => ['No existe una autorizacion aprobada valida para eliminar esta unidad de medida.'],
                    ]);
                }
            }

            $unitMeasure->update([
                'estado' => 'DP',
            ]);

            $this->registerAudit($user, $request->ip(), 'Eliminacion logica de unidad de medida '.$unitMeasure->descripcion);

            return $unitMeasure->refresh();
        });
    }

    private function ensureUniqueValues(string $description, string $abbreviation): void
    {
        $this->ensureUniqueDescription($description);
        $this->ensureUniqueAbbreviation($abbreviation);
    }

    private function ensureUniqueDescription(string $description, ?int $ignoreId = null): void
    {
        $query = UnitMeasure::query()
            ->where('estado', '!=', 'DP')
            ->where('descripcion', $description);

        if ($ignoreId !== null) {
            $query->where('id_unidad_medida', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'descripcion' => ['Ya existe una unidad de medida con la misma descripcion.'],
            ]);
        }
    }

    private function ensureUniqueAbbreviation(string $abbreviation, ?int $ignoreId = null): void
    {
        $query = UnitMeasure::query()
            ->where('estado', '!=', 'DP')
            ->where('abreviatura', $abbreviation);

        if ($ignoreId !== null) {
            $query->where('id_unidad_medida', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'abreviatura' => ['Ya existe una unidad de medida con la misma abreviatura.'],
            ]);
        }
    }

    private function registerAudit(User $user, ?string $ip, string $process): void
    {
        app(AuditService::class)->record($user, $ip, $process);
    }
}

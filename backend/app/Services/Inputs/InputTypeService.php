<?php

namespace App\Services\Inputs;

use App\Http\Requests\Input\StoreInputTypeRequest;
use App\Http\Requests\Input\UpdateInputTypeRequest;
use App\Models\InputType;
use App\Models\User;
use App\Services\AuditService;
use App\Modules\Parameters\Services\ParameterPermissionService;
use Illuminate\Validation\ValidationException;

class InputTypeService
{
    public function __construct(private readonly ParameterPermissionService $permissions) {}

    public function context(User $user): array
    {
        return [
            'statuses' => [
                ['code' => 'AC', 'label' => 'ACTIVO'],
                ['code' => 'DC', 'label' => 'INACTIVO'],
            ],
            'permissions' => $this->permissions->resolve($user, ['TIPOINSUMO', 'TIPO_INSUMO', 'INPUT_TYPES']),
        ];
    }

    public function create(StoreInputTypeRequest $request, User $user): InputType
    {
        $description = trim($request->string('descripcion')->toString());
        $this->ensureUniqueDescription($description);

        $inputType = InputType::query()->create([
            'descripcion' => $description,
            'estado' => strtoupper($request->string('estado')->toString()),
        ]);

        $this->registerAudit($user, $request->ip(), 'Registro de tipo de insumo '.$inputType->descripcion);

        return $inputType;
    }

    public function update(UpdateInputTypeRequest $request, InputType $inputType, User $user): InputType
    {
        $description = trim($request->string('descripcion')->toString());

        if ($description !== trim((string) $inputType->descripcion)) {
            $this->ensureUniqueDescription($description, $inputType->id_tipo);
        }

        $inputType->update([
            'descripcion' => $description,
            'estado' => strtoupper($request->string('estado')->toString()),
        ]);

        $this->registerAudit($user, $request->ip(), 'Actualizacion de tipo de insumo '.$inputType->descripcion);

        return $inputType->refresh();
    }

    private function ensureUniqueDescription(string $description, ?int $ignoreId = null): void
    {
        $query = InputType::query()->where('descripcion', $description);

        if ($ignoreId !== null) {
            $query->where('id_tipo', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'descripcion' => ['Ya existe un tipo de insumo con la misma descripcion.'],
            ]);
        }
    }

    private function registerAudit(User $user, ?string $ip, string $process): void
    {
        app(AuditService::class)->record($user, $ip, $process);
    }
}

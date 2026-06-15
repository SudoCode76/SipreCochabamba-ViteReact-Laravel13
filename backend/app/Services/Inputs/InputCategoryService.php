<?php

namespace App\Services\Inputs;

use App\Http\Requests\Input\StoreInputCategoryRequest;
use App\Http\Requests\Input\UpdateInputCategoryRequest;
use App\Models\InputCategory;
use App\Models\User;
use App\Modules\Parameters\Services\ParameterPermissionService;
use App\Services\AuditService;
use Illuminate\Validation\ValidationException;

class InputCategoryService
{
    private const PERMISSION_KEYS = ['CATEGORIAINSUMO', 'CATEGORIA_INSUMO', 'INPUT_CATEGORIES', 'TIPOINSUMO', 'TIPO_INSUMO', 'INPUT_TYPES'];

    public function __construct(private readonly ParameterPermissionService $permissions) {}

    public function context(User $user): array
    {
        return [
            'statuses' => [
                ['code' => 'AC', 'label' => 'ACTIVO'],
                ['code' => 'DC', 'label' => 'INACTIVO'],
            ],
            'permissions' => $this->permissions->resolve($user, self::PERMISSION_KEYS),
        ];
    }

    public function create(StoreInputCategoryRequest $request, User $user): InputCategory
    {
        $description = trim($request->string('descripcion')->toString());
        $this->ensureUniqueDescription($description);

        $category = InputCategory::query()->create([
            'descripcion' => $description,
            'estado' => strtoupper($request->string('estado')->toString()),
            'usuario' => $user->id_usuario,
            'fecha' => now()->toDateString(),
        ]);

        $this->registerAudit($user, $request->ip(), 'Registro de categoria de insumo '.$category->descripcion);

        return $category;
    }

    public function update(UpdateInputCategoryRequest $request, InputCategory $category, User $user): InputCategory
    {
        $description = trim($request->string('descripcion')->toString());

        if ($description !== trim((string) $category->descripcion)) {
            $this->ensureUniqueDescription($description, $category->id_categoria);
        }

        $category->update([
            'descripcion' => $description,
            'estado' => strtoupper($request->string('estado')->toString()),
        ]);

        $this->registerAudit($user, $request->ip(), 'Actualizacion de categoria de insumo '.$category->descripcion);

        return $category->refresh();
    }

    private function ensureUniqueDescription(string $description, ?int $ignoreId = null): void
    {
        $query = InputCategory::query()->whereRaw('UPPER(TRIM(descripcion)) = ?', [strtoupper(trim($description))]);

        if ($ignoreId !== null) {
            $query->where('id_categoria', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'descripcion' => ['Ya existe una categoria de insumo con la misma descripcion.'],
            ]);
        }
    }

    private function registerAudit(User $user, ?string $ip, string $process): void
    {
        app(AuditService::class)->record($user, $ip, $process);
    }
}

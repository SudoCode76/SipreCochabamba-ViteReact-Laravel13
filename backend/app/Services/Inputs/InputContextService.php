<?php

namespace App\Services\Inputs;

use App\Models\InputCategory;
use App\Models\InputType;
use App\Models\UnitMeasure;
use App\Models\User;

class InputContextService
{
    public function __construct(private readonly InputPermissionService $permissions) {}

    public function execute(User $user): array
    {
        $types = InputType::query()
            ->active()
            ->orderBy('descripcion')
            ->get()
            ->map(fn (InputType $type): array => [
                'id_tipo' => $type->id_tipo,
                'descripcion' => $type->descripcion,
                'estado' => $type->estado,
            ])
            ->values()
            ->all();

        $unitMeasures = UnitMeasure::query()
            ->active()
            ->orderBy('descripcion')
            ->get()
            ->map(fn (UnitMeasure $unitMeasure): array => [
                'id_unidad_medida' => $unitMeasure->id_unidad_medida,
                'descripcion' => $unitMeasure->descripcion,
                'abreviatura' => $unitMeasure->abreviatura,
                'estado' => $unitMeasure->estado,
            ])
            ->values()
            ->all();

        $categories = InputCategory::query()
            ->active()
            ->orderBy('descripcion')
            ->get()
            ->map(fn (InputCategory $category): array => [
                'id_categoria' => $category->id_categoria,
                'descripcion' => $category->descripcion,
                'estado' => $category->estado,
            ])
            ->values()
            ->all();

        return [
            'types' => $types,
            'categories' => $categories,
            'unit_measures' => $unitMeasures,
            'statuses' => [
                ['code' => 'AC', 'label' => 'ACTIVO'],
                ['code' => 'DC', 'label' => 'INACTIVO'],
            ],
            'permissions' => [
                'can_view' => $this->permissions->allows($user, ['INSUMO', 'INPUTS', 'INPUTS_ADMIN', 'LISTA_INSUMO']),
                'can_create' => $this->permissions->allows($user, ['INSUMO', 'INPUTS_ADMIN']),
                'can_update' => $this->permissions->allows($user, ['INSUMO', 'INPUTS_ADMIN']),
                'can_delete' => $this->permissions->allows($user, ['INSUMO', 'INPUTS_ADMIN']),
                'can_view_history' => $this->permissions->allows($user, ['INSUMO', 'INPUTS_ADMIN']),
                'can_view_logs' => $this->permissions->allows($user, ['INSUMO', 'INPUTS_ADMIN']),
                'can_manage_quotes' => $this->permissions->allows($user, ['INSUMO', 'INPUTS_ADMIN']),
            ],
        ];
    }
}

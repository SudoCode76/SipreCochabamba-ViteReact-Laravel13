<?php

namespace App\Services\Inputs;

use App\Models\InputType;
use App\Models\UnitMeasure;
use App\Models\User;

class InputContextService
{
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

        return [
            'types' => $types,
            'unit_measures' => $unitMeasures,
            'statuses' => [
                ['code' => 'AC', 'label' => 'ACTIVO'],
                ['code' => 'DC', 'label' => 'INACTIVO'],
            ],
            'permissions' => [
                'can_view' => $user->isAdministrator(),
                'can_create' => $user->isAdministrator(),
                'can_update' => $user->isAdministrator(),
                'can_delete' => $user->isAdministrator(),
                'can_view_history' => $user->isAdministrator(),
                'can_view_logs' => $user->isAdministrator(),
                'can_manage_quotes' => $user->isAdministrator(),
            ],
        ];
    }
}

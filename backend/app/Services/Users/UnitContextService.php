<?php

namespace App\Services\Users;

use App\Models\Unit;
use App\Models\User;

class UnitContextService
{
    public function execute(User $user): array
    {
        return [
            'units' => Unit::query()
                ->active()
                ->orderBy('descripcion')
                ->get(['id_unidad', 'descripcion', 'estado'])
                ->map(fn (Unit $unit): array => [
                    'id_unidad' => $unit->id_unidad,
                    'descripcion' => $unit->descripcion,
                    'estado' => $unit->estado,
                ])
                ->values()
                ->all(),
            'statuses' => [
                ['code' => 'AC', 'label' => 'ACTIVO'],
                ['code' => 'DC', 'label' => 'INACTIVO'],
            ],
            'permissions' => [
                'can_view' => $user->isAdministrator(),
                'can_select' => $user->isAdministrator(),
            ],
        ];
    }
}

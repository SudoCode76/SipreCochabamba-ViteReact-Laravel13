<?php

namespace App\Services\InputRequests;

use App\Models\InputType;
use App\Models\UnitMeasure;
use App\Models\User;

class InputRequestContextService
{
    public function execute(User $user): array
    {
        return [
            'types' => InputType::query()
                ->active()
                ->orderBy('descripcion')
                ->get(['id_tipo', 'descripcion', 'estado'])
                ->toArray(),
            'unit_measures' => UnitMeasure::query()
                ->active()
                ->orderBy('descripcion')
                ->get(['id_unidad_medida', 'descripcion', 'abreviatura', 'estado'])
                ->toArray(),
            'approval_statuses' => [
                ['code' => 'PD', 'label' => 'PENDIENTE'],
                ['code' => 'AP', 'label' => 'APROBADO'],
                ['code' => 'RC', 'label' => 'RECHAZADO'],
            ],
            'permissions' => [
                'can_view' => $user->isAdministrator(),
                'can_create' => $user->isAdministrator(),
                'can_update' => $user->isAdministrator(),
                'can_view_quotes' => $user->isAdministrator(),
            ],
        ];
    }
}

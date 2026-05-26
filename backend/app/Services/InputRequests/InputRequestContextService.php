<?php

namespace App\Services\InputRequests;

use App\Models\InputType;
use App\Models\UnitMeasure;
use App\Models\User;
use App\Services\Inputs\InputPermissionService;

class InputRequestContextService
{
    public function __construct(private readonly InputPermissionService $permissions) {}

    public function execute(User $user): array
    {
        $canView = $this->permissions->allows($user, ['SOLICITUD', 'NUEVA_SOLICITUD', 'INPUT_QUOTES', 'SOLICITUD_INSUMO', 'LISTAR_SOLICITUD_INSUMO']);
        $canCreate = $this->permissions->allows($user, ['NUEVA_SOLICITUD', 'CREAR_SOLICITUD_INSUMO']);
        $canManage = $this->permissions->allows($user, ['GESTIONAR_SOLICITUD_INSUMO']);

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
                'can_view' => $canView,
                'can_create' => $canCreate,
                'can_update' => $canCreate,
                'can_manage' => $canManage,
                'can_revert' => $canManage,
                'can_view_quotes' => $canView || $canManage,
            ],
            'endpoints' => [
                'list' => '/api/v1/input-requests',
                'show' => '/api/v1/input-requests/{id}',
                'manage' => '/api/v1/solicitudes-insumo/{id}/gestion',
                'revert' => '/api/v1/solicitudes-insumo/{id}/revertir',
                'unit_measure_search' => '/api/v1/unidades-medida/search?q={query}',
            ],
        ];
    }
}

<?php

namespace App\Services\Projects;

use App\Models\User;
use App\Services\Permissions\PermissionResolverService;

class ProjectPermissionService
{
    public function __construct(private readonly PermissionResolverService $permissions) {}

    public function resolve(User $user): array
    {
        if ($user->isAdministrator()) {
            return [
                'can_view' => true,
                'can_create' => true,
                'can_edit' => true,
                'can_sync_items' => true,
                'can_recalculate_budget' => true,
                'can_view_reports' => true,
                'can_view_budget_by_group' => true,
                'can_view_incidence_summary' => true,
                'can_view_general_budget' => true,
                'can_view_input_breakdown' => true,
                'can_view_inputs_report' => true,
                'can_view_unit_prices' => true,
            ];
        }

        $resolved = $this->permissions->resolveMap($user, 'PROYECTO', [
            'can_view' => ['INDEX', 'PROYECTO'],
            'can_create' => ['REGISTRAR_PROYECTO', 'NUEVO_PROYECTO'],
            'can_edit' => ['EDITAR_PROYECTO', 'EDIT_PROYECTO'],
            'can_sync_items' => ['REGISTRAR_ITEM_PROYECTO'],
            'can_recalculate_budget' => ['RECAL_PRESUPUESTO_RUBRO'],
            'can_view_budget_by_group' => ['PRESUPUESTO_RUBRO'],
            'can_view_incidence_summary' => ['RESUMEN_INCIDENCIA'],
            'can_view_general_budget' => ['PRESUPUESTO_GENERAL', 'PRESUPUESTO_RUBRO'],
            'can_view_input_breakdown' => ['DESGLOSE_ITEMS', 'CALCULAR_DESGLOSE'],
            'can_view_inputs_report' => ['REPORTE_INSUMOS', 'DESGLOSE_ITEMS'],
            'can_view_unit_prices' => ['PRECIOS_UNITARIOS', 'IMPRIMIR_PRECIOS_UNITARIOS', 'PRESUPUESTO_RUBRO'],
        ]);

        $resolved['can_view_reports'] = $resolved['can_view_budget_by_group']
            || $resolved['can_view_incidence_summary']
            || $resolved['can_view_general_budget']
            || $resolved['can_view_input_breakdown']
            || $resolved['can_view_inputs_report']
            || $resolved['can_view_unit_prices'];

        return $resolved;
    }
}

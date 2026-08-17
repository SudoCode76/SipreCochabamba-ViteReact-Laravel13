<?php

namespace App\Modules\Projects\Services;

use App\Models\ProjectSignableReport;
use App\Models\User;
use App\Modules\Security\Services\PermissionResolverService;
use Illuminate\Support\Collection;

class ProjectSignableReportService
{
    public const REPORTS = [
        'budget_by_group' => [
            'scope' => 'project',
            'permission' => 'can_view_budget_by_group',
            'function' => 'PRESUPUESTO_RUBRO',
        ],
        'budget_recalculation' => [
            'scope' => 'project',
            'permission' => 'can_recalculate_budget',
            'function' => 'RECAL_PRESUPUESTO_RUBRO',
        ],
        'incidence_summary' => [
            'scope' => 'project',
            'permission' => 'can_view_incidence_summary',
            'function' => 'RESUMEN_INCIDENCIA',
        ],
        'general_budget' => [
            'scope' => 'project',
            'permission' => 'can_view_general_budget',
            'function' => 'PRESUPUESTO_GENERAL',
        ],
        'input_breakdown' => [
            'scope' => 'project',
            'permission' => 'can_view_input_breakdown',
            'function' => 'DESGLOSE_ITEMS',
        ],
        'inputs_report' => [
            'scope' => 'project',
            'permission' => 'can_view_inputs_report',
            'function' => 'REPORTE_INSUMOS',
        ],
        'grouped_inputs_report' => [
            'scope' => 'project',
            'permission' => 'can_view_inputs_report',
            'function' => 'REPORTE_INSUMOS',
        ],
        'unit_prices' => [
            'scope' => 'project',
            'permission' => 'can_view_unit_prices',
            'function' => 'PRECIOS_UNITARIOS',
        ],
        'specifications' => [
            'scope' => 'project',
            'permission' => 'can_view',
            'function' => 'INDEX',
        ],
        'item_unit_price_analysis' => [
            'scope' => 'item',
        ],
        'item_price_recalculation' => [
            'scope' => 'item',
        ],
        'item_material_breakdown' => [
            'scope' => 'item',
        ],
        'item_labor_breakdown' => [
            'scope' => 'item',
        ],
        'item_machinery_breakdown' => [
            'scope' => 'item',
        ],
        'item_breakdown_recalculation' => [
            'scope' => 'item',
        ],
    ];

    public function __construct(
        private readonly ProjectPermissionService $projectPermissionService,
        private readonly PermissionResolverService $permissionResolverService,
    ) {}

    public function list(User $user): Collection
    {
        $permissions = $this->projectPermissionService->resolve($user);
        $canManage = $this->canManage($user);
        $canSignReports = $this->hasSigningPermission($user);

        return ProjectSignableReport::query()
            ->orderBy('id')
            ->get()
            ->map(function (ProjectSignableReport $report) use ($permissions, $canManage, $canSignReports): array {
                $definition = self::REPORTS[$report->report_key] ?? [];
                $reportPermission = (string) ($definition['permission'] ?? '');
                $scope = (string) ($definition['scope'] ?? 'project');
                $canViewReport = $scope === 'item'
                    ? true
                    : (bool) ($permissions[$reportPermission] ?? false);

                return [
                    'report_key' => $report->report_key,
                    'scope' => $scope,
                    'name' => $report->name,
                    'description' => $report->description,
                    'is_enabled' => (bool) $report->is_enabled,
                    'requires_finalized_project' => $scope !== 'item',
                    'validity_days' => $report->validity_days === null ? null : (int) $report->validity_days,
                    'can_view' => $canManage || $canViewReport,
                    'can_sign' => (bool) $report->is_enabled
                        && $canSignReports
                        && $canViewReport,
                    'available_actions' => [
                        'manage' => $canManage,
                    ],
                ];
            });
    }

    public function canManage(?User $user): bool
    {
        return $this->permissionResolverService->allows($user, 'ADMINISTRADOR', ['FIRMAS_DIGITALES', 'REPORTES_FIRMABLES'])
            || $this->permissionResolverService->allows($user, 'PROYECTO', ['CONFIGURAR_FIRMAS', 'FIRMAS_DIGITALES']);
    }

    public function canSign(?User $user, string $reportKey): bool
    {
        return $this->canSignReport($user, $reportKey, $this->hasSigningPermission($user));
    }

    public function canSignPhysically(?User $user, string $reportKey): bool
    {
        $hasPermission = $user?->isAdministrator()
            || $this->permissionResolverService->allows($user, 'PROYECTO', ['FIRMAR_REPORTES_FISICOS']);

        return $this->canSignReport($user, $reportKey, $hasPermission);
    }

    public function findEnabled(string $reportKey): ?ProjectSignableReport
    {
        return ProjectSignableReport::query()
            ->where('report_key', $reportKey)
            ->where('is_enabled', true)
            ->first();
    }

    public function projectStatusAllowsSigning(ProjectSignableReport $report, bool $projectIsFinalized): bool
    {
        return $projectIsFinalized;
    }

    private function hasSigningPermission(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->isAdministrator()
            || $this->permissionResolverService->allows($user, 'PROYECTO', [
                'FIRMAR_REPORTES',
                'FIRMAR_PRESUPUESTO_GENERAL',
                'FIRMAS_DIGITALES',
            ]);
    }

    private function canSignReport(?User $user, string $reportKey, bool $hasSigningPermission): bool
    {
        if (! $user || ! $hasSigningPermission || ! array_key_exists($reportKey, self::REPORTS)) {
            return false;
        }

        $definition = self::REPORTS[$reportKey];

        if (($definition['scope'] ?? 'project') === 'item') {
            return true;
        }

        $permissions = $this->projectPermissionService->resolve($user);

        return (bool) ($permissions[$definition['permission']] ?? false);
    }
}

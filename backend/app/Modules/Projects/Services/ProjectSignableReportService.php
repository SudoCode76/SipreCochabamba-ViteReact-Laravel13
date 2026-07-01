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
            'permission' => 'can_view_budget_by_group',
            'function' => 'PRESUPUESTO_RUBRO',
        ],
        'budget_recalculation' => [
            'permission' => 'can_recalculate_budget',
            'function' => 'RECAL_PRESUPUESTO_RUBRO',
        ],
        'incidence_summary' => [
            'permission' => 'can_view_incidence_summary',
            'function' => 'RESUMEN_INCIDENCIA',
        ],
        'general_budget' => [
            'permission' => 'can_view_general_budget',
            'function' => 'PRESUPUESTO_GENERAL',
        ],
        'input_breakdown' => [
            'permission' => 'can_view_input_breakdown',
            'function' => 'DESGLOSE_ITEMS',
        ],
        'inputs_report' => [
            'permission' => 'can_view_inputs_report',
            'function' => 'REPORTE_INSUMOS',
        ],
        'grouped_inputs_report' => [
            'permission' => 'can_view_inputs_report',
            'function' => 'REPORTE_INSUMOS',
        ],
        'unit_prices' => [
            'permission' => 'can_view_unit_prices',
            'function' => 'PRECIOS_UNITARIOS',
        ],
        'specifications' => [
            'permission' => 'can_view',
            'function' => 'INDEX',
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

                return [
                    'report_key' => $report->report_key,
                    'name' => $report->name,
                    'description' => $report->description,
                    'is_enabled' => (bool) $report->is_enabled,
                    'requires_finalized_project' => (bool) $report->requires_finalized_project,
                    'can_sign' => (bool) $report->is_enabled
                        && $canSignReports
                        && (bool) ($permissions[$reportPermission] ?? false),
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
        if (! $user) {
            return false;
        }

        if (! array_key_exists($reportKey, self::REPORTS)) {
            return false;
        }

        if (! $this->hasSigningPermission($user)) {
            return false;
        }

        $permissions = $this->projectPermissionService->resolve($user);

        return (bool) ($permissions[self::REPORTS[$reportKey]['permission']] ?? false);
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
        return ! $report->requires_finalized_project || $projectIsFinalized;
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
}

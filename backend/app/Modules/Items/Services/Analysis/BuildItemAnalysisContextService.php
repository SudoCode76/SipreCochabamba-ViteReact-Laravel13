<?php

namespace App\Modules\Items\Services\Analysis;

use App\Models\GroupCatalog;
use App\Models\SubgroupCatalog;
use App\Models\UnitMeasure;
use App\Models\User;

class BuildItemAnalysisContextService
{
    public function __construct(
        private readonly ItemAnalysisPermissionService $permissionService,
    ) {}

    public function execute(User $user, string $mode = 'fndr'): array
    {
        $groups = GroupCatalog::query()
            ->active()
            ->orderBy('nombre_grupo')
            ->get();

        $subgroups = SubgroupCatalog::query()
            ->active()
            ->orderBy('id_grupo')
            ->orderBy('descripcion')
            ->get();

        $unitMeasures = UnitMeasure::query()
            ->active()
            ->orderBy('descripcion')
            ->get();

        $permissions = $this->permissionService->resolve($user, $mode);
        $mode = strtolower($mode);
        $meta = $this->resolveModeMeta($mode);

        return [
            'groups' => $groups->map(fn (GroupCatalog $group): array => [
                'id' => $group->id_grupo,
                'name' => $group->nombre_grupo,
                'code' => $group->codigo_grupo,
                'status' => $group->estado,
            ])->values()->all(),
            'subgroups' => $subgroups->map(fn (SubgroupCatalog $subgroup): array => [
                'id' => $subgroup->id_subgrupo,
                'group_id' => $subgroup->id_grupo,
                'description' => $subgroup->descripcion,
                'code' => $subgroup->codigo,
                'status' => $subgroup->estado,
            ])->values()->all(),
            'subgroups_by_group' => $subgroups
                ->groupBy('id_grupo')
                ->map(fn ($items) => $items->map(fn (SubgroupCatalog $subgroup): array => [
                    'id' => $subgroup->id_subgrupo,
                    'group_id' => $subgroup->id_grupo,
                    'description' => $subgroup->descripcion,
                    'code' => $subgroup->codigo,
                    'status' => $subgroup->estado,
                ])->values()->all())
                ->all(),
            'statuses' => [
                ['code' => 'AC', 'label' => 'ACTIVO'],
                ['code' => 'DC', 'label' => 'INACTIVO'],
            ],
            'unit_measures' => $unitMeasures->map(fn (UnitMeasure $unitMeasure): array => [
                'id' => $unitMeasure->id_unidad_medida,
                'description' => $unitMeasure->descripcion,
                'abbreviation' => $unitMeasure->abreviatura,
                'status' => $unitMeasure->estado,
            ])->values()->all(),
            'permissions' => $permissions,
            'meta' => [
                'screen' => $meta['screen'],
                'mode' => $mode,
                'price_formula' => $meta['price_formula'],
                'supports_dependent_subgroup_filter' => true,
                'endpoints' => [
                    'list' => $meta['list_endpoint'],
                    'create' => '/api/v1/items',
                    'subgroups' => '/api/v1/subgroups?group_id={group_id}',
                    'price_analysis' => '/api/v1/items/{id}/price-analysis?mode='.$mode,
                    'price_recalculation' => '/api/v1/items/{id}/price-recalculation?mode='.$mode,
                ],
                'filters' => ['search', 'group_id', 'subgroup_id', 'status', 'page', 'per_page'],
            ],
        ];
    }

    private function resolveModeMeta(string $mode): array
    {
        return match ($mode) {
            'general' => [
                'screen' => 'items',
                'price_formula' => 'GENERAL',
                'list_endpoint' => '/api/v1/items',
            ],
            'fndr' => [
                'screen' => 'items/fndr',
                'price_formula' => 'FNDR',
                'list_endpoint' => '/api/v1/items/fndr',
            ],
            'upre' => [
                'screen' => 'items/upre',
                'price_formula' => 'UPRE',
                'list_endpoint' => '/api/v1/items/upre',
            ],
            'fps' => [
                'screen' => 'items/fps',
                'price_formula' => 'FPS',
                'list_endpoint' => '/api/v1/items/fps',
            ],
            'obras' => [
                'screen' => 'items/obras',
                'price_formula' => 'OBRAS',
                'list_endpoint' => '/api/v1/items/obras',
            ],
            'proman' => [
                'screen' => 'items/proman',
                'price_formula' => 'PROMAN',
                'list_endpoint' => '/api/v1/items/proman',
            ],
            default => throw new \InvalidArgumentException('Modo de contexto no soportado.'),
        };
    }
}

<?php

namespace App\Services\Items\Analysis;

use App\Models\User;
use App\Services\Permissions\PermissionResolverService;

class ItemAnalysisPermissionService
{
    public function __construct(private readonly PermissionResolverService $permissions) {}

    public function resolve(User $user, string $mode = 'fndr'): array
    {
        $config = $this->modeConfig($mode);

        if ($user->isAdministrator()) {
            return [
                'can_view' => true,
                'can_create' => true,
                'can_edit' => true,
                'can_view_price_analysis' => true,
                'can_recalculate' => true,
            ];
        }

        return [
            'can_view' => $this->permissions->allows($user, 'ITEMS', [$config['screen_function'], $config['legacy_screen_function']]),
            'can_create' => $this->permissions->allows($user, 'ITEMS', ['REGISTRAR_ITEM']),
            'can_edit' => $this->permissions->allows($user, 'ITEMS', ['EDITAR_ITEM', 'REGISTRAR_ITEM']),
            'can_view_price_analysis' => $this->permissions->allows($user, 'ITEMS', [$config['analysis_function'], 'ANALISIS_PRECIO']),
            'can_recalculate' => $this->permissions->allows($user, 'ITEMS', [$config['recalculation_function']]),
        ];
    }

    private function modeConfig(string $mode): array
    {
        return match (strtolower($mode)) {
            'general' => [
                'screen_function' => 'INDEX',
                'legacy_screen_function' => 'ITEMS',
                'analysis_function' => 'ANALISIS_PRECIO',
                'recalculation_function' => 'RECALCULAR_ITEM',
            ],
            'fndr' => [
                'screen_function' => 'FNDR',
                'legacy_screen_function' => 'FNDR',
                'analysis_function' => 'ANALISIS_PRECIO_FNDR',
                'recalculation_function' => 'RECALCULAR_ITEM_FNDR',
            ],
            'upre' => [
                'screen_function' => 'UPRE',
                'legacy_screen_function' => 'UPRE',
                'analysis_function' => 'ANALISIS_PRECIO_UPRE',
                'recalculation_function' => 'RECALCULAR_ITEM_UPRE',
            ],
            'fps' => [
                'screen_function' => 'FPS',
                'legacy_screen_function' => 'FPS',
                'analysis_function' => 'ANALISIS_PRECIO_FPS',
                'recalculation_function' => 'RECALCULAR_ITEM_FPS',
            ],
            'obras' => [
                'screen_function' => 'OBRAS_PUBLICAS',
                'legacy_screen_function' => 'OBRAS_PUBLICAS',
                'analysis_function' => 'ANALISIS_PRECIO_OBRAS',
                'recalculation_function' => 'RECALCULAR_ITEM_OBRAS',
            ],
            'proman' => [
                'screen_function' => 'PROMAN',
                'legacy_screen_function' => 'PROMAN',
                'analysis_function' => 'ANALISIS_PRECIO_PROMAN',
                'recalculation_function' => 'RECALCULAR_ITEM_PROMAN',
            ],
            default => throw new \InvalidArgumentException('Modo de items no soportado.'),
        };
    }
}

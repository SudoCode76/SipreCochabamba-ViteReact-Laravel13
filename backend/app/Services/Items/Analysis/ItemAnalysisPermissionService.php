<?php

namespace App\Services\Items\Analysis;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ItemAnalysisPermissionService
{
    public function resolve(User $user, string $mode = 'fndr'): array
    {
        $config = $this->modeConfig($mode);

        if ($user->isAdministrator()) {
            return [
                'can_view' => true,
                'can_create' => true,
                'can_view_price_analysis' => true,
                'can_recalculate' => true,
            ];
        }

        return [
            'can_view' => true,
            'can_create' => $this->has($user, 'ITEMS', ['REGISTRAR_ITEM']),
            'can_view_price_analysis' => $this->has($user, 'ITEMS', [$config['analysis_function'], 'ANALISIS_PRECIO']),
            'can_recalculate' => $this->has($user, 'ITEMS', [$config['recalculation_function']]),
        ];
    }

    private function modeConfig(string $mode): array
    {
        return match (strtolower($mode)) {
            'general' => [
                'screen_function' => 'INDEX',
                'analysis_function' => 'ANALISIS_PRECIO',
                'recalculation_function' => 'RECALCULAR_ITEM',
            ],
            'fndr' => [
                'screen_function' => 'FNDR',
                'analysis_function' => 'ANALISIS_PRECIO_FNDR',
                'recalculation_function' => 'RECALCULAR_ITEM_FNDR',
            ],
            'upre' => [
                'screen_function' => 'UPRE',
                'analysis_function' => 'ANALISIS_PRECIO_UPRE',
                'recalculation_function' => 'RECALCULAR_ITEM_UPRE',
            ],
            'fps' => [
                'screen_function' => 'FPS',
                'analysis_function' => 'ANALISIS_PRECIO_FPS',
                'recalculation_function' => 'RECALCULAR_ITEM_FPS',
            ],
            'obras' => [
                'screen_function' => 'OBRAS_PUBLICAS',
                'analysis_function' => 'ANALISIS_PRECIO_OBRAS',
                'recalculation_function' => 'RECALCULAR_ITEM_OBRAS',
            ],
            'proman' => [
                'screen_function' => 'PROMAN',
                'analysis_function' => 'ANALISIS_PRECIO_PROMAN',
                'recalculation_function' => 'RECALCULAR_ITEM_PROMAN',
            ],
            default => throw new \InvalidArgumentException('Modo de items no soportado.'),
        };
    }

    private function has(User $user, string $className, array $functionNames): bool
    {
        return $user->activePermissions()
            ->whereHas('systemFunction', function (Builder $query) use ($className, $functionNames): void {
                $query->whereRaw('UPPER(TRIM(clase)) = ?', [strtoupper(trim($className))])
                    ->where(function (Builder $query) use ($functionNames): void {
                        foreach ($functionNames as $functionName) {
                            $query->orWhereRaw('UPPER(TRIM(nombre_funcion)) = ?', [strtoupper(trim($functionName))]);
                        }
                    });
            })
            ->exists();
    }
}

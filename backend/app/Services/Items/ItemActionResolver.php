<?php

namespace App\Services\Items;

class ItemActionResolver
{
    public function resolve(string $status, bool $canRecalculate = true): array
    {
        $isActive = strtoupper(trim($status)) === 'AC';

        return [
            'edit' => true,
            'materials' => $isActive,
            'labor' => $isActive,
            'machinery' => $isActive,
            'files' => $isActive,
            'price_analysis' => $isActive,
            'price_recalculation' => $isActive && $canRecalculate,
            'material_breakdown' => $isActive,
            'labor_breakdown' => $isActive,
            'tools_breakdown' => $isActive,
            'breakdown_recalculation' => $isActive && $canRecalculate,
        ];
    }

    public function statusLabel(string $status): string
    {
        return strtoupper(trim($status)) === 'AC'
            ? 'HABILITADO'
            : 'INHABILITADO';
    }
}

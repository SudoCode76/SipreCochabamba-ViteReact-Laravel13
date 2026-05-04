<?php

namespace App\Services\Projects;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ProjectPermissionService
{
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
            ];
        }

        return [
            'can_view' => $this->has($user, 'PROYECTO', ['INDEX']),
            'can_create' => $this->has($user, 'PROYECTO', ['REGISTRAR_PROYECTO']),
            'can_edit' => $this->has($user, 'PROYECTO', ['EDITAR_PROYECTO']),
            'can_sync_items' => $this->has($user, 'PROYECTO', ['REGISTRAR_ITEM_PROYECTO']),
            'can_recalculate_budget' => $this->has($user, 'PROYECTO', ['RECAL_PRESUPUESTO_RUBRO']),
            'can_view_reports' => $this->has($user, 'PROYECTO', ['RESUMEN_INCIDENCIA', 'PRESUPUESTO_RUBRO', 'DESGLOSE_ITEMS']),
        ];
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

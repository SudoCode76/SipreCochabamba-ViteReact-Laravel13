<?php

namespace App\Services\Projects;

use App\Models\Project;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class ProjectListService
{
    public function execute(array $filters): LengthAwarePaginator
    {
        $query = Project::query()
            ->with(['creator', 'requester']);

        $order = strtolower((string) ($filters['order'] ?? 'legacy'));

        if ($order === 'recent') {
            $query->orderByDesc('fecha')
                ->orderByDesc('id_proyecto');
        } elseif ($order === 'oldest') {
            $query->orderBy('fecha')
                ->orderBy('id_proyecto');
        } else {
            $query->orderBy('nombre_proyecto');
        }

        if (! empty($filters['search'])) {
            $search = Str::lower(trim((string) $filters['search']));

            $query->where(function ($query) use ($search): void {
                $query->whereRaw('LOWER(TRIM(nombre_proyecto)) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(TRIM(ubicacion)) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(TRIM(observaciones)) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(TRIM(nombre_responsable)) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(TRIM(CAST(responsable AS CHAR))) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(TRIM(CAST(solicitante AS CHAR))) LIKE ?', ["%{$search}%"])
                    ->orWhereHas('requester', function ($query) use ($search): void {
                        $query->whereRaw('LOWER(TRIM(funcionario)) LIKE ?', ["%{$search}%"])
                            ->orWhereRaw('LOWER(TRIM(username)) LIKE ?', ["%{$search}%"]);
                    });
            });
        }

        if (! empty($filters['status'])) {
            $query->where('estado', strtoupper((string) $filters['status']));
        }

        if (! empty($filters['approval_status'])) {
            $query->where('aprobado', strtoupper((string) $filters['approval_status']));
        }

        if (! empty($filters['responsable_id'])) {
            $query->where('responsable', (string) ((int) $filters['responsable_id']));
        }

        if (! empty($filters['solicitante_id'])) {
            $query->where('solicitante', (int) $filters['solicitante_id']);
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15))->withQueryString();
    }
}

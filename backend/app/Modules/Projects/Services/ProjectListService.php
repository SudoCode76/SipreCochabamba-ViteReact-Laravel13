<?php

namespace App\Modules\Projects\Services;

use App\Models\Project;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class ProjectListService
{
    public function execute(array $filters): LengthAwarePaginator
    {
        $query = Project::query()
            ->with(['creator', 'requester'])
            ->where(function ($query): void {
                $query->where('es_plantilla', false)
                    ->orWhereNull('es_plantilla');
            });

        $order = strtolower((string) ($filters['order'] ?? 'legacy'));

        if ($order === 'recent') {
            $query->orderByDesc('id_proyecto');
        } elseif ($order === 'oldest') {
            $query->orderBy('id_proyecto');
        } else {
            $query->orderBy('nombre_proyecto');
        }

        if (! empty($filters['search'])) {
            $terms = Str::of((string) $filters['search'])
                ->lower()
                ->squish()
                ->explode(' ')
                ->filter()
                ->values();

            $query->where(function ($query) use ($terms): void {
                foreach ($terms as $term) {
                    $query->where(function ($query) use ($term): void {
                        $like = "%{$term}%";

                        $query->whereRaw('LOWER(TRIM(nombre_proyecto)) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(TRIM(ubicacion)) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(TRIM(observaciones)) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(TRIM(nombre_responsable)) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(TRIM(CAST(responsable AS CHAR))) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(TRIM(CAST(solicitante AS CHAR))) LIKE ?', [$like])
                            ->orWhereHas('requester', function ($query) use ($like): void {
                                $query->whereRaw('LOWER(TRIM(funcionario)) LIKE ?', [$like])
                                    ->orWhereRaw('LOWER(TRIM(username)) LIKE ?', [$like]);
                            });
                    });
                }
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

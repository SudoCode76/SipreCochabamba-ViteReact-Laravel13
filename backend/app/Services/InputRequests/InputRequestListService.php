<?php

namespace App\Services\InputRequests;

use App\Models\InputRequest;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class InputRequestListService
{
    public function execute(array $filters): LengthAwarePaginator
    {
        $query = InputRequest::query()
            ->with(['type', 'unitMeasure', 'requester'])
            ->orderBy('descripcion');

        $this->applyFilters($query, $filters);

        return $query->paginate((int) ($filters['per_page'] ?? 15))->withQueryString();
    }

    public function executeManagement(array $filters): LengthAwarePaginator
    {
        $query = InputRequest::query()
            ->with(['type', 'unitMeasure', 'requester'])
            ->orderBy('estado_aprobacion')
            ->orderBy('id_solicitud');

        $this->applyFilters($query, $filters);

        return $query->paginate((int) ($filters['per_page'] ?? 15))->withQueryString();
    }

    private function applyFilters($query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $search = Str::lower(trim((string) $filters['search']));
            $query->where(function ($query) use ($search): void {
                $query->whereRaw('LOWER(TRIM(descripcion)) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(TRIM(ubicacion)) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(TRIM(justificacion)) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw("LOWER(TRIM(COALESCE(notificacion, ''))) LIKE ?", ["%{$search}%"]);
            });
        }

        if (! empty($filters['approval_status'])) {
            $query->where('estado_aprobacion', strtoupper((string) $filters['approval_status']));
        }

        if (! empty($filters['type_id'])) {
            $query->where('tipo', (int) $filters['type_id']);
        }

        if (! empty($filters['unit_measure_id'])) {
            $query->where('unidad_medida', (int) $filters['unit_measure_id']);
        }

        if (! empty($filters['requester_id'])) {
            $query->where('usuario_solicitante', (int) $filters['requester_id']);
        }
    }
}

<?php

namespace App\Services\Inputs;

use App\Models\Authorization;
use App\Models\Input;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class InputListService
{
    public function execute(array $filters): LengthAwarePaginator
    {
        $authorizationKeyName = (new Authorization)->getKeyName();

        $query = Input::query()
            ->select('insumo.*')
            ->selectSub(
                Authorization::query()
                    ->select($authorizationKeyName)
                    ->whereColumn('id_elemento', 'insumo.id_insumo')
                    ->where('estado', 'PE')
                    ->where(function ($query): void {
                        $query->where('tabla', 'insumo')
                            ->orWhere('tipo_elemento', 'insumo');
                    })
                    ->orderByDesc('fecha')
                    ->orderByDesc($authorizationKeyName)
                    ->limit(1),
                'pending_delete_authorization_id'
            )
            ->with(['type', 'category', 'unitMeasure', 'creator']);

        $order = strtolower((string) ($filters['order'] ?? 'legacy'));

        if ($order === 'recent') {
            $query->orderByDesc('fecha')
                ->orderByDesc('id_insumo');
        } elseif ($order === 'oldest') {
            $query->orderBy('fecha')
                ->orderBy('id_insumo');
        } else {
            $query->orderBy('tipo')
                ->orderBy('descripcion');
        }

        $search = $filters['search'] ?? $filters['description'] ?? null;

        if (is_string($search) && trim($search) !== '') {
            $terms = Str::of($search)
                ->lower()
                ->squish()
                ->explode(' ')
                ->filter()
                ->values();

            $query->where(function ($query) use ($terms): void {
                foreach ($terms as $term) {
                    $query->whereRaw('LOWER(TRIM(descripcion)) LIKE ?', ["%{$term}%"]);
                }
            });
        }

        if (! empty($filters['status'])) {
            $query->where('estado', strtoupper((string) $filters['status']));
        } else {
            $query->where(function ($query): void {
                $query->where('estado', '!=', 'DP')
                    ->orWhereNull('estado');
            });
        }

        if (! empty($filters['type_id'])) {
            $query->where('tipo', (int) $filters['type_id']);
        }

        if (! empty($filters['category_id'])) {
            $query->where('id_categoria', (int) $filters['category_id']);
        }

        if (! empty($filters['unit_measure_id'])) {
            $query->where('unidad_medida', (int) $filters['unit_measure_id']);
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15))->withQueryString();
    }
}

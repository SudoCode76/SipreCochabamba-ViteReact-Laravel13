<?php

namespace App\Services\Inputs;

use App\Models\Authorization;
use App\Models\Input;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InputListService
{
    public function execute(array $filters): LengthAwarePaginator
    {
        $authorizationKeyName = (new Authorization)->getKeyName();

        $query = Input::query()
            ->select('insumo.*')
            ->selectRaw($this->normalizedDescriptionExpression('insumo.descripcion').' as duplicate_key')
            ->selectSub($this->duplicateCountQuery($filters), 'duplicate_count')
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
        $this->applyFilters($query, $filters);

        if ($this->wantsDuplicates($filters)) {
            $query->whereIn(DB::raw($this->normalizedDescriptionExpression('insumo.descripcion')), $this->duplicateKeysQuery($filters))
                ->orderByRaw($this->normalizedDescriptionExpression('insumo.descripcion'))
                ->orderBy('id_insumo');
        } elseif ($order === 'recent') {
            $query->orderByDesc('fecha')
                ->orderByDesc('id_insumo');
        } elseif ($order === 'oldest') {
            $query->orderBy('fecha')
                ->orderBy('id_insumo');
        } else {
            $query->orderBy('tipo')
                ->orderBy('descripcion');
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15))->withQueryString();
    }

    private function applyFilters(Builder $query, array $filters): void
    {
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
    }

    private function duplicateKeysQuery(array $filters): Builder
    {
        $query = Input::query()
            ->selectRaw($this->normalizedDescriptionExpression('descripcion'))
            ->whereNotNull('descripcion')
            ->whereRaw("TRIM(descripcion) <> ''");

        $this->applyFilters($query, $filters);

        return $query
            ->groupByRaw($this->normalizedDescriptionExpression('descripcion'))
            ->havingRaw('COUNT(*) > 1');
    }

    private function duplicateCountQuery(array $filters): Builder
    {
        $query = Input::query()
            ->from('insumo as duplicate_inputs')
            ->selectRaw('COUNT(*)')
            ->whereRaw($this->normalizedDescriptionExpression('duplicate_inputs.descripcion').' = '.$this->normalizedDescriptionExpression('insumo.descripcion'));

        $this->applyDuplicateFilters($query, $filters);

        return $query;
    }

    private function applyDuplicateFilters(Builder $query, array $filters): void
    {
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
                    $query->whereRaw('LOWER(TRIM(duplicate_inputs.descripcion)) LIKE ?', ["%{$term}%"]);
                }
            });
        }

        if (! empty($filters['status'])) {
            $query->where('duplicate_inputs.estado', strtoupper((string) $filters['status']));
        } else {
            $query->where(function ($query): void {
                $query->where('duplicate_inputs.estado', '!=', 'DP')
                    ->orWhereNull('duplicate_inputs.estado');
            });
        }

        if (! empty($filters['type_id'])) {
            $query->where('duplicate_inputs.tipo', (int) $filters['type_id']);
        }

        if (! empty($filters['category_id'])) {
            $query->where('duplicate_inputs.id_categoria', (int) $filters['category_id']);
        }

        if (! empty($filters['unit_measure_id'])) {
            $query->where('duplicate_inputs.unidad_medida', (int) $filters['unit_measure_id']);
        }
    }

    private function normalizedDescriptionExpression(string $column): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return "LOWER(TRIM(REPLACE(REPLACE(REPLACE({$column}, '    ', ' '), '   ', ' '), '  ', ' ')))";
        }

        return "LOWER(TRIM(REGEXP_REPLACE({$column}, '\\s+', ' ', 'g')))";
    }

    private function wantsDuplicates(array $filters): bool
    {
        return filter_var($filters['duplicates'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }
}

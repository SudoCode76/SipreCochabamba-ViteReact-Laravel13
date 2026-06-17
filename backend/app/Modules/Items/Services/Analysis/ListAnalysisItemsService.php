<?php

namespace App\Modules\Items\Services\Analysis;

use App\Models\Item;
use App\Modules\Items\Services\ItemFreshnessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ListAnalysisItemsService
{
    public function __construct(
        private readonly ItemPriceAnalysisService $priceAnalysisService,
        private readonly ItemAnalysisListPriceService $itemAnalysisListPriceService,
        private readonly ItemFreshnessService $itemFreshnessService,
    ) {}

    public function execute(array $filters, string $mode = 'fndr'): LengthAwarePaginator
    {
        $query = Item::query()
            ->with(['groupCatalog', 'subgroupCatalog', 'unitMeasure']);

        $query->join('grupo as groupCatalog', 'groupCatalog.id_grupo', '=', 'item.grupo')
            ->join('sub_grupo as subgroupCatalog', 'subgroupCatalog.id_subgrupo', '=', 'item.subgrupo');

        $order = strtolower((string) ($filters['order'] ?? 'legacy'));

        if ($this->wantsDuplicates($filters)) {
            // The duplicate view applies its own order after all filters are in place.
        } elseif ($order === 'missing_specifications') {
            $query->orderByRaw("CASE WHEN item.especificacion IS NULL OR TRIM(item.especificacion) = '' THEN 0 ELSE 1 END")
                ->orderBy('groupCatalog.nombre_grupo')
                ->orderBy('subgroupCatalog.descripcion')
                ->orderBy('item.item')
                ->orderBy('item.id_item');
        } elseif ($order === 'recent') {
            $query->orderByDesc('item.fecha_item')
                ->orderByDesc('item.id_item');
        } elseif ($order === 'oldest') {
            $query->orderBy('item.fecha_item')
                ->orderBy('item.id_item');
        } else {
            $query->orderBy('groupCatalog.nombre_grupo')
                ->orderBy('subgroupCatalog.descripcion')
                ->orderBy('item.item')
                ->orderBy('item.id_item');
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

                        $query->whereRaw('LOWER(TRIM(item.item)) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(TRIM(item.estado)) LIKE ?', [$like]);
                    });
                }
            });
        }

        if (! empty($filters['group_id'])) {
            $query->where('item.grupo', (int) $filters['group_id']);
        }

        if (! empty($filters['subgroup_id'])) {
            $query->where('item.subgrupo', (int) $filters['subgroup_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('item.estado', strtoupper((string) $filters['status']));
        }

        if (! empty($filters['review_days'])) {
            $this->itemFreshnessService->applyReviewDaysFilter($query, (int) $filters['review_days']);
        } elseif (($filters['freshness'] ?? null) === 'outdated') {
            $this->itemFreshnessService->applyOutdatedFilter($query);
        }

        if ($this->wantsDuplicates($filters)) {
            $query->whereIn(DB::raw($this->normalizedItemExpression('item.item')), $this->duplicateKeysQuery($filters))
                ->orderByRaw($this->normalizedItemExpression('item.item'))
                ->orderBy('item.id_item');
        }

        $items = $query
            ->select('item.*')
            ->selectRaw($this->normalizedItemExpression('item.item').' as duplicate_key')
            ->selectSub($this->duplicateCountQuery($filters), 'duplicate_count')
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();

        $items->setCollection(
            $items->getCollection()->map(function (Item $item) use ($mode): array {
                $calculatedPrice = $this->calculatedPrice($item, $mode);
                $freshness = $this->itemFreshnessService->describe($item);

                return [
                    'id_item' => $item->id_item,
                    'name' => $item->item,
                    'calculated_price' => $calculatedPrice['value'],
                    'calculated_price_label' => $calculatedPrice['label'],
                    'status' => $item->estado,
                    'is_duplicate' => (int) ($item->duplicate_count ?? 0) > 1,
                    'duplicate_count' => (int) ($item->duplicate_count ?? 0),
                    'duplicate_key' => $item->duplicate_key,
                    'specification' => $item->especificacion,
                    'sheet' => $item->ficha,
                    ...$freshness,
                    'group' => $item->groupCatalog ? [
                        'id' => $item->groupCatalog->id_grupo,
                        'name' => $item->groupCatalog->nombre_grupo,
                        'code' => $item->groupCatalog->codigo_grupo,
                    ] : null,
                    'subgroup' => $item->subgroupCatalog ? [
                        'id' => $item->subgroupCatalog->id_subgrupo,
                        'description' => $item->subgroupCatalog->descripcion,
                        'code' => $item->subgroupCatalog->codigo,
                    ] : null,
                    'unit_measure' => $item->unitMeasure ? [
                        'id' => $item->unitMeasure->id_unidad_medida,
                        'description' => $item->unitMeasure->descripcion,
                        'abbreviation' => $item->unitMeasure->abreviatura,
                    ] : null,
                ];
            })
        );

        return $items;
    }

    private function calculatedPrice(Item $item, string $mode): array
    {
        if (in_array(strtolower($mode), ['general', 'fndr'], true)) {
            return $this->itemAnalysisListPriceService->resolve($item, $mode);
        }

        $price = round($this->priceAnalysisService->calculateCurrentPrice($item, $mode), 4);

        return [
            'value' => $price,
            'label' => number_format($price, 2, ',', '.'),
        ];
    }

    private function duplicateKeysQuery(array $filters): Builder
    {
        $query = Item::query()
            ->selectRaw($this->normalizedItemExpression('item.item'))
            ->whereNotNull('item.item')
            ->whereRaw("TRIM(item.item) <> ''");

        $this->applyDuplicateFilters($query, $filters, 'item');

        return $query
            ->groupByRaw($this->normalizedItemExpression('item.item'))
            ->havingRaw('COUNT(*) > 1');
    }

    private function duplicateCountQuery(array $filters): Builder
    {
        $query = Item::query()
            ->from('item as duplicate_items')
            ->selectRaw('COUNT(*)')
            ->whereRaw($this->normalizedItemExpression('duplicate_items.item').' = '.$this->normalizedItemExpression('item.item'));

        $this->applyDuplicateFilters($query, $filters, 'duplicate_items');

        return $query;
    }

    private function applyDuplicateFilters(Builder $query, array $filters, string $table): void
    {
        if (! empty($filters['search'])) {
            $terms = Str::of((string) $filters['search'])
                ->lower()
                ->squish()
                ->explode(' ')
                ->filter()
                ->values();

            $query->where(function ($query) use ($terms, $table): void {
                foreach ($terms as $term) {
                    $query->where(function ($query) use ($term, $table): void {
                        $like = "%{$term}%";

                        $query->whereRaw("LOWER(TRIM({$table}.item)) LIKE ?", [$like])
                            ->orWhereRaw("LOWER(TRIM({$table}.estado)) LIKE ?", [$like]);
                    });
                }
            });
        }

        if (! empty($filters['group_id'])) {
            $query->where("{$table}.grupo", (int) $filters['group_id']);
        }

        if (! empty($filters['subgroup_id'])) {
            $query->where("{$table}.subgrupo", (int) $filters['subgroup_id']);
        }

        if (! empty($filters['status'])) {
            $query->where("{$table}.estado", strtoupper((string) $filters['status']));
        }

        if (! empty($filters['review_days'])) {
            $this->applyDuplicateReviewDaysFilter($query, (int) $filters['review_days'], $table);
        } elseif (($filters['freshness'] ?? null) === 'outdated') {
            $this->applyDuplicateOutdatedFilter($query, $table);
        }
    }

    private function applyDuplicateOutdatedFilter(Builder $query, string $table): void
    {
        $query->where("{$table}.estado", 'AC')
            ->where(function (Builder $query) use ($table): void {
                $query->whereNull("{$table}.fecha_item")
                    ->orWhereDate("{$table}.fecha_item", '<', today()->subDays(ItemFreshnessService::OUTDATED_AFTER_DAYS));
            });
    }

    private function applyDuplicateReviewDaysFilter(Builder $query, int $days, string $table): void
    {
        $days = in_array($days, ItemFreshnessService::REVIEW_FILTER_DAYS, true)
            ? $days
            : ItemFreshnessService::OUTDATED_AFTER_DAYS;

        $query->where("{$table}.estado", 'AC');

        if ($days === 180) {
            $query->where(function (Builder $query) use ($days, $table): void {
                $query->whereNull("{$table}.fecha_item")
                    ->orWhereDate("{$table}.fecha_item", '<=', today()->subDays($days));
            });

            return;
        }

        $query->whereNotNull("{$table}.fecha_item")
            ->whereDate("{$table}.fecha_item", '>=', today()->subDays($days));
    }

    private function normalizedItemExpression(string $column): string
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

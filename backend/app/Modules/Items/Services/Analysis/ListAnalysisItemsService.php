<?php

namespace App\Modules\Items\Services\Analysis;

use App\Models\Item;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class ListAnalysisItemsService
{
    public function __construct(
        private readonly ItemPriceAnalysisService $priceAnalysisService,
        private readonly ItemAnalysisListPriceService $itemAnalysisListPriceService,
    ) {}

    public function execute(array $filters, string $mode = 'fndr'): LengthAwarePaginator
    {
        $query = Item::query()
            ->with(['groupCatalog', 'subgroupCatalog', 'unitMeasure']);

        $query->join('grupo as groupCatalog', 'groupCatalog.id_grupo', '=', 'item.grupo')
            ->join('sub_grupo as subgroupCatalog', 'subgroupCatalog.id_subgrupo', '=', 'item.subgrupo');

        $order = strtolower((string) ($filters['order'] ?? 'legacy'));

        if ($order === 'recent') {
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
            $search = Str::lower(trim((string) $filters['search']));
            $query->where(function ($query) use ($search): void {
                $query->whereRaw('LOWER(TRIM(item.item)) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(TRIM(item.estado)) LIKE ?', ["%{$search}%"]);
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

        $items = $query->select('item.*')->paginate((int) ($filters['per_page'] ?? 15))->withQueryString();

        $items->setCollection(
            $items->getCollection()->map(function (Item $item) use ($mode): array {
                $calculatedPrice = $this->calculatedPrice($item, $mode);

                return [
                    'id_item' => $item->id_item,
                    'name' => $item->item,
                    'calculated_price' => $calculatedPrice['value'],
                    'calculated_price_label' => $calculatedPrice['label'],
                    'status' => $item->estado,
                    'specification' => $item->especificacion,
                    'sheet' => $item->ficha,
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
}

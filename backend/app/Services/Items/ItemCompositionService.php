<?php

namespace App\Services\Items;

use App\Models\Input;
use App\Models\Item;
use App\Models\ItemInput;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ItemCompositionService
{
    public function __construct(
        private readonly ItemActionResolver $itemActionResolver,
    ) {}

    public function context(Item $item, array $permissions): array
    {
        $item->loadMissing(['groupCatalog', 'subgroupCatalog', 'unitMeasure']);

        return [
            'item' => [
                'id_item' => $item->id_item,
                'name' => $item->item,
                'status' => $item->estado,
                'status_label' => $this->itemActionResolver->statusLabel($item->estado),
                'price' => $item->precio,
                'unit_measure' => $item->unitMeasure ? [
                    'id' => $item->unitMeasure->id_unidad_medida,
                    'description' => $item->unitMeasure->descripcion,
                    'abbreviation' => $item->unitMeasure->abreviatura,
                ] : null,
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
            ],
            'permissions' => $permissions,
            'available_actions' => $this->itemActionResolver->resolve($item->estado, $permissions['can_recalculate'] ?? true),
            'meta' => [
                'can_edit' => true,
                'can_add_inputs' => strtoupper($item->estado) === 'AC',
                'can_recalculate' => strtoupper($item->estado) === 'AC' && ($permissions['can_recalculate'] ?? true),
                'can_view_analysis' => strtoupper($item->estado) === 'AC',
                'can_view_breakdowns' => strtoupper($item->estado) === 'AC',
            ],
        ];
    }

    public function composition(Item $item): array
    {
        return [
            'materials' => $this->listByType($item, 1),
            'labor' => $this->listByType($item, 2),
            'machinery' => $this->listByType($item, 3),
            'totals' => [
                'materials' => $this->totalByType($item, 1),
                'labor' => $this->totalByType($item, 2),
                'machinery' => $this->totalByType($item, 3),
                'global' => $this->globalTotal($item),
            ],
        ];
    }

    public function listByType(Item $item, int $type): array
    {
        return ItemInput::query()
            ->with(['input.unitMeasure'])
            ->where('id_item', $item->id_item)
            ->where('item_insumo.estado', 'AC')
            ->whereHas('input', function ($query) use ($type): void {
                $query->where('tipo', $type)
                    ->where('estado', 'AC');
            })
            ->join('insumo', 'insumo.id_insumo', '=', 'item_insumo.id_insumo')
            ->orderBy('insumo.descripcion')
            ->select('item_insumo.*')
            ->get()
            ->map(fn (ItemInput $itemInput): array => $this->serializeItemInput($itemInput))
            ->values()
            ->all();
    }

    public function create(Item $item, int $type, int $inputId, float $quantity, User $user): array
    {
        $this->ensureItemCanMutate($item);

        $input = $this->resolveValidInput($inputId, $type);

        return DB::transaction(function () use ($item, $type, $input, $quantity, $user): array {
            $itemInput = ItemInput::query()
                ->where('id_item', $item->id_item)
                ->where('id_insumo', $input->id_insumo)
                ->first();

            if ($itemInput) {
                $itemInput->update([
                    'cantidad' => $quantity,
                    'estado' => 'AC',
                    'id_usuario' => $user->id_usuario,
                    'fecha' => now()->toDateString(),
                    'tipo' => $type,
                ]);

                return $this->serializeItemInput($itemInput->refresh()->load('input.unitMeasure'));
            }

            $created = ItemInput::query()->create([
                'id_insumo' => $input->id_insumo,
                'id_item' => $item->id_item,
                'estado' => 'AC',
                'id_usuario' => $user->id_usuario,
                'cantidad' => $quantity,
                'fecha' => now()->toDateString(),
                'tipo' => $type,
            ]);

            return $this->serializeItemInput($created->load('input.unitMeasure'));
        });
    }

    public function update(Item $item, int $type, ItemInput $itemInput, float $quantity): array
    {
        $this->ensureItemCanMutate($item);
        $this->ensureItemInputBelongsToItemAndType($item, $itemInput, $type);

        $itemInput->update([
            'cantidad' => $quantity,
        ]);

        return $this->serializeItemInput($itemInput->refresh()->load('input.unitMeasure'));
    }

    public function delete(Item $item, int $type, ItemInput $itemInput): void
    {
        $this->ensureItemCanMutate($item);
        $this->ensureItemInputBelongsToItemAndType($item, $itemInput, $type);

        $itemInput->update([
            'estado' => 'DC',
        ]);
    }

    public function syncType(Item $item, int $type, array $rows, array $deletedInputIds, User $user): array
    {
        $this->ensureItemCanMutate($item);

        $normalizedRows = collect($rows)
            ->map(fn (array $row): array => [
                'id_insumo' => (int) $row['id_insumo'],
                'cantidad' => (float) $row['cantidad'],
            ])
            ->keyBy('id_insumo')
            ->values();

        $normalizedRows->each(fn (array $row) => $this->resolveValidInput($row['id_insumo'], $type));

        $deletedIds = collect($deletedInputIds)
            ->map(fn ($inputId): int => (int) $inputId)
            ->filter()
            ->unique()
            ->values();

        $submittedIds = $normalizedRows->pluck('id_insumo');

        DB::transaction(function () use ($item, $type, $user, $normalizedRows, $deletedIds, $submittedIds): void {
            if ($deletedIds->isNotEmpty()) {
                ItemInput::query()
                    ->where('id_item', $item->id_item)
                    ->whereIn('id_insumo', $deletedIds)
                    ->whereHas('input', fn ($query) => $query->where('tipo', $type))
                    ->update(['estado' => 'DC']);
            }

            ItemInput::query()
                ->where('id_item', $item->id_item)
                ->where('estado', 'AC')
                ->whereHas('input', fn ($query) => $query->where('tipo', $type))
                ->when($submittedIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id_insumo', $submittedIds))
                ->update(['estado' => 'DC']);

            foreach ($normalizedRows as $row) {
                ItemInput::query()->updateOrCreate(
                    [
                        'id_item' => $item->id_item,
                        'id_insumo' => $row['id_insumo'],
                    ],
                    [
                        'cantidad' => $row['cantidad'],
                        'estado' => 'AC',
                        'id_usuario' => $user->id_usuario,
                        'fecha' => now()->toDateString(),
                        'tipo' => $type,
                    ],
                );
            }
        });

        $items = $this->listByType($item->refresh(), $type);
        $blockTotal = round(collect($items)->sum('parcial'), 4);

        if ($type === 1) {
            $item->forceFill(['precio' => $blockTotal])->save();
        }

        return [
            'items' => $items,
            'totals' => [
                'block' => $blockTotal,
                'global' => $this->globalTotal($item),
            ],
            'item' => [
                'id_item' => $item->id_item,
                'price' => (float) $item->precio,
            ],
        ];
    }

    public function totalByType(Item $item, int $type): float
    {
        return round(collect($this->listByType($item, $type))->sum('parcial'), 4);
    }

    public function globalTotal(Item $item): float
    {
        return round(
            $this->totalByType($item, 1)
            + $this->totalByType($item, 2)
            + $this->totalByType($item, 3),
            4,
        );
    }

    private function serializeItemInput(ItemInput $itemInput): array
    {
        $input = $itemInput->input;
        $unitPrice = (float) ($input?->precio ?? 0);
        $quantity = (float) $itemInput->cantidad;

        return [
            'id_item_insumo' => $itemInput->id_item_insumo,
            'id_insumo' => $itemInput->id_insumo,
            'descripcion' => $input?->descripcion,
            'unidad' => $input?->unitMeasure?->abreviatura ?? $input?->unitMeasure?->descripcion,
            'cantidad' => round($quantity, 4),
            'precio_unitario' => round($unitPrice, 4),
            'parcial' => round($quantity * $unitPrice, 4),
            'estado' => $itemInput->estado,
            'tipo' => $input?->tipo,
        ];
    }

    private function ensureItemCanMutate(Item $item): void
    {
        if (strtoupper((string) $item->estado) !== 'AC') {
            throw new InvalidArgumentException('El item se encuentra inhabilitado y no permite operaciones de composicion.');
        }
    }

    private function resolveValidInput(int $inputId, int $type): Input
    {
        $input = Input::query()->findOrFail($inputId);

        if ((int) $input->tipo !== $type) {
            throw new InvalidArgumentException('El insumo seleccionado no corresponde al bloque solicitado.');
        }

        if (strtoupper((string) $input->estado) !== 'AC') {
            throw new InvalidArgumentException('El insumo seleccionado no se encuentra activo.');
        }

        return $input;
    }

    private function ensureItemInputBelongsToItemAndType(Item $item, ItemInput $itemInput, int $type): void
    {
        $itemInput->loadMissing('input');

        if ((int) $itemInput->id_item !== (int) $item->id_item) {
            throw new InvalidArgumentException('La relacion item-insumo no pertenece al item solicitado.');
        }

        if ((int) ($itemInput->input?->tipo ?? 0) !== $type) {
            throw new InvalidArgumentException('La relacion item-insumo no corresponde al bloque solicitado.');
        }
    }
}

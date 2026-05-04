<?php

namespace App\Services\Projects;

use App\Models\Item;
use App\Models\Project;
use App\Models\ProjectItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProjectItemService
{
    public function __construct(
        private readonly ProjectItemIncidencePriceService $incidencePriceService,
        private readonly ProjectFormatResolver $formatResolver,
    ) {}

    public function sync(Project $project, array $items, User $user): Project
    {
        DB::transaction(function () use ($project, $items, $user): void {
            $incomingIds = collect($items)
                ->map(fn (array $item): int => (int) $item['id_item'])
                ->unique()
                ->values();

            $existing = ProjectItem::query()
                ->where('id_proyecto', $project->id_proyecto)
                ->whereNotNull('id_item')
                ->get()
                ->keyBy(fn (ProjectItem $item): int => (int) $item->id_item);

            foreach ($items as $itemData) {
                $itemId = (int) $itemData['id_item'];
                $payload = [
                    'precio' => (float) $itemData['precio'],
                    'cantidad' => (float) $itemData['cantidad'],
                    'prioridad' => isset($itemData['prioridad']) ? (int) $itemData['prioridad'] : null,
                    'estado' => strtoupper((string) ($itemData['estado'] ?? 'AC')),
                    'id_usuario' => $user->id_usuario,
                    'fecha' => now()->toDateString(),
                ];

                $existingItem = $existing->get($itemId);

                if ($existingItem instanceof ProjectItem && strtoupper((string) $existingItem->estado) === 'AC') {
                    $existingItem->update($payload);

                    continue;
                }

                if ($existingItem instanceof ProjectItem) {
                    $existingItem->update($payload);

                    continue;
                }

                ProjectItem::query()->create(array_merge($payload, [
                    'id_proyecto' => $project->id_proyecto,
                    'id_item' => $itemId,
                ]));
            }

            ProjectItem::query()
                ->where('id_proyecto', $project->id_proyecto)
                ->whereNotNull('id_item')
                ->whereNotIn('id_item', $incomingIds->all())
                ->where('estado', 'AC')
                ->update([
                    'estado' => 'DC',
                ]);

            $totalPrice = ProjectItem::query()
                ->where('id_proyecto', $project->id_proyecto)
                ->where('estado', 'AC')
                ->whereNotNull('id_item')
                ->get()
                ->sum(fn (ProjectItem $item): float => ((float) $item->precio) * ((float) $item->cantidad));

            $project->update([
                'precio' => round((float) $totalPrice, 4),
            ]);
        });

        return $project->refresh();
    }

    public function listProjectItems(Project $project, string $format): array
    {
        $items = ProjectItem::query()
            ->with(['item.groupCatalog', 'item.subgroupCatalog', 'item.unitMeasure'])
            ->where('id_proyecto', $project->id_proyecto)
            ->where('estado', 'AC')
            ->whereNotNull('id_item')
            ->orderBy('prioridad')
            ->get();

        return $items->map(function (ProjectItem $projectItem) use ($format): array {
            $item = $projectItem->item;
            $price = $item ? $this->incidencePriceService->resolve($item, $format) : null;

            return [
                'id_proyecto_item' => $projectItem->id_proyecto_item,
                'id_proyecto' => $projectItem->id_proyecto,
                'id_item' => $projectItem->id_item,
                'prioridad' => $projectItem->prioridad,
                'cantidad' => round((float) $projectItem->cantidad, 2),
                'precio' => $price,
                'grupo' => $item?->groupCatalog ? [
                    'id_grupo' => $item->groupCatalog->id_grupo,
                    'nombre_grupo' => $item->groupCatalog->nombre_grupo,
                ] : null,
                'subgrupo' => $item?->subgroupCatalog ? [
                    'id_subgrupo' => $item->subgroupCatalog->id_subgrupo,
                    'descripcion' => $item->subgroupCatalog->descripcion,
                ] : null,
                'item' => $item?->item,
                'unidad' => $item?->unitMeasure ? [
                    'id_unidad_medida' => $item->unitMeasure->id_unidad_medida,
                    'nombre_unidad_medida' => $item->unitMeasure->descripcion,
                    'abreviatura' => $item->unitMeasure->abreviatura,
                ] : null,
                'especificacion' => $item?->especificacion,
            ];
        })->values()->all();
    }

    public function incidenceItemDetail(Item $item, string $format): array
    {
        $item->load(['groupCatalog', 'subgroupCatalog', 'unitMeasure']);

        return [
            'id_item' => $item->id_item,
            'item' => $item->item,
            'id_grupo' => $item->groupCatalog?->id_grupo,
            'nombre_grupo' => $item->groupCatalog?->nombre_grupo,
            'id_subgrupo' => $item->subgroupCatalog?->id_subgrupo,
            'descripcion' => $item->subgroupCatalog?->descripcion,
            'id_unidad_medida' => $item->unitMeasure?->id_unidad_medida,
            'nombre_unidad_medida' => $item->unitMeasure?->descripcion,
            'especificacion' => $item->especificacion,
            'precio' => $this->incidencePriceService->resolve($item, $format),
        ];
    }

    public function searchItems(string $search): array
    {
        return Item::query()
            ->where('estado', 'AC')
            ->whereRaw('LOWER(TRIM(item)) LIKE ?', ['%'.mb_strtolower(trim($search)).'%'])
            ->orderBy('item')
            ->limit(20)
            ->get(['id_item', 'item'])
            ->map(fn (Item $item): array => [
                'id' => $item->id_item,
                'text' => $item->item,
            ])
            ->values()
            ->all();
    }
}

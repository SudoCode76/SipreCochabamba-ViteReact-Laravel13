<?php

namespace App\Services\Projects;

use App\Models\Item;
use App\Models\Project;
use App\Models\ProjectItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProjectItemService
{
    public function __construct(
        private readonly ProjectItemIncidencePriceService $incidencePriceService,
        private readonly ProjectLegacyUnitPriceService $legacyUnitPriceService,
        private readonly ProjectFormatResolver $formatResolver,
        private readonly ProjectHistoryService $projectHistoryService,
    ) {}

    public function sync(Project $project, array $items, User $user, ?string $ip = null): Project
    {
        $historySummary = [
            'added' => [],
            'updated' => [],
            'removed' => [],
        ];

        DB::transaction(function () use ($project, $items, $user, &$historySummary): void {
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
                    $this->trackUpdatedProjectItem($historySummary, $existingItem, $payload);
                    $existingItem->update($payload);

                    continue;
                }

                if ($existingItem instanceof ProjectItem) {
                    $historySummary['added'][] = $this->historyItemPayload($itemId, $payload);
                    $existingItem->update($payload);

                    continue;
                }

                $historySummary['added'][] = $this->historyItemPayload($itemId, $payload);
                ProjectItem::query()->create(array_merge($payload, [
                    'id_proyecto' => $project->id_proyecto,
                    'id_item' => $itemId,
                ]));
            }

            $removedItems = ProjectItem::query()
                ->where('id_proyecto', $project->id_proyecto)
                ->whereNotNull('id_item')
                ->whereNotIn('id_item', $incomingIds->all())
                ->where('estado', 'AC')
                ->get();

            foreach ($removedItems as $removedItem) {
                $historySummary['removed'][] = [
                    'id_item' => $removedItem->id_item,
                    'cantidad' => $removedItem->cantidad,
                    'precio' => $removedItem->precio,
                    'prioridad' => $removedItem->prioridad,
                ];
            }

            ProjectItem::query()
                ->whereIn('id_proyecto_item', $removedItems->pluck('id_proyecto_item')->all())
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

        $this->projectHistoryService->recordItemsSynced($project->refresh(), $user, $ip, $historySummary);

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
            $price = $item ? round($this->legacyUnitPriceService->resolve($item, $format), 2) : null;

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
                'especificacion_url' => $this->publicFileUrl($item?->especificacion),
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
            'abreviatura_unidad_medida' => $item->unitMeasure?->abreviatura,
            'especificacion' => $item->especificacion,
            'especificacion_url' => $this->publicFileUrl($item->especificacion),
            'precio' => round($this->legacyUnitPriceService->resolve($item, $format), 2),
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

    private function trackUpdatedProjectItem(array &$summary, ProjectItem $existingItem, array $payload): void
    {
        $changes = [];

        foreach (['precio', 'cantidad', 'prioridad', 'estado'] as $field) {
            $oldValue = $existingItem->{$field};
            $newValue = $payload[$field] ?? null;

            if ((string) $oldValue !== (string) $newValue) {
                $changes[$field] = [
                    'from' => $oldValue,
                    'to' => $newValue,
                ];
            }
        }

        if ($changes === []) {
            return;
        }

        $summary['updated'][] = [
            'id_item' => $existingItem->id_item,
            'changes' => $changes,
        ];
    }

    private function historyItemPayload(int $itemId, array $payload): array
    {
        return [
            'id_item' => $itemId,
            'cantidad' => $payload['cantidad'],
            'precio' => $payload['precio'],
            'prioridad' => $payload['prioridad'],
        ];
    }

    private function publicFileUrl(?string $path): ?string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        return url(Storage::disk('public')->url($path));
    }
}

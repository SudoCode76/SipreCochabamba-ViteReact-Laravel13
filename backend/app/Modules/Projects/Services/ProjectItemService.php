<?php

namespace App\Modules\Projects\Services;

use App\Models\Item;
use App\Models\Project;
use App\Models\ProjectItem;
use App\Models\User;
use App\Modules\Parameters\Services\ModuleService;
use App\Services\Files\PublicFileService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProjectItemService
{
    public function __construct(
        private readonly ProjectItemIncidencePriceService $incidencePriceService,
        private readonly ProjectLegacyUnitPriceService $legacyUnitPriceService,
        private readonly ProjectFormatResolver $formatResolver,
        private readonly ProjectHistoryService $projectHistoryService,
        private readonly ModuleService $moduleService,
        private readonly ProjectItemInputSnapshotService $snapshotService,
        private readonly PublicFileService $publicFileService,
    ) {}

    public function sync(Project $project, array $items, User $user, ?string $ip = null): Project
    {
        $historySummary = [
            'added' => [],
            'updated' => [],
            'removed' => [],
        ];

        DB::transaction(function () use ($project, $items, $user, &$historySummary): void {
            $generalModule = $this->moduleService->ensureGeneral();
            $incomingProjectItemIds = collect($items)
                ->pluck('id_proyecto_item')
                ->filter()
                ->map(fn (int|string $id): int => (int) $id)
                ->unique()
                ->values();

            $existing = ProjectItem::query()
                ->where('id_proyecto', $project->id_proyecto)
                ->whereNotNull('id_item')
                ->get()
                ->keyBy(fn (ProjectItem $item): int => (int) $item->id_proyecto_item);
            $existingProjectItemIds = $existing->keys()->map(fn (int|string $id): int => (int) $id)->values();

            foreach ($items as $itemData) {
                $itemId = (int) $itemData['id_item'];
                $projectItemId = isset($itemData['id_proyecto_item']) ? (int) $itemData['id_proyecto_item'] : null;
                $payload = [
                    'id_item' => $itemId,
                    'id_modulo' => isset($itemData['id_modulo']) ? (int) $itemData['id_modulo'] : $generalModule->id_modulo,
                    'precio' => (float) $itemData['precio'],
                    'cantidad' => (float) $itemData['cantidad'],
                    'prioridad' => isset($itemData['prioridad']) ? (int) $itemData['prioridad'] : null,
                    'estado' => strtoupper((string) ($itemData['estado'] ?? 'AC')),
                    'id_usuario' => $user->id_usuario,
                    'fecha' => now()->toDateString(),
                ];

                $existingItem = $projectItemId ? $existing->get($projectItemId) : null;

                if ($existingItem instanceof ProjectItem) {
                    $this->trackUpdatedProjectItem($historySummary, $existingItem, $payload);
                    $existingItem->update($payload);
                    $this->snapshotService->syncForProjectItem($existingItem->refresh());

                    continue;
                }

                $historySummary['added'][] = $this->historyItemPayload($itemId, $payload);
                $createdProjectItem = ProjectItem::query()->create(array_merge($payload, [
                    'id_proyecto' => $project->id_proyecto,
                ]));
                $this->snapshotService->syncForProjectItem($createdProjectItem);
            }

            $removedItems = ProjectItem::query()
                ->where('id_proyecto', $project->id_proyecto)
                ->whereNotNull('id_item')
                ->whereIn('id_proyecto_item', $existingProjectItemIds->all())
                ->when($incomingProjectItemIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id_proyecto_item', $incomingProjectItemIds->all()))
                ->where('estado', 'AC')
                ->get();

            foreach ($removedItems as $removedItem) {
                $historySummary['removed'][] = [
                    'id_item' => $removedItem->id_item,
                    'id_modulo' => $removedItem->id_modulo,
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
        $this->snapshotService->ensureForProject($project);
        $warnings = $this->snapshotService->warnings($project);
        $warningItemsByProjectItem = collect($warnings['items'] ?? [])->keyBy('id_proyecto_item');
        $warningInputsByProjectItem = collect($warnings['inputs'] ?? [])->groupBy('id_proyecto_item');

        $items = ProjectItem::query()
            ->with(['module', 'item.groupCatalog', 'item.subgroupCatalog', 'item.unitMeasure'])
            ->where('id_proyecto', $project->id_proyecto)
            ->where('estado', 'AC')
            ->whereNotNull('id_item')
            ->orderByRaw('COALESCE(id_modulo, 0)')
            ->orderBy('prioridad')
            ->orderBy('id_proyecto_item')
            ->get();

        return $items->map(function (ProjectItem $projectItem) use ($format, $warningItemsByProjectItem, $warningInputsByProjectItem): array {
            $item = $projectItem->item;
            $price = $item ? round($this->legacyUnitPriceService->resolve($item, $format), 2) : null;
            $itemWarning = $warningItemsByProjectItem->get($projectItem->id_proyecto_item);
            $inputWarnings = $warningInputsByProjectItem->get($projectItem->id_proyecto_item, collect())->values();

            return [
                'id_proyecto_item' => $projectItem->id_proyecto_item,
                'id_proyecto' => $projectItem->id_proyecto,
                'id_item' => $projectItem->id_item,
                'id_modulo' => $projectItem->id_modulo,
                'modulo' => $projectItem->module ? [
                    'id_modulo' => $projectItem->module->id_modulo,
                    'nombre_modulo' => $projectItem->module->nombre_modulo,
                ] : null,
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
                'item_estado' => $item?->estado,
                'has_warnings' => (bool) $itemWarning || $inputWarnings->isNotEmpty(),
                'warnings' => [
                    'item' => $itemWarning,
                    'inputs' => $inputWarnings->all(),
                ],
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

        foreach (['id_item', 'id_modulo', 'precio', 'cantidad', 'prioridad', 'estado'] as $field) {
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
            'id_modulo' => $payload['id_modulo'],
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

        return $this->publicFileService->url($path);
    }
}

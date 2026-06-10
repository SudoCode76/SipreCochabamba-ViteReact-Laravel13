<?php

namespace App\Modules\Items\Services;

use App\Models\Item;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ItemFreshnessService
{
    public const OUTDATED_AFTER_DAYS = 90;

    public function applyOutdatedFilter(Builder $query): Builder
    {
        return $query
            ->where('item.estado', 'AC')
            ->where(function (Builder $query): void {
                $query->whereNull('item.fecha_item')
                    ->orWhereDate('item.fecha_item', '<', $this->cutoffDate());
            });
    }

    public function outdatedCount(): int
    {
        return $this->applyOutdatedFilter(Item::query())->count();
    }

    public function describe(Item $item): array
    {
        $date = $item->fecha_item?->copy()->startOfDay();

        return [
            'fecha_item' => $date?->toDateString(),
            'days_without_update' => $date ? max(0, $date->diffInDays(today())) : null,
            'is_outdated' => strtoupper((string) $item->estado) === 'AC'
                && ($date === null || $date->lt($this->cutoffDate())),
        ];
    }

    public function markReviewed(Item $item): Item
    {
        $item->forceFill(['fecha_item' => today()->toDateString()])->save();

        return $item->refresh();
    }

    private function cutoffDate(): Carbon
    {
        return today()->subDays(self::OUTDATED_AFTER_DAYS);
    }
}

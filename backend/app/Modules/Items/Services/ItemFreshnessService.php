<?php

namespace App\Modules\Items\Services;

use App\Models\Item;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ItemFreshnessService
{
    public const OUTDATED_AFTER_DAYS = 90;
    public const REVIEW_FILTER_DAYS = [60, 120, 180];

    public function applyOutdatedFilter(Builder $query, int $days = self::OUTDATED_AFTER_DAYS): Builder
    {
        $days = $this->normalizeReviewDays($days);

        return $query
            ->where('item.estado', 'AC')
            ->where(function (Builder $query) use ($days): void {
                $query->whereNull('item.fecha_item')
                    ->orWhereDate('item.fecha_item', '<', $this->cutoffDate($days));
            });
    }

    public function applyReviewDaysFilter(Builder $query, int $days): Builder
    {
        $days = $this->normalizeReviewDays($days);

        $query->where('item.estado', 'AC');

        if ($days === 180) {
            return $query->where(function (Builder $query) use ($days): void {
                $query->whereNull('item.fecha_item')
                    ->orWhereDate('item.fecha_item', '<=', $this->cutoffDate($days));
            });
        }

        return $query->whereNotNull('item.fecha_item')
            ->whereDate('item.fecha_item', '>=', $this->cutoffDate($days));
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
                && ($date === null || $date->lt($this->cutoffDate(self::OUTDATED_AFTER_DAYS))),
        ];
    }

    public function markReviewed(Item $item): Item
    {
        $item->forceFill(['fecha_item' => today()->toDateString()])->save();

        return $item->refresh();
    }

    private function cutoffDate(int $days): Carbon
    {
        return today()->subDays($days);
    }

    private function normalizeReviewDays(int $days): int
    {
        return in_array($days, self::REVIEW_FILTER_DAYS, true) ? $days : self::OUTDATED_AFTER_DAYS;
    }
}

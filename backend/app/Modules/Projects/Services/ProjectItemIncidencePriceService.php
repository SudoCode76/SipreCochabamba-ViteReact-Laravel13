<?php

namespace App\Modules\Projects\Services;

use App\Models\Item;
use App\Modules\Items\Services\Analysis\ItemPriceAnalysisService;

class ProjectItemIncidencePriceService
{
    public function __construct(
        private readonly ProjectFormatResolver $formatResolver,
        private readonly ItemPriceAnalysisService $priceAnalysisService,
    ) {}

    public function resolve(Item $item, string $format): float
    {
        return $this->priceAnalysisService->calculateCurrentPrice($item, $this->formatResolver->toItemMode($format));
    }
}

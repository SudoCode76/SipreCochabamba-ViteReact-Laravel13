<?php

namespace App\Services\Projects;

use App\Models\Item;
use App\Services\Items\Analysis\ItemPriceAnalysisService;

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

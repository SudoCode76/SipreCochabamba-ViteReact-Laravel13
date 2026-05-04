<?php

namespace App\Services\Projects;

use App\Models\Item;
use App\Services\Items\Fndr\FndrPriceAnalysisService;

class ProjectItemIncidencePriceService
{
    public function __construct(
        private readonly ProjectFormatResolver $formatResolver,
        private readonly FndrPriceAnalysisService $priceAnalysisService,
    ) {}

    public function resolve(Item $item, string $format): float
    {
        return $this->priceAnalysisService->calculateCurrentPrice($item, $this->formatResolver->toItemMode($format));
    }
}

<?php

use App\Http\Controllers\Api\V1\ObrasCalculationPercentageController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active_user', 'admin'])->group(function (): void {
    Route::get('/calculation-percentages/obras', [ObrasCalculationPercentageController::class, 'index']);
    Route::get('/calculation-percentages/obras/context', [ObrasCalculationPercentageController::class, 'context']);
    Route::post('/calculation-percentages/obras', [ObrasCalculationPercentageController::class, 'store']);
    Route::get('/calculation-percentages/obras/{calculationPercentage}', [ObrasCalculationPercentageController::class, 'show']);
    Route::put('/calculation-percentages/obras/{calculationPercentage}', [ObrasCalculationPercentageController::class, 'update']);
});

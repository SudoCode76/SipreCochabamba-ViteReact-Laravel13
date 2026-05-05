<?php

use App\Http\Controllers\Api\V1\CalculationPercentageController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active_user', 'admin'])->group(function (): void {
    Route::get('/calculation-percentages', [CalculationPercentageController::class, 'index']);
    Route::get('/calculation-percentages/context', [CalculationPercentageController::class, 'context']);
    Route::post('/calculation-percentages', [CalculationPercentageController::class, 'store']);
    Route::get('/calculation-percentages/{calculationPercentage}', [CalculationPercentageController::class, 'show']);
    Route::put('/calculation-percentages/{calculationPercentage}', [CalculationPercentageController::class, 'update']);
});

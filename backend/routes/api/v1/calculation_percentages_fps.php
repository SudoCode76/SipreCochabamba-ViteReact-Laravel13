<?php

use App\Http\Controllers\Api\V1\FpsCalculationPercentageController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active_user', 'admin'])->group(function (): void {
    Route::get('/calculation-percentages/fps', [FpsCalculationPercentageController::class, 'index']);
    Route::get('/calculation-percentages/fps/context', [FpsCalculationPercentageController::class, 'context']);
    Route::post('/calculation-percentages/fps', [FpsCalculationPercentageController::class, 'store']);
    Route::get('/calculation-percentages/fps/{calculationPercentage}', [FpsCalculationPercentageController::class, 'show']);
    Route::put('/calculation-percentages/fps/{calculationPercentage}', [FpsCalculationPercentageController::class, 'update']);
});

<?php

use App\Http\Controllers\Api\V1\PromanCalculationPercentageController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'admin'])->group(function (): void {
    Route::get('/calculation-percentages/proman', [PromanCalculationPercentageController::class, 'index']);
    Route::get('/calculation-percentages/proman/context', [PromanCalculationPercentageController::class, 'context']);
    Route::post('/calculation-percentages/proman', [PromanCalculationPercentageController::class, 'store']);
    Route::get('/calculation-percentages/proman/{calculationPercentage}', [PromanCalculationPercentageController::class, 'show']);
    Route::put('/calculation-percentages/proman/{calculationPercentage}', [PromanCalculationPercentageController::class, 'update']);
});

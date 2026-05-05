<?php

use App\Http\Controllers\Api\V1\FndrCalculationPercentageController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'admin'])->group(function (): void {
    Route::get('/calculation-percentages/fndr', [FndrCalculationPercentageController::class, 'index']);
    Route::get('/calculation-percentages/fndr/context', [FndrCalculationPercentageController::class, 'context']);
    Route::post('/calculation-percentages/fndr', [FndrCalculationPercentageController::class, 'store']);
    Route::get('/calculation-percentages/fndr/{calculationPercentage}', [FndrCalculationPercentageController::class, 'show']);
    Route::put('/calculation-percentages/fndr/{calculationPercentage}', [FndrCalculationPercentageController::class, 'update']);
});

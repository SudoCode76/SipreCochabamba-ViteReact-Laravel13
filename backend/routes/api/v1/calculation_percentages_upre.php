<?php

use App\Http\Controllers\Api\V1\UpreCalculationPercentageController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active_user', 'admin'])->group(function (): void {
    Route::get('/calculation-percentages/upre', [UpreCalculationPercentageController::class, 'index']);
    Route::get('/calculation-percentages/upre/context', [UpreCalculationPercentageController::class, 'context']);
    Route::post('/calculation-percentages/upre', [UpreCalculationPercentageController::class, 'store']);
    Route::get('/calculation-percentages/upre/{calculationPercentage}', [UpreCalculationPercentageController::class, 'show']);
    Route::put('/calculation-percentages/upre/{calculationPercentage}', [UpreCalculationPercentageController::class, 'update']);
});

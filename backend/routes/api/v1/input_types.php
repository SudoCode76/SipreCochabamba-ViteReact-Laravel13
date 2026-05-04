<?php

use App\Http\Controllers\Api\V1\InputTypeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'admin'])->group(function (): void {
    Route::get('/input-types', [InputTypeController::class, 'index']);
    Route::get('/input-types/context', [InputTypeController::class, 'context']);
    Route::post('/input-types', [InputTypeController::class, 'store']);
    Route::get('/input-types/{inputType}', [InputTypeController::class, 'show']);
    Route::put('/input-types/{inputType}', [InputTypeController::class, 'update']);
});

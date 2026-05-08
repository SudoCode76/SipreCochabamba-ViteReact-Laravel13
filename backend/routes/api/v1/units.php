<?php

use App\Http\Controllers\Api\V1\UnitController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active_user', 'admin'])->group(function (): void {
    Route::get('/units', [UnitController::class, 'index']);
    Route::get('/units/context', [UnitController::class, 'context']);
    Route::get('/units/{unit}', [UnitController::class, 'show']);
});

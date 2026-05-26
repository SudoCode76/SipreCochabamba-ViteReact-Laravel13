<?php

use App\Http\Controllers\Api\V1\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active_user'])->prefix('dashboard')->group(function (): void {
    Route::get('/inputs', [DashboardController::class, 'inputs']);
});

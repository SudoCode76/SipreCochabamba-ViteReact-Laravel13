<?php

use App\Http\Controllers\Api\V1\AuthorizationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active_user', 'db_permission:ADMINISTRADOR,AUTORIZACION|AUTORIZACIONES'])->group(function (): void {
    Route::get('/authorizations/context', [AuthorizationController::class, 'context']);
    Route::get('/authorizations', [AuthorizationController::class, 'index']);
    Route::get('/authorizations/{authorization}', [AuthorizationController::class, 'show']);
    Route::get('/authorizations/{authorization}/impact', [AuthorizationController::class, 'impact']);
    Route::patch('/authorizations/{authorization}/status', [AuthorizationController::class, 'updateStatus']);
});

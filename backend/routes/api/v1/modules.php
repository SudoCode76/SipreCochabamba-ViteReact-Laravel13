<?php

use App\Http\Controllers\Api\V1\ModuleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active_user', 'db_permission:PROYECTO,REGISTRAR_ITEM_PROYECTO|PROYECTO|INDEX'])->group(function (): void {
    Route::get('/project-modules', [ModuleController::class, 'active']);
});

Route::middleware(['auth:sanctum', 'active_user', 'db_permission:PARAMETROS,MODULOS'])->group(function (): void {
    Route::get('/modules', [ModuleController::class, 'index']);
    Route::get('/modules/context', [ModuleController::class, 'context']);
    Route::post('/modules', [ModuleController::class, 'store']);
    Route::get('/modules/{module}', [ModuleController::class, 'show']);
    Route::put('/modules/{module}', [ModuleController::class, 'update']);
    Route::delete('/modules/{module}', [ModuleController::class, 'destroy']);
});

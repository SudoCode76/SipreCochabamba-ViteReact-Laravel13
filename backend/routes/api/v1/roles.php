<?php

use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\RolePermissionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'admin'])->group(function (): void {
    Route::get('/roles', [RoleController::class, 'index']);
    Route::post('/roles', [RoleController::class, 'store']);
    Route::get('/roles/{role}', [RoleController::class, 'show']);
    Route::put('/roles/{role}', [RoleController::class, 'update']);
    Route::patch('/roles/{role}/status', [RoleController::class, 'updateStatus']);

    Route::get('/roles/{role}/permissions', [RolePermissionController::class, 'show']);
    Route::put('/roles/{role}/permissions', [RolePermissionController::class, 'update']);
    Route::post('/roles/{role}/permissions/attach', [RolePermissionController::class, 'attach']);
    Route::post('/roles/{role}/permissions/detach', [RolePermissionController::class, 'detach']);
    Route::post('/roles/{role}/permissions/sync', [RolePermissionController::class, 'sync']);
    Route::post('/roles/{role}/permissions/clone-from/{sourceRoleId}', [RolePermissionController::class, 'cloneFrom']);
    Route::get('/permissions/matrix', [RolePermissionController::class, 'matrix']);
});

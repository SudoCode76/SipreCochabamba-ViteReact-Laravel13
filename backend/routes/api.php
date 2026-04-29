<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\RolePermissionController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'name' => config('app.name'),
        'status' => 'ok',
        'database' => DB::connection()->getDatabaseName(),
        'timestamp' => now()->toIso8601String(),
    ]);
});

Route::prefix('v1')->group(function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::post('/login', [AuthController::class, 'login']);

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::post('/change-password', [AuthController::class, 'changePassword']);
        });
    });

    Route::middleware('auth:sanctum')->prefix('profile')->group(function (): void {
        Route::get('/', [ProfileController::class, 'show']);
        Route::put('/password', [ProfileController::class, 'updatePassword']);
    });

    Route::middleware(['auth:sanctum', 'admin'])->group(function (): void {
        Route::post('/roles', [RoleController::class, 'store']);
        Route::put('/roles/{role}', [RoleController::class, 'update']);
        Route::get('/roles/{role}/permissions', [RolePermissionController::class, 'show']);
        Route::put('/roles/{role}/permissions', [RolePermissionController::class, 'update']);
        Route::post('/roles/{role}/permissions/attach', [RolePermissionController::class, 'attach']);
        Route::post('/roles/{role}/permissions/detach', [RolePermissionController::class, 'detach']);
        Route::get('/permissions/matrix', [RolePermissionController::class, 'matrix']);
    });
});

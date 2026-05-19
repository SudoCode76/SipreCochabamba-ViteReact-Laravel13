<?php

use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active_user', 'db_permission:ADMINISTRADOR,USUARIOS'])->group(function (): void {
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::get('/users/{user}', [UserController::class, 'show']);
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::patch('/users/{user}/status', [UserController::class, 'updateStatus']);
    Route::patch('/users/{user}/role', [UserController::class, 'updateRole']);
    Route::patch('/users/{user}/unit', [UserController::class, 'updateUnit']);
});

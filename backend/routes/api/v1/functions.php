<?php

use App\Http\Controllers\Api\V1\FunctionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active_user', 'admin'])->group(function (): void {
    Route::get('/functions/context', [FunctionController::class, 'context']);
    Route::get('/functions', [FunctionController::class, 'index']);
    Route::post('/functions', [FunctionController::class, 'store']);
    Route::get('/functions/{function}', [FunctionController::class, 'show']);
    Route::put('/functions/{function}', [FunctionController::class, 'update']);
    Route::patch('/functions/{function}/status', [FunctionController::class, 'updateStatus']);
});

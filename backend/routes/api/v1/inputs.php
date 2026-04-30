<?php

use App\Http\Controllers\Api\V1\InputController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'admin'])->group(function (): void {
    Route::get('/inputs', [InputController::class, 'index']);
    Route::post('/inputs', [InputController::class, 'store']);
    Route::get('/inputs/{input}', [InputController::class, 'show']);
    Route::put('/inputs/{input}', [InputController::class, 'update']);
    Route::patch('/inputs/{input}/status', [InputController::class, 'updateStatus']);
    Route::get('/inputs/{input}/history', [InputController::class, 'history']);
    Route::get('/inputs/{input}/logs', [InputController::class, 'logs']);
    Route::get('/inputs/{input}/quotes', [InputController::class, 'quotes']);
    Route::post('/inputs/{input}/quotes', [InputController::class, 'storeQuote']);
});

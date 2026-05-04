<?php

use App\Http\Controllers\Api\V1\InputController;
use App\Http\Controllers\Api\V1\SearchController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'admin'])->group(function (): void {
    Route::get('/inputs/context', [InputController::class, 'context']);
    Route::get('/inputs', [InputController::class, 'index']);
    Route::post('/inputs', [InputController::class, 'store']);
    Route::get('/inputs/{input}', [InputController::class, 'show']);
    Route::get('/inputs/{input}/name', [InputController::class, 'name']);
    Route::put('/inputs/{input}', [InputController::class, 'update']);
    Route::delete('/inputs/{input}', [InputController::class, 'destroy']);
    Route::post('/inputs/{input}/delete-authorization-request', [InputController::class, 'requestDeleteAuthorization']);
    Route::get('/inputs/{input}/delete-authorization-status', [InputController::class, 'deleteAuthorizationStatus']);
    Route::patch('/inputs/{input}/status', [InputController::class, 'updateStatus']);
    Route::get('/inputs/{input}/history', [InputController::class, 'history']);
    Route::get('/inputs/{input}/logs', [InputController::class, 'logs']);
    Route::get('/inputs/{input}/quotes', [InputController::class, 'quotes']);
    Route::post('/inputs/{input}/quotes', [InputController::class, 'storeQuote']);
    Route::get('/inputs/{input}/quotes/current', [InputController::class, 'currentQuote']);
    Route::get('/inputs/{input}/quotes/history', [InputController::class, 'quoteHistory']);
    Route::get('/inputs/{input}/quotes/log-history', [InputController::class, 'quoteLogHistory']);
    Route::get('/input-logs/{log}/files', [InputController::class, 'logFiles']);
    Route::get('/search/inputs', [SearchController::class, 'inputs']);
    Route::get('/search/unit-measures', [SearchController::class, 'unitMeasures']);
});

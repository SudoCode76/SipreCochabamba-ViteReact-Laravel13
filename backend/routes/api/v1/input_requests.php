<?php

use App\Http\Controllers\Api\V1\InputRequestController;
use App\Http\Controllers\Api\V1\SearchController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'admin'])->group(function (): void {
    Route::get('/input-requests', [InputRequestController::class, 'index']);
    Route::get('/input-requests/context', [InputRequestController::class, 'context']);
    Route::post('/input-requests', [InputRequestController::class, 'store']);
    Route::get('/input-requests/{inputRequest}', [InputRequestController::class, 'show']);
    Route::put('/input-requests/{inputRequest}', [InputRequestController::class, 'update']);
    Route::get('/input-requests/{inputRequest}/quotes/history', [InputRequestController::class, 'quotesHistory']);
    Route::get('/input-requests/{inputRequest}/quote-summary', [InputRequestController::class, 'quoteSummary']);
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/search/input-types', [SearchController::class, 'inputTypes']);
});

<?php

use App\Http\Controllers\Api\V1\InputRequestController;
use App\Http\Controllers\Api\V1\SearchController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active_user', 'admin'])->group(function (): void {
    Route::get('/input-requests', [InputRequestController::class, 'index']);
    Route::get('/input-requests/context', [InputRequestController::class, 'context']);
    Route::post('/input-requests', [InputRequestController::class, 'store']);
    Route::get('/input-requests/{inputRequest}', [InputRequestController::class, 'show']);
    Route::put('/input-requests/{inputRequest}', [InputRequestController::class, 'update']);
    Route::get('/input-requests/{inputRequest}/quotes/history', [InputRequestController::class, 'quotesHistory']);
    Route::get('/input-requests/{inputRequest}/quote-summary', [InputRequestController::class, 'quoteSummary']);

    Route::get('/solicitudes-insumo/gestion', [InputRequestController::class, 'managementIndex']);
    Route::get('/solicitudes-insumo/{inputRequest}', [InputRequestController::class, 'managementShow']);
    Route::post('/solicitudes-insumo/{inputRequest}/gestion', [InputRequestController::class, 'manage']);
    Route::post('/solicitudes-insumo/{inputRequest}/revertir', [InputRequestController::class, 'revert']);
    Route::get('/unidades-medida/search', [SearchController::class, 'unitMeasures']);
});

Route::middleware(['auth:sanctum', 'active_user'])->group(function (): void {
    Route::get('/search/input-types', [SearchController::class, 'inputTypes']);
});

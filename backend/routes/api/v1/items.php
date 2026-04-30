<?php

use App\Http\Controllers\Api\V1\ItemController;
use App\Http\Controllers\Api\V1\SubgroupController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/items/fndr/context', [ItemController::class, 'fndrContext']);
    Route::get('/items/fndr', [ItemController::class, 'fndrIndex']);
    Route::get('/items/upre/context', [ItemController::class, 'upreContext']);
    Route::get('/items/upre', [ItemController::class, 'upreIndex']);
    Route::post('/items', [ItemController::class, 'store']);
    Route::get('/items/{item}/price-analysis', [ItemController::class, 'priceAnalysis']);
    Route::post('/items/{item}/price-recalculation', [ItemController::class, 'priceRecalculation']);
    Route::get('/subgroups', [SubgroupController::class, 'index']);
});

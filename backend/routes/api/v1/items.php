<?php

use App\Http\Controllers\Api\V1\ItemController;
use App\Http\Controllers\Api\V1\SubgroupController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/items/context', [ItemController::class, 'context']);
    Route::get('/items', [ItemController::class, 'index']);
    Route::get('/items/fndr/context', [ItemController::class, 'fndrContext']);
    Route::get('/items/fndr', [ItemController::class, 'fndrIndex']);
    Route::get('/items/fps/context', [ItemController::class, 'fpsContext']);
    Route::get('/items/fps', [ItemController::class, 'fpsIndex']);
    Route::get('/items/obras/context', [ItemController::class, 'obrasContext']);
    Route::get('/items/obras', [ItemController::class, 'obrasIndex']);
    Route::get('/items/proman/context', [ItemController::class, 'promanContext']);
    Route::get('/items/proman', [ItemController::class, 'promanIndex']);
    Route::get('/items/upre/context', [ItemController::class, 'upreContext']);
    Route::get('/items/upre', [ItemController::class, 'upreIndex']);
    Route::post('/items', [ItemController::class, 'store']);
    Route::get('/items/{item}/price-analysis', [ItemController::class, 'priceAnalysis']);
    Route::post('/items/{item}/price-recalculation', [ItemController::class, 'priceRecalculation']);
    Route::get('/subgroups', [SubgroupController::class, 'index']);
});

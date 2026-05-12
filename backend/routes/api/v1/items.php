<?php

use App\Http\Controllers\Api\V1\ItemController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active_user'])->group(function (): void {
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
    Route::put('/items/{item}', [ItemController::class, 'update']);
    Route::get('/items/{item}/composition/context', [ItemController::class, 'compositionContext']);
    Route::get('/items/{item}/composition', [ItemController::class, 'composition']);
    Route::get('/items/{item}/materials', [ItemController::class, 'materials']);
    Route::post('/items/{item}/materials', [ItemController::class, 'storeMaterial']);
    Route::get('/items/{item}/materials/total', [ItemController::class, 'materialsTotal']);
    Route::put('/items/{item}/materials/{itemInput}', [ItemController::class, 'updateMaterial']);
    Route::delete('/items/{item}/materials/{itemInput}', [ItemController::class, 'deleteMaterial']);
    Route::get('/items/{item}/labor', [ItemController::class, 'labor']);
    Route::post('/items/{item}/labor', [ItemController::class, 'storeLabor']);
    Route::get('/items/{item}/labor/total', [ItemController::class, 'laborTotal']);
    Route::put('/items/{item}/labor/{itemInput}', [ItemController::class, 'updateLabor']);
    Route::delete('/items/{item}/labor/{itemInput}', [ItemController::class, 'deleteLabor']);
    Route::get('/items/{item}/machinery', [ItemController::class, 'machinery']);
    Route::post('/items/{item}/machinery', [ItemController::class, 'storeMachinery']);
    Route::get('/items/{item}/machinery/total', [ItemController::class, 'machineryTotal']);
    Route::put('/items/{item}/machinery/{itemInput}', [ItemController::class, 'updateMachinery']);
    Route::delete('/items/{item}/machinery/{itemInput}', [ItemController::class, 'deleteMachinery']);
    Route::get('/items/{item}/total', [ItemController::class, 'globalTotal']);
    Route::post('/items/{item}/breakdowns/recalculate', [ItemController::class, 'breakdownRecalculation']);
    Route::get('/items/{item}/price-analysis', [ItemController::class, 'priceAnalysis']);
    Route::post('/items/{item}/price-recalculation', [ItemController::class, 'priceRecalculation']);
});

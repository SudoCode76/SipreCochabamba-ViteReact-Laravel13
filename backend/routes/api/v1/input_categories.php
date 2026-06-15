<?php

use App\Http\Controllers\Api\V1\InputCategoryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active_user', 'db_permission:PARAMETROS,CATEGORIAINSUMO|CATEGORIA_INSUMO|INPUT_CATEGORIES|TIPOINSUMO|TIPO_INSUMO|INPUT_TYPES'])->group(function (): void {
    Route::get('/input-categories', [InputCategoryController::class, 'index']);
    Route::get('/input-categories/context', [InputCategoryController::class, 'context']);
    Route::post('/input-categories', [InputCategoryController::class, 'store']);
    Route::get('/input-categories/{category}', [InputCategoryController::class, 'show']);
    Route::put('/input-categories/{category}', [InputCategoryController::class, 'update']);
});

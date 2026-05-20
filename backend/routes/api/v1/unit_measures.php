<?php

use App\Http\Controllers\Api\V1\UnitMeasureController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active_user', 'db_permission:PARAMETROS,UNIDAD_MEDIDA|UNIDAD'])->group(function (): void {
    Route::get('/unit-measures', [UnitMeasureController::class, 'index']);
    Route::get('/unit-measures/context', [UnitMeasureController::class, 'context']);
    Route::post('/unit-measures', [UnitMeasureController::class, 'store']);
    Route::get('/unit-measures/{unitMeasure}', [UnitMeasureController::class, 'show']);
    Route::put('/unit-measures/{unitMeasure}', [UnitMeasureController::class, 'update']);
    Route::delete('/unit-measures/{unitMeasure}', [UnitMeasureController::class, 'destroy']);
    Route::post('/unit-measures/{unitMeasure}/delete-authorization-request', [UnitMeasureController::class, 'requestDeleteAuthorization']);
    Route::get('/unit-measures/{unitMeasure}/delete-authorization-status', [UnitMeasureController::class, 'deleteAuthorizationStatus']);
});

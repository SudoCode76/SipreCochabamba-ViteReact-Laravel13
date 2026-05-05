<?php

use App\Http\Controllers\Api\V1\SubgroupController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/subgroups', [SubgroupController::class, 'index']);
    Route::get('/subgroups/by-group/{group}', [SubgroupController::class, 'byGroup']);
});

Route::middleware(['auth:sanctum', 'admin'])->group(function (): void {
    Route::get('/subgroups/context', [SubgroupController::class, 'context']);
    Route::post('/subgroups', [SubgroupController::class, 'store']);
    Route::get('/subgroups/{subgroup}', [SubgroupController::class, 'show']);
    Route::put('/subgroups/{subgroup}', [SubgroupController::class, 'update']);
    Route::delete('/subgroups/{subgroup}', [SubgroupController::class, 'destroy']);
    Route::post('/subgroups/{subgroup}/delete-authorization-request', [SubgroupController::class, 'requestDeleteAuthorization']);
    Route::get('/subgroups/{subgroup}/delete-authorization-status', [SubgroupController::class, 'deleteAuthorizationStatus']);
});

<?php

use App\Http\Controllers\Api\V1\GroupController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active_user', 'admin'])->group(function (): void {
    Route::get('/groups', [GroupController::class, 'index']);
    Route::get('/groups/context', [GroupController::class, 'context']);
    Route::post('/groups', [GroupController::class, 'store']);
    Route::get('/groups/{group}', [GroupController::class, 'show']);
    Route::put('/groups/{group}', [GroupController::class, 'update']);
    Route::delete('/groups/{group}', [GroupController::class, 'destroy']);
    Route::post('/groups/{group}/delete-authorization-request', [GroupController::class, 'requestDeleteAuthorization']);
    Route::get('/groups/{group}/delete-authorization-status', [GroupController::class, 'deleteAuthorizationStatus']);
});

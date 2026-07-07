<?php

use App\Http\Controllers\Api\V1\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active_user'])->prefix('profile')->group(function (): void {
    Route::get('/', [ProfileController::class, 'show']);
    Route::put('/password', [ProfileController::class, 'updatePassword']);
    Route::post('/signature-image', [ProfileController::class, 'uploadSignatureImage']);
    Route::delete('/signature-image', [ProfileController::class, 'deleteSignatureImage']);
});

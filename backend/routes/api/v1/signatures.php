<?php

use App\Http\Controllers\Api\V1\ProjectReportSignatureController;
use Illuminate\Support\Facades\Route;

Route::get('/citizenship/signature/callback', [ProjectReportSignatureController::class, 'callback']);
Route::post('/citizenship/signature/callback', [ProjectReportSignatureController::class, 'callback']);
Route::get('/citizenship/signature/login-callback', [ProjectReportSignatureController::class, 'loginCallback']);
Route::post('/citizenship/signature/login-callback', [ProjectReportSignatureController::class, 'loginCallback']);
Route::get('/citizenship/signature/approval-callback', [ProjectReportSignatureController::class, 'approvalCallback']);
Route::post('/citizenship/signature/approval-callback', [ProjectReportSignatureController::class, 'approvalCallback']);
Route::get('/citizenship/signature/logout-callback', [ProjectReportSignatureController::class, 'logoutCallback']);
Route::post('/citizenship/signature/logout-callback', [ProjectReportSignatureController::class, 'logoutCallback']);

Route::middleware(['auth:sanctum', 'active_user'])->group(function (): void {
    Route::get('/citizenship/session', [ProjectReportSignatureController::class, 'citizenshipSession']);
    Route::post('/citizenship/session/logout', [ProjectReportSignatureController::class, 'logoutCitizenshipSession']);
    Route::get('/signable-project-reports', [ProjectReportSignatureController::class, 'index']);
    Route::patch('/signable-project-reports/{reportKey}', [ProjectReportSignatureController::class, 'update']);
});

<?php

use App\Http\Controllers\Api\V1\AuditController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active_user', 'db_permission:ADMINISTRADOR,AUDITORIA'])->group(function (): void {
    Route::get('/audits', [AuditController::class, 'index']);
});

<?php

use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active_user'])->group(function (): void {
    Route::get('/projects/context', [ProjectController::class, 'context']);
    Route::get('/projects/create-context', [ProjectController::class, 'context']);
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::get('/projects/{project}', [ProjectController::class, 'show']);
    Route::put('/projects/{project}', [ProjectController::class, 'update']);
    Route::post('/projects/{project}/items/sync', [ProjectController::class, 'syncItems']);
    Route::get('/projects/{project}/items', [ProjectController::class, 'items']);
    Route::get('/projects/items/{item}/incidence-price', [ProjectController::class, 'incidencePrice']);
    Route::post('/projects/{project}/budget-recalculation', [ProjectController::class, 'budgetRecalculation']);
    Route::get('/projects/{project}/budget-by-group', [ProjectController::class, 'budgetByGroup']);
    Route::get('/projects/{project}/budget-by-group/pdf', [ProjectController::class, 'budgetByGroupPdf']);
    Route::get('/projects/{project}/incidence-summary', [ProjectController::class, 'incidenceSummary']);
    Route::get('/projects/{project}/incidence-summary/pdf', [ProjectController::class, 'incidenceSummaryPdf']);
    Route::get('/projects/{project}/general-budget/pdf', [ProjectController::class, 'generalBudgetPdf']);
    Route::post('/projects/{project}/breakdown-calculation', [ProjectController::class, 'breakdownCalculation']);
    Route::get('/projects/{project}/unit-prices', [ProjectController::class, 'unitPrices']);
    Route::get('/search/items', [SearchController::class, 'items']);
    Route::get('/users/{user}/display-name', [UserController::class, 'displayName']);
});

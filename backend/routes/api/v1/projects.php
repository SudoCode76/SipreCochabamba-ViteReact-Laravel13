<?php

use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\UserController;
use App\Modules\Projects\Http\Controllers\ProjectBudgetController;
use App\Modules\Projects\Http\Controllers\ProjectCatalogController;
use App\Modules\Projects\Http\Controllers\ProjectHistoryController;
use App\Modules\Projects\Http\Controllers\ProjectItemsController;
use App\Modules\Projects\Http\Controllers\ProjectReportController;
use App\Modules\Projects\Http\Controllers\ProjectTemplateController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active_user'])->group(function (): void {
    Route::get('/projects/context', [ProjectCatalogController::class, 'context']);
    Route::get('/projects/create-context', [ProjectCatalogController::class, 'context']);
    Route::get('/projects', [ProjectCatalogController::class, 'index']);
    Route::post('/projects', [ProjectCatalogController::class, 'store']);
    Route::get('/project-templates', [ProjectTemplateController::class, 'index']);
    Route::get('/project-templates/{template}', [ProjectTemplateController::class, 'show']);
    Route::put('/project-templates/{project}', [ProjectTemplateController::class, 'update']);
    Route::post('/project-templates/{template}/create-project', [ProjectTemplateController::class, 'createProject']);
    Route::post('/projects/{project}/template', [ProjectTemplateController::class, 'store']);
    Route::get('/projects/{project}/history', [ProjectHistoryController::class, 'history']);
    Route::get('/projects/{project}', [ProjectCatalogController::class, 'show']);
    Route::put('/projects/{project}', [ProjectCatalogController::class, 'update']);
    Route::post('/projects/{project}/items/sync', [ProjectItemsController::class, 'syncItems']);
    Route::get('/projects/{project}/items', [ProjectItemsController::class, 'items']);
    Route::get('/projects/items/{item}/incidence-price', [ProjectItemsController::class, 'incidencePrice']);
    Route::post('/projects/{project}/budget-recalculation', [ProjectBudgetController::class, 'budgetRecalculation']);
    Route::get('/projects/{project}/budget-recalculation/pdf', [ProjectBudgetController::class, 'budgetRecalculationPdf']);
    Route::get('/projects/{project}/budget-by-group', [ProjectBudgetController::class, 'budgetByGroup']);
    Route::get('/projects/{project}/budget-by-group/pdf', [ProjectReportController::class, 'budgetByGroupPdf']);
    Route::get('/projects/{project}/incidence-summary', [ProjectBudgetController::class, 'incidenceSummary']);
    Route::get('/projects/{project}/incidence-summary/pdf', [ProjectReportController::class, 'incidenceSummaryPdf']);
    Route::get('/projects/{project}/general-budget/pdf', [ProjectReportController::class, 'generalBudgetPdf']);
    Route::get('/projects/{project}/input-breakdown/pdf', [ProjectReportController::class, 'inputBreakdownPdf']);
    Route::get('/projects/{project}/inputs-report/pdf', [ProjectReportController::class, 'inputsReportPdf']);
    Route::post('/projects/{project}/breakdown-calculation', [ProjectBudgetController::class, 'breakdownCalculation']);
    Route::get('/projects/{project}/unit-prices', [ProjectBudgetController::class, 'unitPrices']);
    Route::get('/search/items', [SearchController::class, 'items']);
    Route::get('/users/{user}/display-name', [UserController::class, 'displayName']);
});

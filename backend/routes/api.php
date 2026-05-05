<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'name' => config('app.name'),
        'status' => 'ok',
        'database' => DB::connection()->getDatabaseName(),
        'timestamp' => now()->toIso8601String(),
    ]);
});

Route::prefix('v1')->group(function (): void {
    require __DIR__.'/api/v1/auth.php';
    require __DIR__.'/api/v1/profile.php';
    require __DIR__.'/api/v1/users.php';
    require __DIR__.'/api/v1/roles.php';
    require __DIR__.'/api/v1/functions.php';
    require __DIR__.'/api/v1/groups.php';
    require __DIR__.'/api/v1/subgroups.php';
    require __DIR__.'/api/v1/calculation_percentages.php';
    require __DIR__.'/api/v1/items.php';
    require __DIR__.'/api/v1/projects.php';
    require __DIR__.'/api/v1/inputs.php';
    require __DIR__.'/api/v1/input_types.php';
    require __DIR__.'/api/v1/unit_measures.php';
    require __DIR__.'/api/v1/input_requests.php';
});

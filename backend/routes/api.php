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

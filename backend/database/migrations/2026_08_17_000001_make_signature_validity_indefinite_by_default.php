<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_signable_reports', function (Blueprint $table): void {
            $table->integer('validity_days')->nullable()->default(null)->change();
        });

        DB::table('project_signable_reports')
            ->where('validity_days', 30)
            ->update(['validity_days' => null]);
    }

    public function down(): void
    {
        DB::table('project_signable_reports')
            ->whereNull('validity_days')
            ->update(['validity_days' => 30]);
        Schema::table('project_signable_reports', function (Blueprint $table): void {
            $table->integer('validity_days')->default(30)->nullable(false)->change();
        });
    }
};

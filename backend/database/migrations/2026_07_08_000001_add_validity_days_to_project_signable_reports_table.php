<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_signable_reports', function (Blueprint $table): void {
            $table->integer('validity_days')->default(30);
        });
    }

    public function down(): void
    {
        Schema::table('project_signable_reports', function (Blueprint $table): void {
            $table->dropColumn('validity_days');
        });
    }
};

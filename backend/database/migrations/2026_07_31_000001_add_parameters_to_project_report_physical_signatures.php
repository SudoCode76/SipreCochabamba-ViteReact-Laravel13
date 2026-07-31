<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_report_physical_signatures', function (Blueprint $table): void {
            $table->json('parameters')->nullable()->after('report_key');
        });
    }

    public function down(): void
    {
        Schema::table('project_report_physical_signatures', function (Blueprint $table): void {
            $table->dropColumn('parameters');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_report_physical_signatures', function (Blueprint $table): void {
            $table->json('selected_pages')->nullable()->after('page_scope');
            $table->json('page_positions')->nullable()->after('selected_pages');
            $table->timestamp('pages_confirmed_at')->nullable()->after('page_positions');
        });
    }

    public function down(): void
    {
        Schema::table('project_report_physical_signatures', function (Blueprint $table): void {
            $table->dropColumn(['selected_pages', 'page_positions', 'pages_confirmed_at']);
        });
    }
};

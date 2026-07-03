<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_report_signatures', function (Blueprint $table): void {
            $table->string('base_document_hash', 64)->nullable()->index()->after('base_file_path');
        });
    }

    public function down(): void
    {
        Schema::table('project_report_signatures', function (Blueprint $table): void {
            $table->dropColumn('base_document_hash');
        });
    }
};

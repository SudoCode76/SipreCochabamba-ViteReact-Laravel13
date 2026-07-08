<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_report_physical_signatures', function (Blueprint $table): void {
            if (! Schema::hasColumn('project_report_physical_signatures', 'page')) {
                $table->unsignedInteger('page')->nullable()->after('signature_image_path');
            }

            if (! Schema::hasColumn('project_report_physical_signatures', 'x')) {
                $table->decimal('x', 8, 2)->nullable()->after('page');
            }

            if (! Schema::hasColumn('project_report_physical_signatures', 'y')) {
                $table->decimal('y', 8, 2)->nullable()->after('x');
            }

            if (! Schema::hasColumn('project_report_physical_signatures', 'width')) {
                $table->decimal('width', 8, 2)->nullable()->after('y');
            }

            if (! Schema::hasColumn('project_report_physical_signatures', 'height')) {
                $table->decimal('height', 8, 2)->nullable()->after('width');
            }
        });
    }

    public function down(): void
    {
        Schema::table('project_report_physical_signatures', function (Blueprint $table): void {
            foreach (['page', 'x', 'y', 'width', 'height'] as $column) {
                if (Schema::hasColumn('project_report_physical_signatures', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

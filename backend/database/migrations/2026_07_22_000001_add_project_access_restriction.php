<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('proyecto') || Schema::hasColumn('proyecto', 'project_access_restricted')) {
            return;
        }

        Schema::table('proyecto', function (Blueprint $table): void {
            $table->boolean('project_access_restricted')->default(false);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('proyecto') || ! Schema::hasColumn('proyecto', 'project_access_restricted')) {
            return;
        }

        Schema::table('proyecto', function (Blueprint $table): void {
            $table->dropColumn('project_access_restricted');
        });
    }
};

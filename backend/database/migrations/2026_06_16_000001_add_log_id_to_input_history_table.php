<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('historial_insumo') && ! Schema::hasColumn('historial_insumo', 'id_log_insumo')) {
            Schema::table('historial_insumo', function (Blueprint $table): void {
                $table->unsignedInteger('id_log_insumo')->nullable()->after('id_insumo');
                $table->index('id_log_insumo');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('historial_insumo') && Schema::hasColumn('historial_insumo', 'id_log_insumo')) {
            Schema::table('historial_insumo', function (Blueprint $table): void {
                $table->dropIndex(['id_log_insumo']);
                $table->dropColumn('id_log_insumo');
            });
        }
    }
};

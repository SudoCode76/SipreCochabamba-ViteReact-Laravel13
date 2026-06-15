<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('historial_insumo') && ! Schema::hasColumn('historial_insumo', 'id_categoria')) {
            Schema::table('historial_insumo', function (Blueprint $table): void {
                $table->unsignedInteger('id_categoria')->nullable()->after('tipo');
                $table->index('id_categoria');
            });
        }

        if (Schema::hasTable('log_insumo') && ! Schema::hasColumn('log_insumo', 'id_categoria')) {
            Schema::table('log_insumo', function (Blueprint $table): void {
                $table->unsignedInteger('id_categoria')->nullable()->after('tipo');
                $table->index('id_categoria');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('historial_insumo') && Schema::hasColumn('historial_insumo', 'id_categoria')) {
            Schema::table('historial_insumo', function (Blueprint $table): void {
                $table->dropIndex(['id_categoria']);
                $table->dropColumn('id_categoria');
            });
        }

        if (Schema::hasTable('log_insumo') && Schema::hasColumn('log_insumo', 'id_categoria')) {
            Schema::table('log_insumo', function (Blueprint $table): void {
                $table->dropIndex(['id_categoria']);
                $table->dropColumn('id_categoria');
            });
        }
    }
};

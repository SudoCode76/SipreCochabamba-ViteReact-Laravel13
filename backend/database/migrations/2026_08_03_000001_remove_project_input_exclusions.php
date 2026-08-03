<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('proyecto_item_insumo_snapshot')) {
            return;
        }

        $table = 'proyecto_item_insumo_snapshot';
        $columns = array_filter([
            Schema::hasColumn($table, 'excluido_por') ? 'excluido_por' : null,
            Schema::hasColumn($table, 'excluido_en') ? 'excluido_en' : null,
        ]);

        $update = ['estado' => 'AC'];
        foreach ($columns as $column) {
            $update[$column] = null;
        }

        DB::table($table)->where('estado', 'EX')->update($update);

        if ($columns !== []) {
            Schema::table($table, function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('proyecto_item_insumo_snapshot')) {
            return;
        }

        Schema::table('proyecto_item_insumo_snapshot', function (Blueprint $table): void {
            if (! Schema::hasColumn('proyecto_item_insumo_snapshot', 'excluido_por')) {
                $table->unsignedInteger('excluido_por')->nullable();
            }
            if (! Schema::hasColumn('proyecto_item_insumo_snapshot', 'excluido_en')) {
                $table->timestamp('excluido_en')->nullable();
            }
        });
    }
};

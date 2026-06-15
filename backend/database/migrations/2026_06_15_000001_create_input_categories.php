<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('categoria_insumo')) {
            Schema::create('categoria_insumo', function (Blueprint $table): void {
                $table->increments('id_categoria');
                $table->string('descripcion', 80);
                $table->string('estado', 2)->default('AC');
                $table->unsignedInteger('usuario')->nullable();
                $table->date('fecha')->nullable();

                $table->index('estado');
                $table->index('descripcion');
            });
        }

        if (Schema::hasTable('insumo') && ! Schema::hasColumn('insumo', 'id_categoria')) {
            Schema::table('insumo', function (Blueprint $table): void {
                $table->unsignedInteger('id_categoria')->nullable()->after('tipo');
                $table->index('id_categoria');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('insumo') && Schema::hasColumn('insumo', 'id_categoria')) {
            Schema::table('insumo', function (Blueprint $table): void {
                $table->dropIndex(['id_categoria']);
                $table->dropColumn('id_categoria');
            });
        }

        Schema::dropIfExists('categoria_insumo');
    }
};

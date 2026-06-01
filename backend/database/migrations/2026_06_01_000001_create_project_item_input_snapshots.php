<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('proyecto_item_insumo_snapshot')) {
            Schema::create('proyecto_item_insumo_snapshot', function (Blueprint $table): void {
                $table->id('id_snapshot');
                $table->unsignedBigInteger('id_proyecto_item');
                $table->unsignedBigInteger('id_insumo')->nullable();
                $table->string('descripcion', 500);
                $table->unsignedTinyInteger('tipo')->nullable();
                $table->string('unidad', 100)->nullable();
                $table->decimal('cantidad', 18, 4)->default(0);
                $table->decimal('precio_unitario', 18, 4)->default(0);
                $table->decimal('parcial', 18, 4)->default(0);
                $table->string('estado', 2)->default('AC');
                $table->timestamps();

                $table->index(['id_proyecto_item', 'estado'], 'piis_project_item_status_idx');
                $table->index(['id_insumo'], 'piis_input_idx');
            });
        }

        // Las fotos de proyectos existentes se generan bajo demanda por proyecto para
        // evitar bloquear despliegues con bases legacy grandes.
    }

    public function down(): void
    {
        Schema::dropIfExists('proyecto_item_insumo_snapshot');
    }
};

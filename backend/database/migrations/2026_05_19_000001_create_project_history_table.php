<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proyecto_historial', function (Blueprint $table): void {
            $table->increments('id_historial');
            $table->unsignedInteger('id_proyecto');
            $table->unsignedInteger('id_usuario')->nullable();
            $table->string('usuario_nombre', 100)->nullable();
            $table->string('accion', 50);
            $table->string('titulo', 150);
            $table->text('detalle')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('fecha_hora')->useCurrent();

            $table->index(['id_proyecto', 'fecha_hora']);
            $table->index('accion');
        });

        if (Schema::hasTable('proyecto')) {
            Schema::table('proyecto_historial', function (Blueprint $table): void {
                $table->foreign('id_proyecto')->references('id_proyecto')->on('proyecto');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('proyecto_historial');
    }
};

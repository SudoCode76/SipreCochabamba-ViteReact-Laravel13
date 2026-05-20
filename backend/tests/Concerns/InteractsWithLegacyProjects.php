<?php

namespace Tests\Concerns;

use App\Models\Project;
use App\Models\ProjectItem;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait InteractsWithLegacyProjects
{
    protected function setUpLegacyProjectSchema(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('proyecto_historial');
        Schema::dropIfExists('proyecto_item');
        Schema::dropIfExists('proyecto');
        Schema::dropIfExists('modulo');
        Schema::enableForeignKeyConstraints();

        Schema::create('proyecto', function (Blueprint $table): void {
            $table->increments('id_proyecto');
            $table->string('nombre_proyecto', 500)->nullable();
            $table->date('fecha')->nullable();
            $table->string('ubicacion', 100)->nullable();
            $table->string('responsable', 100)->nullable();
            $table->unsignedInteger('solicitante')->nullable();
            $table->string('observaciones', 500)->nullable();
            $table->string('aprobado', 2)->nullable();
            $table->date('fecha_aprob')->nullable();
            $table->unsignedInteger('id_usuario')->nullable();
            $table->string('estado', 2)->nullable();
            $table->string('nombre_responsable', 100)->nullable();
            $table->string('latitud', 50)->nullable();
            $table->string('longitud', 50)->nullable();
            $table->float('precio')->nullable();
            $table->string('distrito', 50)->nullable();
            $table->string('zona', 150)->nullable();
            $table->string('otb', 150)->nullable();
        });

        Schema::create('modulo', function (Blueprint $table): void {
            $table->increments('id_modulo');
            $table->string('nombre_modulo', 150);
            $table->string('estado', 2)->default('AC');
            $table->unsignedInteger('id_usuario')->nullable();
            $table->date('fecha')->nullable();
        });

        DB::table('modulo')->insert([
            'id_modulo' => 1,
            'nombre_modulo' => 'General',
            'estado' => 'AC',
            'id_usuario' => null,
            'fecha' => now()->toDateString(),
        ]);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SELECT setval(pg_get_serial_sequence('modulo', 'id_modulo'), COALESCE((SELECT MAX(id_modulo) FROM modulo), 1))");
        }

        Schema::create('proyecto_item', function (Blueprint $table): void {
            $table->increments('id_proyecto_item');
            $table->unsignedInteger('id_proyecto')->nullable();
            $table->unsignedInteger('id_item')->nullable();
            $table->unsignedInteger('id_modulo')->nullable();
            $table->string('estado', 2)->nullable();
            $table->double('cantidad')->nullable();
            $table->date('fecha')->nullable();
            $table->float('precio')->nullable();
            $table->unsignedInteger('id_usuario')->nullable();
            $table->unsignedInteger('prioridad')->nullable();

            $table->foreign('id_proyecto')->references('id_proyecto')->on('proyecto');
            $table->foreign('id_item')->references('id_item')->on('item');
            $table->foreign('id_modulo')->references('id_modulo')->on('modulo');
        });

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
            $table->timestamp('fecha_hora')->nullable();

            $table->foreign('id_proyecto')->references('id_proyecto')->on('proyecto');
        });
    }

    protected function createProjectRecord(array $overrides = []): Project
    {
        return Project::query()->create(array_merge([
            'id_proyecto' => 1,
            'nombre_proyecto' => 'PROYECTO TEST',
            'fecha' => now()->toDateString(),
            'ubicacion' => 'UBICACION TEST',
            'responsable' => '1',
            'solicitante' => 1,
            'observaciones' => 'OBSERVACIONES',
            'aprobado' => 'PD',
            'fecha_aprob' => null,
            'id_usuario' => 1,
            'estado' => 'AC',
            'nombre_responsable' => 'Usuario Demo',
            'latitud' => '123',
            'longitud' => '456',
            'precio' => 0,
            'distrito' => '1',
            'zona' => 'ZONA TEST',
            'otb' => 'OTB TEST',
        ], $overrides));
    }

    protected function createProjectItemRecord(array $overrides = []): ProjectItem
    {
        return ProjectItem::query()->create(array_merge([
            'id_proyecto_item' => 1,
            'id_proyecto' => 1,
            'id_item' => 1,
            'id_modulo' => 1,
            'estado' => 'AC',
            'cantidad' => 2,
            'fecha' => now()->toDateString(),
            'precio' => 10,
            'id_usuario' => 1,
            'prioridad' => 1,
        ], $overrides));
    }
}

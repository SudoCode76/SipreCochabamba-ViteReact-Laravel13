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
        Schema::dropIfExists('project_signature_authorized_users');
        Schema::dropIfExists('project_version_signature_users');
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
            $table->boolean('es_plantilla')->default(false);
            $table->string('nombre_responsable', 100)->nullable();
            $table->string('latitud', 50)->nullable();
            $table->string('longitud', 50)->nullable();
            $table->float('precio')->nullable();
            $table->string('distrito', 50)->nullable();
            $table->string('zona', 150)->nullable();
            $table->string('otb', 150)->nullable();
            $table->unsignedInteger('id_proyecto_raiz')->nullable();
            $table->unsignedInteger('id_version_origen')->nullable();
            $table->unsignedInteger('numero_version')->default(1);
            $table->boolean('es_version_actual')->default(true);
            $table->timestamp('fecha_version')->nullable();
            $table->timestamp('fecha_finalizacion')->nullable();
            $table->string('signature_access_mode', 20)->default('selected');
            $table->timestamp('signature_signers_configured_at')->nullable();
            $table->timestamp('signature_signers_locked_at')->nullable();
            $table->boolean('project_access_restricted')->default(false);
        });

        Schema::create('project_signature_authorized_users', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('id_proyecto_raiz');
            $table->unsignedInteger('id_usuario');
            $table->timestamps();
            $table->unique(['id_proyecto_raiz', 'id_usuario']);
        });

        Schema::create('project_version_signature_users', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('id_proyecto');
            $table->unsignedInteger('id_usuario');
            $table->string('signature_image_path')->nullable();
            $table->string('signature_image_hash', 64)->nullable();
            $table->timestamps();
            $table->unique(['id_proyecto', 'id_usuario']);
        });

        if (Schema::hasTable('project_report_physical_signatures')
            && ! Schema::hasColumn('project_report_physical_signatures', 'page_scope')) {
            Schema::table('project_report_physical_signatures', function (Blueprint $table): void {
                $table->string('page_scope', 10)->default('all');
            });
        }

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
            $table->string('nombre_snapshot', 500)->nullable();
            $table->string('grupo_snapshot', 255)->nullable();
            $table->string('subgrupo_snapshot', 255)->nullable();
            $table->string('unidad_snapshot', 100)->nullable();
            $table->string('estado_catalogo_snapshot', 20)->nullable();

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
        $project = Project::query()->create(array_merge([
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
            'es_plantilla' => false,
            'nombre_responsable' => 'Usuario Demo',
            'latitud' => '123',
            'longitud' => '456',
            'precio' => 0,
            'distrito' => '1',
            'zona' => 'ZONA TEST',
            'otb' => 'OTB TEST',
            'id_proyecto_raiz' => $overrides['id_proyecto'] ?? 1,
            'id_version_origen' => null,
            'numero_version' => 1,
            'es_version_actual' => true,
            'fecha_version' => now(),
            'fecha_finalizacion' => null,
            'signature_signers_configured_at' => now(),
        ], $overrides));

        $rootId = (int) ($project->id_proyecto_raiz ?: $project->id_proyecto);
        if ($project->id_proyecto === $rootId && $project->id_usuario) {
            DB::table('project_signature_authorized_users')->insertOrIgnore([
                'id_proyecto_raiz' => $rootId,
                'id_usuario' => $project->id_usuario,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }


        if ($project->id_usuario) {
            DB::table('project_version_signature_users')->insertOrIgnore([
                'id_proyecto' => $project->id_proyecto,
                'id_usuario' => $project->id_usuario,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $project;
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
            'nombre_snapshot' => 'ITEM TEST',
            'grupo_snapshot' => 'GRUPO TEST',
            'subgrupo_snapshot' => 'SUBGRUPO TEST',
            'unidad_snapshot' => 'u',
            'estado_catalogo_snapshot' => 'AC',
        ], $overrides));
    }
}

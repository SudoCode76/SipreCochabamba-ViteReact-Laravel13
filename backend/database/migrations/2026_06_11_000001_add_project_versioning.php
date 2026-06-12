<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('proyecto')) {
            Schema::table('proyecto', function (Blueprint $table): void {
                if (! Schema::hasColumn('proyecto', 'id_proyecto_raiz')) {
                    $table->unsignedInteger('id_proyecto_raiz')->nullable()->index();
                }
                if (! Schema::hasColumn('proyecto', 'id_version_origen')) {
                    $table->unsignedInteger('id_version_origen')->nullable()->index();
                }
                if (! Schema::hasColumn('proyecto', 'numero_version')) {
                    $table->unsignedInteger('numero_version')->default(1);
                }
                if (! Schema::hasColumn('proyecto', 'es_version_actual')) {
                    $table->boolean('es_version_actual')->default(true)->index();
                }
                if (! Schema::hasColumn('proyecto', 'fecha_version')) {
                    $table->timestamp('fecha_version')->nullable();
                }
                if (! Schema::hasColumn('proyecto', 'fecha_finalizacion')) {
                    $table->timestamp('fecha_finalizacion')->nullable();
                }
            });

            DB::table('proyecto')
                ->whereNull('id_proyecto_raiz')
                ->orderBy('id_proyecto')
                ->get()
                ->each(function ($project): void {
                    DB::table('proyecto')
                        ->where('id_proyecto', $project->id_proyecto)
                        ->update([
                            'id_proyecto_raiz' => $project->id_proyecto,
                            'numero_version' => 1,
                            'es_version_actual' => true,
                            'fecha_version' => $project->fecha ?? now(),
                            'fecha_finalizacion' => $project->aprobado === 'RV'
                                ? ($project->fecha_aprob ?? $project->fecha ?? now())
                                : null,
                        ]);
                });
        }

        if (Schema::hasTable('proyecto_item')) {
            Schema::table('proyecto_item', function (Blueprint $table): void {
                if (! Schema::hasColumn('proyecto_item', 'nombre_snapshot')) {
                    $table->string('nombre_snapshot', 500)->nullable();
                }
                if (! Schema::hasColumn('proyecto_item', 'grupo_snapshot')) {
                    $table->string('grupo_snapshot', 255)->nullable();
                }
                if (! Schema::hasColumn('proyecto_item', 'subgrupo_snapshot')) {
                    $table->string('subgrupo_snapshot', 255)->nullable();
                }
                if (! Schema::hasColumn('proyecto_item', 'unidad_snapshot')) {
                    $table->string('unidad_snapshot', 100)->nullable();
                }
                if (! Schema::hasColumn('proyecto_item', 'estado_catalogo_snapshot')) {
                    $table->string('estado_catalogo_snapshot', 20)->nullable();
                }
            });
        }

        if (Schema::hasTable('proyecto_item_insumo_snapshot')) {
            Schema::table('proyecto_item_insumo_snapshot', function (Blueprint $table): void {
                if (! Schema::hasColumn('proyecto_item_insumo_snapshot', 'id_item_insumo_origen')) {
                    $table->unsignedInteger('id_item_insumo_origen')->nullable()->index();
                }
                if (! Schema::hasColumn('proyecto_item_insumo_snapshot', 'excluido_por')) {
                    $table->unsignedInteger('excluido_por')->nullable();
                }
                if (! Schema::hasColumn('proyecto_item_insumo_snapshot', 'excluido_en')) {
                    $table->timestamp('excluido_en')->nullable();
                }
            });
        }

        if (! Schema::hasTable('proyecto_porcentaje_snapshot')) {
            Schema::create('proyecto_porcentaje_snapshot', function (Blueprint $table): void {
                $table->bigIncrements('id_snapshot');
                $table->unsignedInteger('id_proyecto');
                $table->string('formato', 20);
                $table->string('codigo', 100)->nullable();
                $table->string('descripcion', 255);
                $table->decimal('porcentaje', 12, 4)->default(0);
                $table->string('estado', 2)->default('AC');
                $table->timestamps();

                $table->index(['id_proyecto', 'formato'], 'pps_project_format_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('proyecto_porcentaje_snapshot');

        if (Schema::hasTable('proyecto_item_insumo_snapshot')) {
            Schema::table('proyecto_item_insumo_snapshot', function (Blueprint $table): void {
                foreach (['id_item_insumo_origen', 'excluido_por', 'excluido_en'] as $column) {
                    if (Schema::hasColumn('proyecto_item_insumo_snapshot', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('proyecto_item')) {
            Schema::table('proyecto_item', function (Blueprint $table): void {
                foreach (['nombre_snapshot', 'grupo_snapshot', 'subgrupo_snapshot', 'unidad_snapshot', 'estado_catalogo_snapshot'] as $column) {
                    if (Schema::hasColumn('proyecto_item', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('proyecto')) {
            Schema::table('proyecto', function (Blueprint $table): void {
                foreach (['id_proyecto_raiz', 'id_version_origen', 'numero_version', 'es_version_actual', 'fecha_version', 'fecha_finalizacion'] as $column) {
                    if (Schema::hasColumn('proyecto', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};

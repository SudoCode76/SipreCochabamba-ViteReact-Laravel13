<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('proyecto') && ! Schema::hasColumn('proyecto', 'es_plantilla')) {
            Schema::table('proyecto', function (Blueprint $table): void {
                $table->boolean('es_plantilla')->default(false)->after('estado');
            });
        }

        if (Schema::hasTable('funcion')) {
            $exists = DB::table('funcion')
                ->where('clase', 'PARAMETROS')
                ->where('nombre_funcion', 'PLANILLAS_PROYECTO')
                ->exists();

            if (! $exists) {
                DB::table('funcion')->insert([
                    'nombre_funcion' => 'PLANILLAS_PROYECTO',
                    'descripcion' => 'Gestion de planillas de proyecto',
                    'clase' => 'PARAMETROS',
                    'estado' => 'AC',
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('proyecto') && Schema::hasColumn('proyecto', 'es_plantilla')) {
            Schema::table('proyecto', function (Blueprint $table): void {
                $table->dropColumn('es_plantilla');
            });
        }
    }
};

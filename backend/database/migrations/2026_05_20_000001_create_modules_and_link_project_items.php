<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('modulo')) {
            Schema::create('modulo', function (Blueprint $table): void {
                $table->increments('id_modulo');
                $table->string('nombre_modulo', 150);
                $table->string('estado', 2)->default('AC');
                $table->unsignedInteger('id_usuario')->nullable();
                $table->date('fecha')->nullable();
            });
        }

        $generalId = DB::table('modulo')->whereRaw('LOWER(TRIM(nombre_modulo)) = ?', ['general'])->value('id_modulo');

        if (! $generalId) {
            $generalId = DB::table('modulo')->insertGetId([
                'nombre_modulo' => 'General',
                'estado' => 'AC',
                'id_usuario' => null,
                'fecha' => now()->toDateString(),
            ], 'id_modulo');
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SELECT setval(pg_get_serial_sequence('modulo', 'id_modulo'), COALESCE((SELECT MAX(id_modulo) FROM modulo), 1))");
        }

        if (Schema::hasTable('funcion')) {
            $exists = DB::table('funcion')
                ->where('clase', 'PARAMETROS')
                ->where('nombre_funcion', 'MODULOS')
                ->exists();

            if (! $exists) {
                DB::table('funcion')->insert([
                    'nombre_funcion' => 'MODULOS',
                    'descripcion' => 'Gestión de módulos de proyecto',
                    'clase' => 'PARAMETROS',
                    'estado' => 'AC',
                ]);
            }
        }

        if (Schema::hasTable('proyecto_item') && ! Schema::hasColumn('proyecto_item', 'id_modulo')) {
            Schema::table('proyecto_item', function (Blueprint $table): void {
                $table->unsignedInteger('id_modulo')->nullable()->after('id_item');
            });
        }

        if (Schema::hasTable('proyecto_item') && Schema::hasColumn('proyecto_item', 'id_modulo')) {
            DB::table('proyecto_item')
                ->whereNull('id_modulo')
                ->update(['id_modulo' => $generalId]);
        }

        if (! Schema::hasTable('proyecto_item') || ! Schema::hasColumn('proyecto_item', 'id_modulo')) {
            return;
        }

        try {
            Schema::table('proyecto_item', function (Blueprint $table): void {
                $table->foreign('id_modulo')->references('id_modulo')->on('modulo');
            });
        } catch (Throwable) {
            // Some legacy databases may already have the constraint with a manual name.
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('proyecto_item', 'id_modulo')) {
            Schema::table('proyecto_item', function (Blueprint $table): void {
                try {
                    $table->dropForeign(['id_modulo']);
                } catch (Throwable) {
                }

                $table->dropColumn('id_modulo');
            });
        }

        Schema::dropIfExists('modulo');
    }
};

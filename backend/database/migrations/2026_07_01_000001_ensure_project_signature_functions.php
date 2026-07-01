<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('funcion')) {
            return;
        }

        $functions = [
            [
                'clase' => 'PROYECTO',
                'nombre_funcion' => 'FIRMAR_REPORTES',
                'descripcion' => 'Firmar reportes de proyecto',
            ],
            [
                'clase' => 'PROYECTO',
                'nombre_funcion' => 'CONFIGURAR_FIRMAS',
                'descripcion' => 'Configurar reportes firmables',
            ],
            [
                'clase' => 'PROYECTO',
                'nombre_funcion' => 'FIRMAS_DIGITALES',
                'descripcion' => 'Gestionar firmas digitales de proyecto',
            ],
            [
                'clase' => 'ADMINISTRADOR',
                'nombre_funcion' => 'REPORTES_FIRMABLES',
                'descripcion' => 'Gestionar reportes firmables',
            ],
            [
                'clase' => 'ADMINISTRADOR',
                'nombre_funcion' => 'FIRMAS_DIGITALES',
                'descripcion' => 'Gestionar firmas digitales',
            ],
        ];

        foreach ($functions as $function) {
            $exists = DB::table('funcion')
                ->where('clase', $function['clase'])
                ->where('nombre_funcion', $function['nombre_funcion'])
                ->exists();

            if ($exists) {
                DB::table('funcion')
                    ->where('clase', $function['clase'])
                    ->where('nombre_funcion', $function['nombre_funcion'])
                    ->update([
                        'descripcion' => $function['descripcion'],
                        'estado' => 'AC',
                    ]);

                continue;
            }

            DB::table('funcion')->insert([
                'nombre_funcion' => $function['nombre_funcion'],
                'descripcion' => $function['descripcion'],
                'clase' => $function['clase'],
                'estado' => 'AC',
            ]);
        }
    }

    public function down(): void
    {
        // No se eliminan funciones para no romper permisos ya asignados en roles.
    }
};

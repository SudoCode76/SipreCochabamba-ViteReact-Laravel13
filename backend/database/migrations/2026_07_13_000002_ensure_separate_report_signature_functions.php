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

        foreach ([
            'FIRMAR_REPORTES' => 'Firmar reportes digitalmente',
            'FIRMAR_REPORTES_FISICOS' => 'Firmar reportes fisicamente y ajustar sus firmas',
        ] as $name => $description) {
            DB::table('funcion')->updateOrInsert(
                ['clase' => 'PROYECTO', 'nombre_funcion' => $name],
                ['descripcion' => $description, 'estado' => 'AC'],
            );
        }
    }

    public function down(): void
    {
        // Los permisos asignados a roles deben conservarse.
    }
};

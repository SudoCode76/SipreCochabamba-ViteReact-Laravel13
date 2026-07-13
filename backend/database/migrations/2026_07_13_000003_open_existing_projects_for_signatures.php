<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('proyecto') || ! Schema::hasColumn('proyecto', 'signature_access_mode')) {
            return;
        }

        DB::table('proyecto')
            ->where('signature_access_mode', 'selected')
            ->update(['signature_access_mode' => 'all']);
    }

    public function down(): void
    {
        // No se restringen proyectos que pudieron cambiar su configuración después del despliegue.
    }
};

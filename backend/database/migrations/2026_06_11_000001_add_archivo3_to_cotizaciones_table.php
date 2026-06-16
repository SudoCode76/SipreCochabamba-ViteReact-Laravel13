<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cotizaciones')) {
            return;
        }

        if (Schema::hasColumn('cotizaciones', 'archivo3')) {
            return;
        }

        Schema::table('cotizaciones', function (Blueprint $table): void {
            $table->string('archivo3', 180)->nullable()->after('archivo2');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cotizaciones')) {
            return;
        }

        if (! Schema::hasColumn('cotizaciones', 'archivo3')) {
            return;
        }

        Schema::table('cotizaciones', function (Blueprint $table): void {
            $table->dropColumn('archivo3');
        });
    }
};

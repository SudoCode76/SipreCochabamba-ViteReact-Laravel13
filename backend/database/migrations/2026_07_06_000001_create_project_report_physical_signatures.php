<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('usuario', 'firma_imagen_path')) {
            Schema::table('usuario', function (Blueprint $table): void {
                $table->string('firma_imagen_path')->nullable();
            });
        }

        if (! Schema::hasTable('project_report_physical_signatures')) {
            Schema::create('project_report_physical_signatures', function (Blueprint $table): void {
                $table->id();
                $table->integer('id_proyecto');
                $table->string('report_key', 80);
                $table->string('parameters_hash', 64);
                $table->string('logical_document_hash', 64);
                $table->integer('id_usuario');
                $table->string('signature_image_path');
                $table->timestamp('marked_at')->useCurrent();
                $table->timestamps();

                $table->unique([
                    'id_proyecto',
                    'report_key',
                    'parameters_hash',
                    'logical_document_hash',
                    'id_usuario',
                ], 'proj_phys_sig_unique');
                $table->index([
                    'id_proyecto',
                    'report_key',
                    'parameters_hash',
                    'logical_document_hash',
                ], 'proj_phys_sig_lookup');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_report_physical_signatures');

        if (Schema::hasColumn('usuario', 'firma_imagen_path')) {
            Schema::table('usuario', function (Blueprint $table): void {
                $table->dropColumn('firma_imagen_path');
            });
        }
    }
};

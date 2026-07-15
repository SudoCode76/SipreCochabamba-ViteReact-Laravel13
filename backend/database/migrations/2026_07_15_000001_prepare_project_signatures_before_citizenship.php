<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('proyecto') || ! Schema::hasTable('usuario')) {
            return;
        }

        Schema::table('proyecto', function (Blueprint $table): void {
            $table->timestamp('signature_signers_configured_at')->nullable();
            $table->timestamp('signature_signers_locked_at')->nullable();
        });

        Schema::create('project_version_signature_users', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('id_proyecto');
            $table->unsignedInteger('id_usuario');
            $table->string('signature_image_path')->nullable();
            $table->string('signature_image_hash', 64)->nullable();
            $table->timestamps();

            $table->unique(['id_proyecto', 'id_usuario'], 'project_version_signature_user_unique');
            $table->foreign('id_proyecto')->references('id_proyecto')->on('proyecto')->cascadeOnDelete();
            $table->foreign('id_usuario')->references('id_usuario')->on('usuario')->restrictOnDelete();
        });

        Schema::table('project_report_physical_signatures', function (Blueprint $table): void {
            $table->string('page_scope', 10)->default('all');
        });

        DB::table('proyecto')
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('project_report_signatures')
                    ->whereColumn('project_report_signatures.id_proyecto', 'proyecto.id_proyecto')
                    ->where('project_report_signatures.status', 'signed');
            })
            ->update(['signature_signers_locked_at' => now()]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('proyecto')) {
            return;
        }

        Schema::table('project_report_physical_signatures', function (Blueprint $table): void {
            $table->dropColumn('page_scope');
        });

        Schema::dropIfExists('project_version_signature_users');

        Schema::table('proyecto', function (Blueprint $table): void {
            $table->dropColumn(['signature_signers_configured_at', 'signature_signers_locked_at']);
        });
    }
};

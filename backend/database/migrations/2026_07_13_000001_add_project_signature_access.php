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
            $table->string('signature_access_mode', 20)->default('selected');
        });

        Schema::create('project_signature_authorized_users', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('id_proyecto_raiz');
            $table->unsignedInteger('id_usuario');
            $table->timestamps();

            $table->unique(['id_proyecto_raiz', 'id_usuario'], 'project_signature_user_unique');
            $table->foreign('id_proyecto_raiz')->references('id_proyecto')->on('proyecto')->cascadeOnDelete();
            $table->foreign('id_usuario')->references('id_usuario')->on('usuario')->restrictOnDelete();
        });

        $projects = DB::table('proyecto')
            ->orderBy('id_proyecto')
            ->get(['id_proyecto', 'id_proyecto_raiz', 'id_usuario']);

        foreach ($projects->groupBy(fn ($project): int => (int) ($project->id_proyecto_raiz ?: $project->id_proyecto)) as $rootId => $versions) {
            $root = $versions->firstWhere('id_proyecto', $rootId) ?: $versions->first();

            if (! $root?->id_usuario) {
                continue;
            }

            DB::table('project_signature_authorized_users')->insertOrIgnore([
                'id_proyecto_raiz' => $rootId,
                'id_usuario' => $root->id_usuario,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_signature_authorized_users');

        if (Schema::hasTable('proyecto') && Schema::hasColumn('proyecto', 'signature_access_mode')) {
            Schema::table('proyecto', function (Blueprint $table): void {
                $table->dropColumn('signature_access_mode');
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_signable_reports', function (Blueprint $table): void {
            $table->id();
            $table->string('report_key', 80)->unique();
            $table->string('name', 160);
            $table->string('description', 255)->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->timestamps();
        });

        Schema::create('project_report_signatures', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('id_proyecto')->index();
            $table->string('report_key', 80)->index();
            $table->json('parameters')->nullable();
            $table->string('parameters_hash', 64)->index();
            $table->string('status', 30)->default('pending')->index();
            $table->string('code', 160)->nullable()->index();
            $table->unsignedInteger('id_usuario')->nullable()->index();
            $table->string('base_file_path', 500)->nullable();
            $table->string('signed_file_path', 500)->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();

            $table->foreign('report_key')
                ->references('report_key')
                ->on('project_signable_reports')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });

        DB::table('project_signable_reports')->insert([
            [
                'report_key' => 'budget_by_group',
                'name' => 'Presupuesto por Rubros',
                'description' => 'PDF de presupuesto por rubros del proyecto.',
                'is_enabled' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'report_key' => 'budget_recalculation',
                'name' => 'Recalcular Precio por Rubro',
                'description' => 'PDF de presupuesto recalculado por fecha.',
                'is_enabled' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'report_key' => 'incidence_summary',
                'name' => 'Resumen por Incidencia',
                'description' => 'PDF de resumen por incidencia.',
                'is_enabled' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'report_key' => 'general_budget',
                'name' => 'Presupuesto General',
                'description' => 'PDF de presupuesto general del proyecto.',
                'is_enabled' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'report_key' => 'input_breakdown',
                'name' => 'Desglose de Insumos del Proyecto',
                'description' => 'PDF de desglose de insumos del proyecto.',
                'is_enabled' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'report_key' => 'inputs_report',
                'name' => 'Reporte de Insumos',
                'description' => 'PDF consolidado de insumos del proyecto.',
                'is_enabled' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'report_key' => 'grouped_inputs_report',
                'name' => 'Proyecto Agrupado por Insumos',
                'description' => 'PDF de proyecto agrupado por insumos.',
                'is_enabled' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'report_key' => 'unit_prices',
                'name' => 'Precios Unitarios',
                'description' => 'PDF de análisis de precios unitarios.',
                'is_enabled' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'report_key' => 'specifications',
                'name' => 'Todas las Especificaciones',
                'description' => 'PDF consolidado de especificaciones técnicas.',
                'is_enabled' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('project_report_signatures');
        Schema::dropIfExists('project_signable_reports');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_report_signatures', function (Blueprint $table): void {
            if (! Schema::hasColumn('project_report_signatures', 'id_item')) {
                $table->unsignedBigInteger('id_item')->nullable()->after('id_proyecto');
                $table->foreign('id_item')->references('id_item')->on('item')->nullOnDelete();
            }
        });

        Schema::table('project_report_physical_signatures', function (Blueprint $table): void {
            if (! Schema::hasColumn('project_report_physical_signatures', 'id_item')) {
                $table->unsignedBigInteger('id_item')->nullable()->after('id_proyecto');
                $table->foreign('id_item')->references('id_item')->on('item')->nullOnDelete();
            }
        });

        DB::statement('ALTER TABLE project_report_signatures ALTER COLUMN id_proyecto DROP NOT NULL');
        DB::statement('ALTER TABLE project_report_physical_signatures ALTER COLUMN id_proyecto DROP NOT NULL');

        $reports = [
            ['item_unit_price_analysis', 'Análisis de precio unitario', 'PDF de análisis de precio unitario de ítem.'],
            ['item_price_recalculation', 'Recalcular precio de ítem', 'PDF de recálculo de precio de ítem.'],
            ['item_material_breakdown', 'Desglose de materiales de ítem', 'PDF de desglose de materiales de ítem.'],
            ['item_labor_breakdown', 'Desglose de mano de obra de ítem', 'PDF de desglose de mano de obra de ítem.'],
            ['item_machinery_breakdown', 'Desglose de maquinaria de ítem', 'PDF de desglose de maquinaria de ítem.'],
            ['item_breakdown_recalculation', 'Desglose histórico de ítem', 'PDF de desglose histórico/recalculado de ítem.'],
        ];

        foreach ($reports as [$key, $name, $description]) {
            DB::table('project_signable_reports')->updateOrInsert(
                ['report_key' => $key],
                [
                    'name' => $name,
                    'description' => $description,
                    'is_enabled' => false,
                    'requires_finalized_project' => false,
                    'validity_days' => 30,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('project_signable_reports')
            ->whereIn('report_key', [
                'item_unit_price_analysis',
                'item_price_recalculation',
                'item_material_breakdown',
                'item_labor_breakdown',
                'item_machinery_breakdown',
                'item_breakdown_recalculation',
            ])
            ->delete();

        Schema::table('project_report_physical_signatures', function (Blueprint $table): void {
            if (Schema::hasColumn('project_report_physical_signatures', 'id_item')) {
                $table->dropForeign(['id_item']);
                $table->dropColumn('id_item');
            }
        });

        Schema::table('project_report_signatures', function (Blueprint $table): void {
            if (Schema::hasColumn('project_report_signatures', 'id_item')) {
                $table->dropForeign(['id_item']);
                $table->dropColumn('id_item');
            }
        });
    }
};

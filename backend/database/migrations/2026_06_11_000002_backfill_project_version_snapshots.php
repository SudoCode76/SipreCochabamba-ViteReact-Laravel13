<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('proyecto')
            || ! Schema::hasTable('proyecto_item')
            || ! Schema::hasTable('proyecto_item_insumo_snapshot')
            || ! Schema::hasTable('proyecto_porcentaje_snapshot')
        ) {
            return;
        }

        $this->backfillProjectItemDescriptions();
        $this->backfillInputSnapshots();
        $this->backfillPercentageSnapshots();
    }

    public function down(): void
    {
        // Los snapshots pueden haber sido usados por versiones nuevas; no se eliminan.
    }

    private function backfillProjectItemDescriptions(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            UPDATE proyecto_item AS pi
            SET nombre_snapshot = COALESCE(pi.nombre_snapshot, i.item),
                grupo_snapshot = COALESCE(pi.grupo_snapshot, g.nombre_grupo),
                subgrupo_snapshot = COALESCE(pi.subgrupo_snapshot, sg.descripcion),
                unidad_snapshot = COALESCE(pi.unidad_snapshot, um.abreviatura, um.descripcion),
                estado_catalogo_snapshot = COALESCE(pi.estado_catalogo_snapshot, i.estado, 'NO_DISPONIBLE')
            FROM item AS i
            LEFT JOIN grupo AS g ON g.id_grupo = i.grupo
            LEFT JOIN sub_grupo AS sg ON sg.id_subgrupo = i.subgrupo
            LEFT JOIN unidad_medida AS um ON um.id_unidad_medida = i.id_unidad
            WHERE pi.id_item = i.id_item
              AND pi.estado = 'AC'
        SQL);
    }

    private function backfillInputSnapshots(): void
    {
        $source = DB::table('proyecto_item as pi')
            ->join('item_insumo as ii', 'ii.id_item', '=', 'pi.id_item')
            ->join('insumo as i', 'i.id_insumo', '=', 'ii.id_insumo')
            ->leftJoin('unidad_medida as um', 'um.id_unidad_medida', '=', 'i.unidad_medida')
            ->where('pi.estado', 'AC')
            ->where('ii.estado', 'AC')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('proyecto_item_insumo_snapshot as existing')
                    ->whereColumn('existing.id_proyecto_item', 'pi.id_proyecto_item')
                    ->whereColumn('existing.id_item_insumo_origen', 'ii.id_item_insumo');
            })
            ->selectRaw("
                pi.id_proyecto_item,
                i.id_insumo,
                ii.id_item_insumo,
                i.descripcion,
                COALESCE(ii.tipo, i.tipo),
                COALESCE(um.abreviatura, um.descripcion),
                COALESCE(ii.cantidad, 0),
                COALESCE(i.precio, 0),
                COALESCE(ii.cantidad, 0) * COALESCE(i.precio, 0),
                CASE WHEN i.estado = 'AC' THEN 'AC' ELSE 'DC' END,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            ");

        DB::table('proyecto_item_insumo_snapshot')->insertUsing([
            'id_proyecto_item',
            'id_insumo',
            'id_item_insumo_origen',
            'descripcion',
            'tipo',
            'unidad',
            'cantidad',
            'precio_unitario',
            'parcial',
            'estado',
            'created_at',
            'updated_at',
        ], $source);
    }

    private function backfillPercentageSnapshots(): void
    {
        $formats = [
            'PCA' => 'porcentaje_calculo',
            'PC_OBRAS' => 'porcentaje_calculo_obras',
            'PC_FPS' => 'porcentaje_calculo_fps',
            'PC_FNDR' => 'porcentaje_calculo_fndr',
            'PC_UPRE' => 'porcentaje_calculo_upre',
            'PC_PROMAN' => 'porcentaje_calculo_proman',
        ];

        foreach ($formats as $format => $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $source = DB::table('proyecto as p')
                ->crossJoin($table.' as percentage')
                ->where(function ($query): void {
                    $query->where('p.es_plantilla', false)->orWhereNull('p.es_plantilla');
                })
                ->where('percentage.estado', 'AC')
                ->whereNotExists(function ($query) use ($format): void {
                    $query->selectRaw('1')
                        ->from('proyecto_porcentaje_snapshot as existing')
                        ->whereColumn('existing.id_proyecto', 'p.id_proyecto')
                        ->where('existing.formato', $format);
                })
                ->selectRaw(
                    'p.id_proyecto, ? as formato, percentage.codigo, percentage.descripcion, percentage.porcentaje, percentage.estado, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP',
                    [$format],
                );

            DB::table('proyecto_porcentaje_snapshot')->insertUsing([
                'id_proyecto',
                'formato',
                'codigo',
                'descripcion',
                'porcentaje',
                'estado',
                'created_at',
                'updated_at',
            ], $source);
        }
    }
};

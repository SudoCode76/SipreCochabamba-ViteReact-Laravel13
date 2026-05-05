<?php

namespace Tests\Concerns;

use App\Models\FndrCalculationPercentage;
use App\Models\FpsCalculationPercentage;
use App\Models\GeneralCalculationPercentage;
use App\Models\GroupCatalog;
use App\Models\Item;
use App\Models\ItemInput;
use App\Models\ObrasCalculationPercentage;
use App\Models\PromanCalculationPercentage;
use App\Models\SubgroupCatalog;
use App\Models\UpreCalculationPercentage;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

trait InteractsWithLegacyItems
{
    protected function setUpLegacyItemSchema(): void
    {
        Schema::create('grupo', function (Blueprint $table): void {
            $table->increments('id_grupo');
            $table->string('nombre_grupo', 100)->nullable();
            $table->string('estado', 2)->nullable();
            $table->string('codigo_grupo', 30)->nullable();
        });

        Schema::create('sub_grupo', function (Blueprint $table): void {
            $table->increments('id_subgrupo');
            $table->unsignedInteger('id_grupo')->nullable();
            $table->string('descripcion', 100)->nullable();
            $table->string('estado', 2)->nullable();
            $table->string('codigo', 30)->nullable();

            $table->foreign('id_grupo')->references('id_grupo')->on('grupo');
        });

        Schema::create('item', function (Blueprint $table): void {
            $table->increments('id_item');
            $table->string('item', 100)->nullable();
            $table->unsignedInteger('id_unidad')->nullable();
            $table->float('precio')->nullable();
            $table->string('estado', 2)->nullable();
            $table->unsignedInteger('id_usuario')->nullable();
            $table->string('cod', 30)->nullable();
            $table->unsignedInteger('grupo')->nullable();
            $table->unsignedInteger('subgrupo')->nullable();
            $table->string('especificacion', 200)->nullable();
            $table->string('ficha', 200)->nullable();
            $table->date('fecha_item')->nullable();

            $table->foreign('id_unidad')->references('id_unidad_medida')->on('unidad_medida');
        });

        if (Schema::hasTable('item_insumo') === false) {
            Schema::create('item_insumo', function (Blueprint $table): void {
                $table->increments('id_item_insumo');
                $table->unsignedInteger('id_insumo')->nullable();
                $table->unsignedInteger('id_item')->nullable();
                $table->string('estado', 2)->nullable();
                $table->unsignedInteger('id_usuario')->nullable();
                $table->float('cantidad')->nullable();
                $table->date('fecha')->nullable();
                $table->unsignedInteger('tipo')->nullable();

                $table->foreign('id_insumo')->references('id_insumo')->on('insumo');
                $table->foreign('id_item')->references('id_item')->on('item');
            });
        }

        Schema::create('porcentaje_calculo_fndr', function (Blueprint $table): void {
            $table->increments('id_porcentaje');
            $table->string('descripcion', 100)->nullable();
            $table->float('porcentaje')->nullable();
            $table->string('codigo', 30)->nullable();
            $table->string('observacion')->nullable();
            $table->string('estado', 2)->nullable();
            $table->unsignedInteger('usuario')->nullable();
        });

        Schema::create('porcentaje_calculo_upre', function (Blueprint $table): void {
            $table->increments('id_porcentaje');
            $table->string('descripcion', 100)->nullable();
            $table->float('porcentaje')->nullable();
            $table->string('codigo', 30)->nullable();
            $table->string('observacion')->nullable();
            $table->string('estado', 2)->nullable();
            $table->unsignedInteger('usuario')->nullable();
        });

        Schema::create('porcentaje_calculo_fps', function (Blueprint $table): void {
            $table->increments('id_porcentaje');
            $table->string('descripcion', 100)->nullable();
            $table->float('porcentaje')->nullable();
            $table->string('codigo', 30)->nullable();
            $table->string('observacion')->nullable();
            $table->string('estado', 2)->nullable();
            $table->unsignedInteger('usuario')->nullable();
        });

        Schema::create('porcentaje_calculo', function (Blueprint $table): void {
            $table->increments('id_porcentaje');
            $table->string('descripcion', 100)->nullable();
            $table->float('porcentaje')->nullable();
            $table->string('estado', 2)->nullable();
            $table->unsignedInteger('usuario')->nullable();
            $table->string('codigo', 30)->nullable();
            $table->string('observacion')->nullable();
        });

        Schema::create('porcentaje_calculo_obras', function (Blueprint $table): void {
            $table->increments('id_porcentaje');
            $table->string('descripcion', 100)->nullable();
            $table->float('porcentaje')->nullable();
            $table->string('estado', 2)->nullable();
            $table->unsignedInteger('usuario')->nullable();
            $table->string('codigo', 30)->nullable();
            $table->string('observacion')->nullable();
        });

        Schema::create('porcentaje_calculo_proman', function (Blueprint $table): void {
            $table->increments('id_porcentaje');
            $table->string('descripcion')->nullable();
            $table->float('porcentaje')->nullable();
            $table->string('codigo')->nullable();
            $table->string('observacion')->nullable();
            $table->string('estado')->nullable();
            $table->string('usuario')->nullable();
        });
    }

    protected function createGroup(array $overrides = []): GroupCatalog
    {
        return GroupCatalog::query()->create(array_merge([
            'id_grupo' => 1,
            'nombre_grupo' => 'OBRAS PRELIMINARES',
            'estado' => 'AC',
            'codigo_grupo' => '001-OPR',
        ], $overrides));
    }

    protected function createSubgroup(array $overrides = []): SubgroupCatalog
    {
        return SubgroupCatalog::query()->create(array_merge([
            'id_subgrupo' => 1,
            'id_grupo' => 1,
            'descripcion' => 'PRELIMINARES',
            'estado' => 'AC',
            'codigo' => 'PRE',
        ], $overrides));
    }

    protected function createItemRecord(array $overrides = []): Item
    {
        return Item::query()->create(array_merge([
            'id_item' => 1,
            'item' => 'ITEM FNDR TEST',
            'id_unidad' => 1,
            'precio' => 0,
            'estado' => 'AC',
            'id_usuario' => 1,
            'cod' => 'ITM-001',
            'grupo' => 1,
            'subgrupo' => 1,
            'fecha_item' => now()->toDateString(),
        ], $overrides));
    }

    protected function createItemInputRecord(array $overrides = []): ItemInput
    {
        return ItemInput::query()->create(array_merge([
            'id_item_insumo' => 1,
            'id_insumo' => 1,
            'id_item' => 1,
            'estado' => 'AC',
            'id_usuario' => 1,
            'cantidad' => 1,
            'fecha' => now()->toDateString(),
            'tipo' => null,
        ], $overrides));
    }

    protected function seedFndrPercentages(): void
    {
        $rows = [
            [1, 'CARGAS SOCIALES', 50, 'FNDR-CS'],
            [2, 'IMPUESTO AL VALOR AGREGADO', 10, 'FNDR-IVA'],
            [3, 'HERRAMIENTAS MENORES', 5, 'FNDR-HM'],
            [4, 'GASTOS GRALES Y ADMINISTRATIVOS', 10, 'FNDR-GGA'],
            [5, 'UTILIDAD', 10, 'FNDR-UT'],
            [6, 'IMPUESTO A LAS TRANSACCIONES', 3, 'FNDR-IT'],
        ];

        foreach ($rows as [$id, $description, $percentage, $code]) {
            FndrCalculationPercentage::query()->create([
                'id_porcentaje' => $id,
                'descripcion' => $description,
                'porcentaje' => $percentage,
                'codigo' => $code,
                'observacion' => null,
                'estado' => 'AC',
                'usuario' => 1,
            ]);
        }
    }

    protected function seedUprePercentages(): void
    {
        $rows = [
            [1, 'CARGAS SOCIALES', 30, 'UPRE-CS'],
            [2, 'IMPUESTO AL VALOR AGREGADO', 14.94, 'UPRE-IVA'],
            [3, 'HERRAMIENTAS MENORES', 5, 'UPRE-HM'],
            [4, 'GASTOS GRALES Y ADMINISTRATIVOS', 7, 'UPRE-GGA'],
            [5, 'UTILIDAD', 7, 'UPRE-UT'],
            [6, 'IMPUESTO A LAS TRANSACCIONES', 3.09, 'UPRE-IT'],
        ];

        foreach ($rows as [$id, $description, $percentage, $code]) {
            UpreCalculationPercentage::query()->create([
                'id_porcentaje' => $id,
                'descripcion' => $description,
                'porcentaje' => $percentage,
                'codigo' => $code,
                'observacion' => null,
                'estado' => 'AC',
                'usuario' => 1,
            ]);
        }
    }

    protected function seedFpsPercentages(): void
    {
        $rows = [
            [1, 'CARGAS SOCIALES', 30, 'FPS-CS'],
            [2, 'IMPUESTO AL VALOR AGREGADO', 14.94, 'FPS-IVA'],
            [3, 'HERRAMIENTAS MENORES', 5, 'FPS-HM'],
            [4, 'GASTOS GRALES Y ADMINISTRATIVOS', 10, 'FPS-GGA'],
            [5, 'UTILIDAD', 15, 'FPS-UT'],
            [6, 'IMPUESTO A LAS TRANSACCIONES', 3.09, 'FPS-IT'],
        ];

        foreach ($rows as [$id, $description, $percentage, $code]) {
            FpsCalculationPercentage::query()->create([
                'id_porcentaje' => $id,
                'descripcion' => $description,
                'porcentaje' => $percentage,
                'codigo' => $code,
                'observacion' => null,
                'estado' => 'AC',
                'usuario' => 1,
            ]);
        }
    }

    protected function seedGeneralPercentages(): void
    {
        $rows = [
            [1, 'CARGAS SOCIALES', 58, 'PC-CS'],
            [2, 'IMPUESTO AL VALOR AGREGADO', 14.94, 'PC-IVA'],
            [3, 'HERRAMIENTAS MENORES', 5, 'PC-HM'],
            [4, 'GASTOS GRALES Y ADMINISTRATIVOS', 10, 'PC-GGA'],
            [5, 'UTILIDAD', 7, 'PC-UT'],
            [6, 'IMPUESTO A LAS TRANSACCIONES', 3.09, 'PC-IT'],
        ];

        foreach ($rows as [$id, $description, $percentage, $code]) {
            GeneralCalculationPercentage::query()->create([
                'id_porcentaje' => $id,
                'descripcion' => $description,
                'porcentaje' => $percentage,
                'estado' => 'AC',
                'usuario' => 1,
                'codigo' => $code,
                'observacion' => null,
            ]);
        }
    }

    protected function seedObrasPercentages(): void
    {
        $rows = [
            [1, 'CARGAS SOCIALES', 0, 'OP-CS'],
            [2, 'IMPUESTO AL VALOR AGREGADO', 0, 'OP-IVA'],
            [3, 'HERRAMIENTAS MENORES', 0, 'OP-HM'],
            [4, 'GASTOS GRALES Y ADMINISTRATIVOS', 0, 'OP-GGA'],
            [5, 'UTILIDAD', 0, 'OP-UT'],
            [6, 'IMPUESTO A LAS TRANSACCIONES', 0, 'OP-IT'],
        ];

        foreach ($rows as [$id, $description, $percentage, $code]) {
            ObrasCalculationPercentage::query()->create([
                'id_porcentaje' => $id,
                'descripcion' => $description,
                'porcentaje' => $percentage,
                'estado' => 'AC',
                'usuario' => 1,
                'codigo' => $code,
                'observacion' => null,
            ]);
        }
    }

    protected function seedPromanPercentages(): void
    {
        $rows = [
            [1, 'HERRAMIENTAS MENORES', 5, 'UPRE-HM'],
            [2, 'IMPUESTO AL VALOR AGREGADO', 0, 'UPRE-IVA'],
            [3, 'IMPUESTO A LAS TRANSACCIONES', 0, 'UPRE-IT'],
            [4, 'CARGAS SOCIALES', 57, 'UPRE-CS'],
            [5, 'UTILIDAD', 0, 'UPRE-UT'],
            [6, 'GASTOS GRALES Y ADMINISTRATIVOS', 10, 'UPRE-GGA'],
        ];

        foreach ($rows as [$id, $description, $percentage, $code]) {
            PromanCalculationPercentage::query()->create([
                'id_porcentaje' => $id,
                'descripcion' => $description,
                'porcentaje' => $percentage,
                'codigo' => $code,
                'observacion' => null,
                'estado' => 'AC',
                'usuario' => '1',
            ]);
        }
    }
}

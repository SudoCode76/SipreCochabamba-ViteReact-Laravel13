<?php

namespace Tests\Concerns;

use App\Models\Authorization;
use App\Models\Input;
use App\Models\InputCategory;
use App\Models\InputHistory;
use App\Models\InputLog;
use App\Models\InputQuote;
use App\Models\InputType;
use App\Models\ItemInput;
use App\Models\UnitMeasure;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

trait InteractsWithLegacyInputs
{
    protected function setUpLegacyInputSchema(): void
    {
        Schema::create('tipo_insumo', function (Blueprint $table): void {
            $table->increments('id_tipo');
            $table->string('descripcion', 80)->nullable();
            $table->string('estado', 2)->nullable();
        });

        if (Schema::hasTable('categoria_insumo') === false) {
            Schema::create('categoria_insumo', function (Blueprint $table): void {
                $table->increments('id_categoria');
                $table->string('descripcion', 80)->nullable();
                $table->string('estado', 2)->nullable();
                $table->unsignedInteger('usuario')->nullable();
                $table->date('fecha')->nullable();
            });
        }

        Schema::create('unidad_medida', function (Blueprint $table): void {
            $table->increments('id_unidad_medida');
            $table->string('descripcion', 30)->nullable();
            $table->string('estado', 2)->nullable();
            $table->string('abreviatura', 50)->nullable();
            $table->string('usuario')->nullable();
        });

        Schema::create('insumo', function (Blueprint $table): void {
            $table->increments('id_insumo');
            $table->string('descripcion', 100)->nullable();
            $table->unsignedInteger('unidad_medida')->nullable();
            $table->decimal('precio', 10, 2)->nullable();
            $table->unsignedInteger('tipo')->nullable();
            $table->unsignedInteger('id_categoria')->nullable();
            $table->string('estado', 2)->nullable();
            $table->unsignedInteger('usuario')->nullable();
            $table->date('fecha')->nullable();
            $table->integer('solicitud')->nullable();
            $table->string('cod', 30)->nullable();
            $table->date('fecha_cotiz')->nullable();
            $table->string('observacion', 300)->nullable();

            $table->foreign('unidad_medida')->references('id_unidad_medida')->on('unidad_medida');
            $table->foreign('tipo')->references('id_tipo')->on('tipo_insumo');
            $table->foreign('id_categoria')->references('id_categoria')->on('categoria_insumo');
        });

        Schema::create('historial_insumo', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('descripcion', 100)->nullable();
            $table->unsignedInteger('id_insumo')->nullable();
            $table->decimal('precio', 10, 2)->nullable();
            $table->unsignedInteger('tipo')->nullable();
            $table->unsignedInteger('id_categoria')->nullable();
            $table->unsignedInteger('unidad_medida')->nullable();
            $table->text('accion')->nullable();
            $table->unsignedInteger('usuario')->nullable();
            $table->timestamp('fecha')->nullable();
            $table->string('estado')->nullable();
            $table->string('ip')->nullable();
            $table->string('nombre_usuario')->nullable();

            $table->foreign('id_insumo')->references('id_insumo')->on('insumo');
        });

        Schema::create('log_insumo', function (Blueprint $table): void {
            $table->increments('id_log');
            $table->string('descripcion', 100)->nullable();
            $table->unsignedInteger('id_insumo')->nullable();
            $table->decimal('precio', 10, 2)->nullable();
            $table->unsignedInteger('tipo')->nullable();
            $table->unsignedInteger('id_categoria')->nullable();
            $table->unsignedInteger('unidad_medida')->nullable();
            $table->string('accion', 2)->nullable();
            $table->unsignedInteger('usuario')->nullable();
            $table->date('fecha')->nullable();
            $table->string('estado')->nullable();

            $table->foreign('id_insumo')->references('id_insumo')->on('insumo');
        });

        Schema::create('cotizaciones', function (Blueprint $table): void {
            $table->increments('id_cotizacion');
            $table->unsignedInteger('id_insumo')->nullable();
            $table->string('condicion', 10)->nullable();
            $table->string('estado', 2)->nullable();
            $table->unsignedInteger('id_log_insumo')->nullable();
            $table->string('archivo', 180)->nullable();
            $table->date('fecha')->nullable();
            $table->string('archivo1', 180)->nullable();
            $table->string('archivo2', 180)->nullable();
            $table->integer('id_solicitud')->nullable();

            $table->foreign('id_insumo')->references('id_insumo')->on('insumo');
        });

        if (Schema::hasTable('item_insumo') === false) {
            Schema::create('item_insumo', function (Blueprint $table): void {
                $table->increments('id_item_insumo');
                $table->unsignedInteger('id_insumo')->nullable();
                $table->unsignedInteger('id_item')->nullable();
                $table->string('estado', 2)->nullable();
                $table->unsignedInteger('id_usuario')->nullable();
                $table->double('cantidad')->nullable();
                $table->date('fecha')->nullable();
                $table->unsignedInteger('tipo')->nullable();
            });
        }

        if (Schema::hasTable('autorizaciones') === false) {
            Schema::create('autorizaciones', function (Blueprint $table): void {
                $table->increments('id_autorizacion');
                $table->unsignedInteger('num_sec')->nullable();
                $table->unsignedInteger('id_elemento')->nullable();
                $table->string('elemento', 150)->nullable();
                $table->string('tipo_elemento', 50)->nullable();
                $table->string('tabla', 50)->nullable();
                $table->unsignedInteger('solicitante')->nullable();
                $table->string('estado', 2)->nullable();
                $table->string('nro_autorizacion', 100)->nullable();
                $table->unsignedInteger('usuario_adm')->nullable();
                $table->timestamp('fecha')->nullable();
                $table->timestamp('fecha_aut')->nullable();
            });
        }
    }

    protected function createInputType(array $overrides = []): InputType
    {
        return InputType::query()->create(array_merge([
            'id_tipo' => 1,
            'descripcion' => 'MATERIAL',
            'estado' => 'AC',
        ], $overrides));
    }

    protected function createUnitMeasure(array $overrides = []): UnitMeasure
    {
        return UnitMeasure::query()->create(array_merge([
            'id_unidad_medida' => 1,
            'descripcion' => 'Pieza',
            'estado' => 'AC',
            'abreviatura' => 'pza.',
            'usuario' => '1',
        ], $overrides));
    }

    protected function createInputCategory(array $overrides = []): InputCategory
    {
        return InputCategory::query()->create(array_merge([
            'id_categoria' => 1,
            'descripcion' => 'Categoria demo',
            'estado' => 'AC',
            'usuario' => 1,
            'fecha' => now()->toDateString(),
        ], $overrides));
    }

    protected function createInput(array $overrides = []): Input
    {
        $typeId = $overrides['tipo'] ?? 1;
        $unitMeasureId = $overrides['unidad_medida'] ?? 1;
        $categoryId = $overrides['id_categoria'] ?? 1;

        if (InputType::query()->whereKey($typeId)->exists() === false) {
            $this->createInputType(['id_tipo' => $typeId]);
        }

        if (UnitMeasure::query()->whereKey($unitMeasureId)->exists() === false) {
            $this->createUnitMeasure(['id_unidad_medida' => $unitMeasureId]);
        }

        if ($categoryId !== null && InputCategory::query()->whereKey($categoryId)->exists() === false) {
            $this->createInputCategory(['id_categoria' => $categoryId]);
        }

        return Input::query()->create(array_merge([
            'id_insumo' => 1,
            'descripcion' => 'Acero estructural',
            'unidad_medida' => $unitMeasureId,
            'precio' => 15.36,
            'tipo' => $typeId,
            'id_categoria' => $categoryId,
            'estado' => 'AC',
            'usuario' => 1,
            'fecha' => now()->toDateString(),
            'solicitud' => null,
            'cod' => 'INS-001',
            'fecha_cotiz' => now()->toDateString(),
            'observacion' => 'Observacion inicial',
        ], $overrides));
    }

    protected function createInputLog(array $overrides = []): InputLog
    {
        return InputLog::query()->create(array_merge([
            'id_log' => 1,
            'descripcion' => 'Acero estructural',
            'id_insumo' => 1,
            'precio' => 15.36,
            'tipo' => 1,
            'id_categoria' => 1,
            'unidad_medida' => 1,
            'accion' => 'RG',
            'usuario' => 1,
            'fecha' => now()->toDateString(),
            'estado' => 'AC',
        ], $overrides));
    }

    protected function createInputHistory(array $overrides = []): InputHistory
    {
        return InputHistory::query()->create(array_merge([
            'id' => 1,
            'descripcion' => 'Acero estructural',
            'id_insumo' => 1,
            'precio' => 15.36,
            'tipo' => 1,
            'id_categoria' => 1,
            'unidad_medida' => 1,
            'accion' => 'MODIFICADO',
            'usuario' => 1,
            'fecha' => now(),
            'estado' => 'AC',
            'ip' => '127.0.0.1',
            'nombre_usuario' => 'Usuario Demo',
        ], $overrides));
    }

    protected function createInputQuote(array $overrides = []): InputQuote
    {
        return InputQuote::query()->create(array_merge([
            'id_cotizacion' => 1,
            'id_insumo' => 1,
            'condicion' => 'CONTADO',
            'estado' => 'AC',
            'id_log_insumo' => 1,
            'archivo' => 'public/cotizaciones/cotizacion.pdf',
            'fecha' => now()->toDateString(),
            'archivo1' => 'public/cotizaciones/anexo1.pdf',
            'archivo2' => 'public/cotizaciones/anexo2.pdf',
            'id_solicitud' => null,
        ], $overrides));
    }

    protected function createItemInput(array $overrides = []): ItemInput
    {
        return ItemInput::query()->create(array_merge([
            'id_item_insumo' => 1,
            'id_insumo' => 1,
            'id_item' => 1,
            'estado' => 'AC',
            'id_usuario' => 1,
            'cantidad' => 1,
            'fecha' => now()->toDateString(),
            'tipo' => 1,
        ], $overrides));
    }

    protected function createAuthorization(array $overrides = []): Authorization
    {
        return Authorization::query()->create(array_merge([
            'id_autorizacion' => 1,
            'num_sec' => 1,
            'id_elemento' => 1,
            'elemento' => 'Acero estructural',
            'tipo_elemento' => 'insumo',
            'tabla' => 'insumo',
            'solicitante' => 1,
            'estado' => 'PE',
            'nro_autorizacion' => 'AUTH-001',
            'usuario_adm' => null,
            'fecha' => now(),
            'fecha_aut' => null,
        ], $overrides));
    }
}

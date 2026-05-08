<?php

namespace Tests\Concerns;

use App\Models\InputQuote;
use App\Models\InputRequest;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

trait InteractsWithLegacyInputRequests
{
    protected function setUpLegacyInputRequestSchema(): void
    {
        Schema::create('solicitud_insumo', function (Blueprint $table): void {
            $table->increments('id_solicitud');
            $table->string('descripcion', 100)->nullable();
            $table->decimal('precio', 10, 2)->nullable();
            $table->unsignedInteger('unidad_medida')->nullable();
            $table->unsignedInteger('tipo')->nullable();
            $table->string('ubicacion', 255)->nullable();
            $table->string('justificacion', 500)->nullable();
            $table->unsignedInteger('usuario_solicitante')->nullable();
            $table->string('estado_aprobacion', 2)->nullable();
            $table->string('notificacion', 255)->nullable();
            $table->string('observacion', 500)->nullable();
            $table->unsignedInteger('usuario_aprobacion')->nullable();
            $table->date('fecha_aprobacion')->nullable();
            $table->string('archivo', 180)->nullable();
            $table->string('archivo1', 180)->nullable();
            $table->string('archivo2', 180)->nullable();
            $table->date('fecha')->nullable();
            $table->timestamp('fecha_modificacion')->nullable();
        });
    }

    protected function createInputRequestRecord(array $overrides = []): InputRequest
    {
        return InputRequest::query()->create(array_merge([
            'id_solicitud' => 1,
            'descripcion' => 'SOLICITUD DE ACERO',
            'precio' => 10.50,
            'unidad_medida' => 1,
            'tipo' => 1,
            'ubicacion' => 'ALMACEN CENTRAL',
            'justificacion' => 'REPOSICION DE STOCK',
            'usuario_solicitante' => 1,
            'estado_aprobacion' => 'PD',
            'notificacion' => 'Pendiente de revision',
            'observacion' => null,
            'usuario_aprobacion' => null,
            'fecha_aprobacion' => null,
            'archivo' => 'archivos/cotizaciones/valido/solicitud.pdf',
            'archivo1' => 'archivos/cotizaciones/propuesto_1/alternativa-1.pdf',
            'archivo2' => 'archivos/cotizaciones/propuesto_2/alternativa-2.pdf',
            'fecha' => now()->toDateString(),
            'fecha_modificacion' => now(),
        ], $overrides));
    }

    protected function createInputRequestQuote(array $overrides = []): InputQuote
    {
        return InputQuote::query()->create(array_merge([
            'id_cotizacion' => 1,
            'id_insumo' => null,
            'condicion' => 'VALIDO',
            'estado' => 'AC',
            'id_log_insumo' => null,
            'archivo' => 'archivos/cotizaciones/valido/solicitud.pdf',
            'fecha' => now()->toDateString(),
            'archivo1' => 'archivos/cotizaciones/propuesto_1/alternativa-1.pdf',
            'archivo2' => 'archivos/cotizaciones/propuesto_2/alternativa-2.pdf',
            'id_solicitud' => 1,
        ], $overrides));
    }
}

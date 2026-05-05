<?php

namespace Tests\Feature\Input;

use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithLegacyAuth;
use Tests\Concerns\InteractsWithLegacyInputs;
use Tests\TestCase;

class InputApiTest extends TestCase
{
    use InteractsWithLegacyAuth;
    use InteractsWithLegacyInputs;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLegacyAuthSchema();
        $this->setUpLegacyInputSchema();
        $this->createInputType();
        $this->createUnitMeasure();
    }

    public function test_admin_can_list_inputs_with_legacy_order_and_filters(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createInputType([
            'id_tipo' => 2,
            'descripcion' => 'MANO DE OBRA',
            'estado' => 'AC',
        ]);

        $this->createUnitMeasure([
            'id_unidad_medida' => 2,
            'descripcion' => 'Metro',
            'abreviatura' => 'm',
            'estado' => 'AC',
        ]);

        $this->createInput([
            'id_insumo' => 1,
            'descripcion' => 'Zinc',
            'tipo' => 2,
            'unidad_medida' => 2,
            'estado' => 'AC',
        ]);

        $this->createInput([
            'id_insumo' => 2,
            'descripcion' => 'Acero estructural',
            'tipo' => 1,
            'unidad_medida' => 1,
            'estado' => 'AC',
        ]);

        $this->createInput([
            'id_insumo' => 3,
            'descripcion' => 'Arena fina',
            'tipo' => 1,
            'unidad_medida' => 2,
            'estado' => 'DC',
        ]);

        $response = $this->getJson('/api/v1/inputs?status=AC&per_page=10');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.meta.total', 2)
            ->assertJsonPath('data.items.0.descripcion', 'Acero estructural')
            ->assertJsonPath('data.items.0.tipo', 1)
            ->assertJsonPath('data.items.0.unidad_medida', 1)
            ->assertJsonPath('data.items.0.nombre_tipo', 'MATERIAL')
            ->assertJsonPath('data.items.0.nombre_unidad_medida', 'Pieza')
            ->assertJsonPath('data.items.1.descripcion', 'Zinc')
            ->assertJsonPath('data.items.1.tipo', 2);

        $this->getJson('/api/v1/inputs?search=acero&status=AC&type_id=1&unit_measure_id=1&per_page=10')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.id_insumo', 2);
    }

    public function test_admin_can_get_input_context_and_select_searches(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createUnitMeasure([
            'id_unidad_medida' => 2,
            'descripcion' => 'Metro',
            'abreviatura' => 'm',
            'estado' => 'AC',
        ]);

        $this->createInput([
            'id_insumo' => 2,
            'descripcion' => 'Arena fina',
            'estado' => 'AC',
        ]);

        $this->getJson('/api/v1/inputs/context')
            ->assertOk()
            ->assertJsonPath('data.statuses.0.code', 'AC')
            ->assertJsonPath('data.statuses.1.code', 'DC')
            ->assertJsonPath('data.permissions.can_delete', true);

        $this->getJson('/api/v1/search/inputs?search=arena')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', 1)
            ->assertJsonPath('data.items.0.text', 'Arena fina');

        $this->getJson('/api/v1/search/unit-measures?search=metro')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', 2)
            ->assertJsonPath('data.items.0.text', 'Metro (m)');
    }

    public function test_admin_can_create_show_update_and_get_simple_name_of_input(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $response = $this->postJson('/api/v1/inputs', [
            'descripcion' => 'Cemento Portland',
            'unidad_medida' => 1,
            'precio' => 65.50,
            'tipo' => 1,
            'estado' => 'AC',
            'cod' => 'INS-100',
            'fecha_cotiz' => '2026-04-29',
            'observacion' => 'Cotizacion base',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.input.descripcion', 'Cemento Portland')
            ->assertJsonPath('data.input.observacion', 'Cotizacion base')
            ->assertJsonPath('data.input.tipo', 1)
            ->assertJsonPath('data.input.unidad_medida', 1);

        $inputId = $response->json('data.input.id_insumo');

        $this->assertDatabaseHas('log_insumo', [
            'id_insumo' => $inputId,
            'accion' => 'RG',
        ]);

        $this->assertDatabaseHas('historial_insumo', [
            'id_insumo' => $inputId,
            'accion' => 'REGISTRADOR',
        ]);

        $this->getJson('/api/v1/inputs/'.$inputId)
            ->assertOk()
            ->assertJsonPath('data.input.nombre_tipo', 'MATERIAL')
            ->assertJsonPath('data.input.nombre_unidad_medida', 'Pieza')
            ->assertJsonPath('data.input.abreviatura', 'pza.');

        $this->getJson('/api/v1/inputs/'.$inputId.'/name')
            ->assertOk()
            ->assertJsonPath('data.id_insumo', $inputId)
            ->assertJsonPath('data.descripcion', 'Cemento Portland');

        $this->putJson('/api/v1/inputs/'.$inputId, [
            'descripcion' => 'Cemento Portland IP-30',
            'unidad_medida' => 1,
            'precio' => 70.00,
            'tipo' => 1,
            'estado' => 'DC',
            'cod' => 'INS-101',
            'fecha_cotiz' => '2026-05-01',
            'observacion' => 'Actualizado',
        ])->assertOk()
            ->assertJsonPath('data.input.descripcion', 'Cemento Portland IP-30')
            ->assertJsonPath('data.input.estado', 'DC');

        $this->assertDatabaseHas('historial_insumo', [
            'id_insumo' => $inputId,
            'accion' => 'MODIFICADO',
        ]);
    }

    public function test_cannot_create_or_update_duplicate_input_description_when_other_input_is_not_deleted(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createInput([
            'id_insumo' => 1,
            'descripcion' => 'Acero estructural',
            'estado' => 'AC',
        ]);

        $this->postJson('/api/v1/inputs', [
            'descripcion' => 'acero estructural',
            'unidad_medida' => 1,
            'precio' => 65.50,
            'tipo' => 1,
            'fecha_cotiz' => '2026-04-29',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.descripcion.0', 'Ya existe otro insumo con la misma descripcion.');

        $this->createInput([
            'id_insumo' => 2,
            'descripcion' => 'Arena fina',
            'estado' => 'AC',
        ]);

        $this->putJson('/api/v1/inputs/2', [
            'descripcion' => 'ACERO ESTRUCTURAL',
            'unidad_medida' => 1,
            'precio' => 10,
            'tipo' => 1,
            'estado' => 'AC',
            'fecha_cotiz' => '2026-05-01',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.descripcion.0', 'Ya existe otro insumo con la misma descripcion.');
    }

    public function test_admin_can_view_history_logs_and_quote_endpoints_of_input(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createInput();
        $this->createInputLog();
        $this->createInputHistory();
        $this->createInputQuote([
            'fecha' => '2026-04-01',
        ]);
        $this->createInputQuote([
            'id_cotizacion' => 2,
            'condicion' => 'VG',
            'fecha' => '2026-05-01',
            'id_log_insumo' => 1,
        ]);

        $this->getJson('/api/v1/inputs/1/history')
            ->assertOk()
            ->assertJsonPath('data.items.0.accion', 'MODIFICADO')
            ->assertJsonPath('data.items.0.nombre_tipo', 'MATERIAL');

        $this->getJson('/api/v1/inputs/1/logs')
            ->assertOk()
            ->assertJsonPath('data.items.0.accion', 'RG')
            ->assertJsonPath('data.items.0.nombre_unidad_medida', 'Pieza');

        $this->getJson('/api/v1/inputs/1/quotes/current')
            ->assertOk()
            ->assertJsonPath('data.quote.id_cotizacion', 2)
            ->assertJsonPath('data.quote.input_description', 'Acero estructural');

        $this->getJson('/api/v1/inputs/1/quotes/history')
            ->assertOk()
            ->assertJsonPath('data.items.0.id_cotizacion', 2);

        $this->getJson('/api/v1/inputs/1/quotes/log-history')
            ->assertOk()
            ->assertJsonPath('data.items.0.id_log_insumo', 1);

        $this->getJson('/api/v1/input-logs/1/files')
            ->assertOk()
            ->assertJsonCount(2, 'data.items');
    }

    public function test_admin_can_register_quote_for_input_with_uploaded_files(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createInput();
        $this->createInputLog();

        $response = $this->postJson('/api/v1/inputs/1/quotes', [
            'condition' => 'CREDITO',
            'status' => 'AC',
            'log_id' => 1,
            'date' => '2026-04-29',
            'request_id' => 8,
            'valido' => UploadedFile::fake()->create('quote.pdf', 100, 'application/pdf'),
            'propuesto_1' => UploadedFile::fake()->create('quote-a.pdf', 100, 'application/pdf'),
            'propuesto_2' => UploadedFile::fake()->create('quote-b.pdf', 100, 'application/pdf'),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.quote.id_insumo', 1)
            ->assertJsonPath('data.quote.condicion', 'CREDITO')
            ->assertJsonPath('data.quote.id_log_insumo', 1)
            ->assertJsonPath('data.quote.id_solicitud', 8);

        $this->assertDatabaseHas('cotizaciones', [
            'id_insumo' => 1,
            'condicion' => 'CREDITO',
            'id_log_insumo' => 1,
            'id_solicitud' => 8,
        ]);
    }

    public function test_admin_can_request_check_and_execute_logical_delete_with_authorization(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createInput();

        $this->postJson('/api/v1/inputs/1/delete-authorization-request', [
            'nro_autorizacion' => 'AUTH-001',
        ])->assertCreated()
            ->assertJsonPath('data.authorization.estado', 'PE');

        $this->getJson('/api/v1/inputs/1/delete-authorization-status')
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.usable', false);

        $this->createAuthorization([
            'id_autorizacion' => 2,
            'id_elemento' => 1,
            'estado' => 'AP',
            'nro_autorizacion' => 'AUTH-OK',
        ]);

        $this->getJson('/api/v1/inputs/1/delete-authorization-status')
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.usable', true);

        $this->deleteJson('/api/v1/inputs/1', [
            'autorizacion' => 'AUTH-OK',
        ])->assertOk()
            ->assertJsonPath('data.input.estado', 'DP');

        $this->assertDatabaseHas('insumo', [
            'id_insumo' => 1,
            'estado' => 'DP',
        ]);

        $this->assertDatabaseHas('log_insumo', [
            'id_insumo' => 1,
            'estado' => 'DP',
        ]);
    }

    public function test_cannot_delete_input_when_used_by_active_items_or_without_approved_authorization(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createInput();
        $this->createItemInput();

        $this->deleteJson('/api/v1/inputs/1', [
            'autorizacion' => 'AUTH-001',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.input.0', 'El insumo no puede eliminarse porque esta asociado a items activos.');

        $this->assertDatabaseMissing('insumo', [
            'id_insumo' => 1,
            'estado' => 'DP',
        ]);
    }

    public function test_input_endpoints_require_authentication(): void
    {
        $this->createInput();

        $this->getJson('/api/v1/inputs')->assertUnauthorized();
        $this->getJson('/api/v1/inputs/context')->assertUnauthorized();
        $this->postJson('/api/v1/inputs', [])->assertUnauthorized();
        $this->getJson('/api/v1/inputs/1')->assertUnauthorized();
        $this->getJson('/api/v1/inputs/1/name')->assertUnauthorized();
        $this->putJson('/api/v1/inputs/1', [])->assertUnauthorized();
        $this->deleteJson('/api/v1/inputs/1', [])->assertUnauthorized();
        $this->postJson('/api/v1/inputs/1/delete-authorization-request', [])->assertUnauthorized();
        $this->getJson('/api/v1/inputs/1/delete-authorization-status')->assertUnauthorized();
        $this->patchJson('/api/v1/inputs/1/status', [])->assertUnauthorized();
        $this->getJson('/api/v1/inputs/1/history')->assertUnauthorized();
        $this->getJson('/api/v1/inputs/1/logs')->assertUnauthorized();
        $this->getJson('/api/v1/inputs/1/quotes')->assertUnauthorized();
        $this->postJson('/api/v1/inputs/1/quotes', [])->assertUnauthorized();
        $this->getJson('/api/v1/inputs/1/quotes/current')->assertUnauthorized();
        $this->getJson('/api/v1/inputs/1/quotes/history')->assertUnauthorized();
        $this->getJson('/api/v1/inputs/1/quotes/log-history')->assertUnauthorized();
        $this->getJson('/api/v1/input-logs/1/files')->assertUnauthorized();
        $this->getJson('/api/v1/search/inputs')->assertUnauthorized();
        $this->getJson('/api/v1/search/unit-measures')->assertUnauthorized();
    }

    public function test_non_admin_cannot_manage_inputs(): void
    {
        $this->createLegacyAuthUser();
        $this->createInput();

        $userRole = Role::query()->create([
            'id_rol' => 2,
            'nombre_rol' => 'Tecnico',
            'estado' => 'AC',
        ]);

        $unit = Unit::query()->firstOrCreate([
            'id_unidad' => 2,
        ], [
            'descripcion' => 'Unidad Tecnica',
            'estado' => 'AC',
        ]);

        $user = User::query()->create([
            'id_usuario' => 2,
            'funcionario' => 'Usuario Tecnico',
            'ci' => '87654321',
            'username' => 'tecnico',
            'clave' => Hash::make('secret123'),
            'estado' => 'AC',
            'id_unidad' => $unit->id_unidad,
            'rol' => $userRole->id_rol,
            'fecha' => now()->toDateString(),
            'subalcaldia' => null,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/inputs')->assertForbidden();
        $this->getJson('/api/v1/inputs/context')->assertForbidden();
        $this->postJson('/api/v1/inputs', [
            'descripcion' => 'Cemento',
            'unidad_medida' => 1,
            'precio' => 10,
            'tipo' => 1,
            'fecha_cotiz' => '2026-05-01',
        ])->assertForbidden();
    }
}

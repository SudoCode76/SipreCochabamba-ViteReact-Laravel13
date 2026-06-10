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
use Tests\Concerns\InteractsWithLegacyInputRequests;
use Tests\Concerns\InteractsWithLegacyInputs;
use Tests\TestCase;

class InputRequestApiTest extends TestCase
{
    use InteractsWithLegacyAuth;
    use InteractsWithLegacyInputRequests;
    use InteractsWithLegacyInputs;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLegacyAuthSchema();
        $this->setUpLegacyInputSchema();
        $this->setUpLegacyInputRequestSchema();
        $this->createInputType();
        $this->createUnitMeasure();
    }

    public function test_admin_can_list_input_requests_with_enriched_fields_and_actions(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createInputRequestRecord([
            'id_solicitud' => 1,
            'descripcion' => 'B SOLICITUD',
            'estado_aprobacion' => 'AP',
        ]);

        $this->createInputRequestRecord([
            'id_solicitud' => 2,
            'descripcion' => 'A SOLICITUD',
            'estado_aprobacion' => 'PD',
        ]);

        $this->getJson('/api/v1/input-requests?per_page=10')
            ->assertOk()
            ->assertJsonPath('data.items.0.id_solicitud', 2)
            ->assertJsonPath('data.items.0.nombre_tipo', 'MATERIAL')
            ->assertJsonPath('data.items.0.nombre_unidad_medida', 'Pieza')
            ->assertJsonPath('data.items.0.nombre_completo', 'Usuario Demo')
            ->assertJsonPath('data.items.0.approval_status_label', 'PENDIENTE')
            ->assertJsonPath('data.items.0.available_actions.edit', true)
            ->assertJsonPath('data.items.0.available_actions.gestionar', true)
            ->assertJsonPath('data.items.0.available_actions.view_quotes', true)
            ->assertJsonPath('data.items.1.approval_status_label', 'APROBADO')
            ->assertJsonPath('data.items.1.available_actions.edit', false)
            ->assertJsonPath('data.items.1.available_actions.revertir', true);
    }

    public function test_admin_can_list_management_requests_with_legacy_order_and_actions(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createInputRequestRecord([
            'id_solicitud' => 1,
            'descripcion' => 'SOLICITUD APROBADA',
            'estado_aprobacion' => 'AP',
        ]);

        $this->createInputRequestRecord([
            'id_solicitud' => 2,
            'descripcion' => 'SOLICITUD PENDIENTE',
            'estado_aprobacion' => 'PD',
        ]);

        $this->createInputRequestRecord([
            'id_solicitud' => 3,
            'descripcion' => 'SOLICITUD RECHAZADA',
            'estado_aprobacion' => 'RC',
        ]);

        $this->getJson('/api/v1/solicitudes-insumo/gestion?per_page=10')
            ->assertOk()
            ->assertJsonPath('data.items.0.id_solicitud', 1)
            ->assertJsonPath('data.items.0.approval_status', 'AP')
            ->assertJsonPath('data.items.0.available_actions.revertir', true)
            ->assertJsonPath('data.items.1.id_solicitud', 2)
            ->assertJsonPath('data.items.1.approval_status', 'PD')
            ->assertJsonPath('data.items.1.available_actions.gestionar', true)
            ->assertJsonPath('data.items.2.id_solicitud', 3)
            ->assertJsonPath('data.items.2.approval_status', 'RC');
    }

    public function test_admin_can_get_context_and_search_input_types(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->getJson('/api/v1/input-requests/context')
            ->assertOk()
            ->assertJsonPath('data.approval_statuses.0.code', 'PD')
            ->assertJsonPath('data.approval_statuses.1.code', 'AP')
            ->assertJsonPath('data.approval_statuses.2.code', 'RC')
            ->assertJsonPath('data.permissions.can_create', true);

        $this->getJson('/api/v1/search/input-types?search=mat')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', 1)
            ->assertJsonPath('data.items.0.text', 'MATERIAL');
    }

    public function test_admin_can_create_show_and_update_input_request_with_files(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->createLegacyAuthUser());

        $response = $this->postJson('/api/v1/input-requests', [
            'solicitud' => [
                'descripcion' => 'Solicitud de cemento',
                'precio' => 45.20,
                'unidad_medida' => 1,
                'tipo' => 1,
                'ubicacion' => 'Almacen norte',
                'latitud' => '8059108.12345',
                'longitud' => '788396.12345',
                'distrito' => 'D1',
                'zona' => 'Zona Norte',
                'otb' => 'OTB Demo',
                'justificacion' => 'Reposicion inmediata',
                'usuario_solicitante' => 1,
                'estado_aprobacion' => 'PD',
            ],
            'valido' => UploadedFile::fake()->create('valido.pdf', 100, 'application/pdf'),
            'propuesto_1' => UploadedFile::fake()->create('prop1.pdf', 100, 'application/pdf'),
            'propuesto_2' => UploadedFile::fake()->create('prop2.pdf', 100, 'application/pdf'),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.request.descripcion', 'SOLICITUD DE CEMENTO')
            ->assertJsonPath('data.request.approval_status_label', 'PENDIENTE');

        $requestId = $response->json('data.request.id_solicitud');

        $this->assertDatabaseHas('solicitud_insumo', [
            'id_solicitud' => $requestId,
            'estado_aprobacion' => 'PD',
            'latitud' => '8059108.12345',
            'longitud' => '788396.12345',
            'distrito' => 'D1',
            'zona' => 'ZONA NORTE',
            'otb' => 'OTB DEMO',
        ]);

        $this->assertDatabaseHas('cotizaciones', [
            'id_solicitud' => $requestId,
            'condicion' => 'VALIDO',
            'archivo' => 'archivos/cotizaciones/cotizacion_valida_valido.pdf',
            'archivo1' => 'archivos/cotizaciones/cotizacion_propuesto1_prop1.pdf',
            'archivo2' => 'archivos/cotizaciones/cotizacion_propuesto2_prop2.pdf',
        ]);

        $this->getJson('/api/v1/input-requests/'.$requestId)
            ->assertOk()
            ->assertJsonPath('data.request.nombre_tipo', 'MATERIAL')
            ->assertJsonPath('data.request.latitud', '8059108.12345')
            ->assertJsonPath('data.request.nombre_completo', 'Usuario Demo');

        $this->putJson('/api/v1/input-requests/'.$requestId, [
            'descripcion' => 'Solicitud de cemento editada',
            'precio' => 50.00,
            'unidad_medida' => 1,
            'tipo' => 1,
            'ubicacion' => 'Almacen sur',
            'justificacion' => 'Reposicion actualizada',
            'usuario_solicitante' => 1,
            'estado_aprobacion' => 'AP',
            'adj' => 'NO',
        ])->assertOk()
            ->assertJsonPath('data.request.descripcion', 'SOLICITUD DE CEMENTO EDITADA')
            ->assertJsonPath('data.request.approval_status_label', 'APROBADO')
            ->assertJsonPath('data.request.available_actions.edit', false);
    }

    public function test_input_request_requires_valid_quote_file_on_create(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->postJson('/api/v1/input-requests', [
            'descripcion' => 'Solicitud sin cotizacion',
            'precio' => 45.20,
            'unidad_medida' => 1,
            'tipo' => 1,
            'ubicacion' => 'Almacen norte',
            'justificacion' => 'Reposicion inmediata',
            'usuario_solicitante' => 1,
            'estado_aprobacion' => 'PD',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('valido');
    }

    public function test_admin_can_get_quote_history_and_quote_summary(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->createLegacyAuthUser());

        Storage::disk('public')->put('archivos/cotizaciones/valido/solicitud.pdf', 'pdf');
        Storage::disk('public')->put('archivos/cotizaciones/propuesto_1/alternativa-1.pdf', 'pdf');
        Storage::disk('public')->put('archivos/cotizaciones/propuesto_2/alternativa-2.pdf', 'pdf');

        $this->createInputRequestRecord();
        $this->createInputRequestQuote([
            'id_cotizacion' => 1,
            'fecha' => '2026-05-01',
            'condicion' => 'VALIDO',
        ]);
        $this->createInputRequestQuote([
            'id_cotizacion' => 2,
            'fecha' => '2026-06-01',
            'condicion' => 'VALIDO',
        ]);

        $this->getJson('/api/v1/input-requests/1/quotes/history')
            ->assertOk()
            ->assertJsonPath('data.items.0.id_cotizacion', 2)
            ->assertJsonPath('data.items.0.archivo_url', '/storage/archivos/cotizaciones/valido/solicitud.pdf')
            ->assertJsonPath('data.items.0.archivo_available', true)
            ->assertJsonPath('data.items.0.archivo_label', 'Propuesta oficial')
            ->assertJsonPath('data.items.1.id_cotizacion', 1);

        $this->getJson('/api/v1/input-requests/1/quote-summary')
            ->assertOk()
            ->assertJsonPath('data.id_solicitud', 1)
            ->assertJsonPath('data.descripcion', 'SOLICITUD DE ACERO')
            ->assertJsonPath('data.archivo', 'archivos/cotizaciones/valido/solicitud.pdf');
    }

    public function test_admin_can_approve_pending_request_and_create_input_and_log(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $request = $this->createInputRequestRecord([
            'id_solicitud' => 1,
            'descripcion' => 'NUEVO INSUMO',
            'estado_aprobacion' => 'PD',
        ]);

        $this->postJson("/api/v1/solicitudes-insumo/{$request->id_solicitud}/gestion", [
            'estado_aprobacion' => 'AP',
            'precio' => 123.45,
            'unidad_medida' => 1,
            'ubicacion' => 'CHIMBA',
            'justificacion' => 'PARA REVISION',
            'notificacion' => 'COTIZACION REALIZADA',
            'usuario_aprobacion' => 1,
            'fecha_aprobacion' => '2026-05-08',
        ])->assertOk()
            ->assertJsonPath('message', 'Solicitud aprobada correctamente.')
            ->assertJsonPath('data.request.approval_status', 'AP');

        $this->assertDatabaseHas('solicitud_insumo', [
            'id_solicitud' => 1,
            'estado_aprobacion' => 'AP',
            'usuario_aprobacion' => 1,
        ]);

        $this->assertDatabaseHas('insumo', [
            'descripcion' => 'NUEVO INSUMO',
            'precio' => 123.45,
            'unidad_medida' => 1,
            'estado' => 'AC',
            'usuario' => 1,
            'solicitud' => 1,
        ]);

        $this->assertDatabaseHas('log_insumo', [
            'descripcion' => 'NUEVO INSUMO',
            'precio' => 123.45,
            'unidad_medida' => 1,
            'accion' => 'RG',
            'usuario' => 1,
            'estado' => 'AC',
        ]);
    }

    public function test_admin_can_reject_pending_request_without_creating_input(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $request = $this->createInputRequestRecord([
            'id_solicitud' => 1,
            'descripcion' => 'NUEVO INSUMO',
            'estado_aprobacion' => 'PD',
        ]);

        $this->postJson("/api/v1/solicitudes-insumo/{$request->id_solicitud}/gestion", [
            'estado_aprobacion' => 'RC',
            'precio' => 123.45,
            'unidad_medida' => 1,
            'ubicacion' => 'CHIMBA',
            'justificacion' => 'PARA REVISION',
            'notificacion' => 'NO PROCEDE',
            'usuario_aprobacion' => 1,
            'fecha_aprobacion' => '2026-05-08',
        ])->assertOk()
            ->assertJsonPath('message', 'Solicitud rechazada correctamente.')
            ->assertJsonPath('data.request.approval_status', 'RC');

        $this->assertDatabaseHas('solicitud_insumo', [
            'id_solicitud' => 1,
            'estado_aprobacion' => 'RC',
        ]);

        $this->assertDatabaseMissing('insumo', [
            'solicitud' => 1,
        ]);
    }

    public function test_cannot_approve_pending_request_if_active_input_with_same_description_exists(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $request = $this->createInputRequestRecord([
            'id_solicitud' => 1,
            'descripcion' => 'INSUMO DUPLICADO',
            'estado_aprobacion' => 'PD',
        ]);

        $this->createInput(['descripcion' => 'INSUMO DUPLICADO', 'estado' => 'AC']);

        $this->postJson("/api/v1/solicitudes-insumo/{$request->id_solicitud}/gestion", [
            'estado_aprobacion' => 'AP',
            'precio' => 123.45,
            'unidad_medida' => 1,
            'ubicacion' => 'CHIMBA',
            'justificacion' => 'PARA REVISION',
            'notificacion' => 'COTIZACION REALIZADA',
            'usuario_aprobacion' => 1,
            'fecha_aprobacion' => '2026-05-08',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.descripcion.0', 'Ya existe un insumo activo con la misma descripcion.');
    }

    public function test_admin_can_revert_approved_request_and_deactivate_related_input(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $request = $this->createInputRequestRecord([
            'id_solicitud' => 1,
            'descripcion' => 'INSUMO REVERTIBLE',
            'estado_aprobacion' => 'AP',
            'notificacion' => 'APROBADA',
        ]);

        $this->createInput([
            'id_insumo' => 1,
            'descripcion' => 'INSUMO REVERTIBLE',
            'solicitud' => 1,
            'estado' => 'AC',
        ]);

        $this->postJson("/api/v1/solicitudes-insumo/{$request->id_solicitud}/revertir", [
            'observacion' => 'Motivo de reversión',
            'usuario_rev' => 1,
            'fecha_rev' => '2026-05-08',
        ])->assertOk()
            ->assertJsonPath('data.request.approval_status', 'PD')
            ->assertJsonPath('data.request.notificacion', '');

        $this->assertDatabaseHas('solicitud_insumo', [
            'id_solicitud' => 1,
            'estado_aprobacion' => 'PD',
            'observacion' => 'Motivo de reversión',
        ]);

        $this->assertDatabaseHas('insumo', [
            'id_insumo' => 1,
            'estado' => 'DC',
        ]);

        $this->assertDatabaseHas('log_insumo', [
            'id_insumo' => 1,
            'accion' => 'RV',
            'estado' => 'DC',
        ]);
    }

    public function test_admin_can_revert_rejected_request_without_touching_inputs(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $request = $this->createInputRequestRecord([
            'id_solicitud' => 1,
            'estado_aprobacion' => 'RC',
        ]);

        $this->postJson("/api/v1/solicitudes-insumo/{$request->id_solicitud}/revertir", [
            'observacion' => 'Motivo de reversión',
            'usuario_rev' => 1,
            'fecha_rev' => '2026-05-08',
        ])->assertOk()
            ->assertJsonPath('data.request.approval_status', 'PD');

        $this->assertDatabaseHas('solicitud_insumo', [
            'id_solicitud' => 1,
            'estado_aprobacion' => 'PD',
            'observacion' => 'Motivo de reversión',
        ]);
    }

    public function test_admin_can_search_unit_measures_with_legacy_alias_q(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createUnitMeasure([
            'id_unidad_medida' => 2,
            'descripcion' => 'Metro cubico',
            'abreviatura' => 'M3',
            'estado' => 'AC',
        ]);

        $this->getJson('/api/v1/unidades-medida/search?q=metro')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', 2);
    }

    public function test_input_request_endpoints_require_authentication(): void
    {
        $this->createInputRequestRecord();

        $this->getJson('/api/v1/input-requests')->assertUnauthorized();
        $this->getJson('/api/v1/input-requests/context')->assertUnauthorized();
        $this->postJson('/api/v1/input-requests', [])->assertUnauthorized();
        $this->getJson('/api/v1/input-requests/1')->assertUnauthorized();
        $this->putJson('/api/v1/input-requests/1', [])->assertUnauthorized();
        $this->getJson('/api/v1/input-requests/1/quotes/history')->assertUnauthorized();
        $this->getJson('/api/v1/input-requests/1/quote-summary')->assertUnauthorized();
        $this->getJson('/api/v1/search/input-types')->assertUnauthorized();
        $this->getJson('/api/v1/solicitudes-insumo/gestion')->assertUnauthorized();
        $this->getJson('/api/v1/solicitudes-insumo/1')->assertUnauthorized();
        $this->postJson('/api/v1/solicitudes-insumo/1/gestion', [])->assertUnauthorized();
        $this->postJson('/api/v1/solicitudes-insumo/1/revertir', [])->assertUnauthorized();
        $this->getJson('/api/v1/unidades-medida/search?q=metro')->assertUnauthorized();
    }

    public function test_non_admin_cannot_manage_input_requests(): void
    {
        $this->createLegacyAuthUser();
        $this->createInputRequestRecord();

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

        $this->getJson('/api/v1/input-requests')->assertForbidden();
        $this->getJson('/api/v1/input-requests/context')->assertForbidden();
        $this->postJson('/api/v1/input-requests', [
            'descripcion' => 'Solicitud',
            'precio' => 10,
            'unidad_medida' => 1,
            'tipo' => 1,
            'ubicacion' => 'ALMACEN',
            'justificacion' => 'JUSTIFICACION',
            'usuario_solicitante' => 1,
        ])->assertForbidden();

        $this->getJson('/api/v1/solicitudes-insumo/gestion')->assertForbidden();
        $this->postJson('/api/v1/solicitudes-insumo/1/gestion', [])->assertForbidden();
        $this->postJson('/api/v1/solicitudes-insumo/1/revertir', [])->assertForbidden();
        $this->getJson('/api/v1/unidades-medida/search?q=metro')->assertForbidden();
    }
}

<?php

namespace Tests\Feature\Authorization;

use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithLegacyAuth;
use Tests\Concerns\InteractsWithLegacyInputs;
use Tests\TestCase;

class AuthorizationApiTest extends TestCase
{
    use InteractsWithLegacyAuth;
    use InteractsWithLegacyInputs;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLegacyAuthSchema();
        $this->setUpLegacyInputSchema();
    }

    public function test_administrator_can_get_authorizations_context_list_and_show_authorizations(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $requester = User::query()->create([
            'id_usuario' => 2,
            'funcionario' => 'Erika Ayala Gonzales',
            'ci' => '98765432',
            'username' => 'erika',
            'clave' => Hash::make('secret123'),
            'estado' => 'AC',
            'id_unidad' => 1,
            'rol' => 1,
            'fecha' => now()->toDateString(),
            'subalcaldia' => null,
        ]);

        $this->createAuthorization([
            'id_autorizacion' => 1,
            'elemento' => 'Cable flexible',
            'tipo_elemento' => 'insumo',
            'tabla' => 'insumo',
            'solicitante' => $requester->id_usuario,
            'estado' => 'PE',
            'nro_autorizacion' => null,
            'fecha' => '2025-01-28 17:29:00',
        ]);

        $authorization = $this->createAuthorization([
            'id_autorizacion' => 2,
            'elemento' => 'Fierro liso 1/4"',
            'tipo_elemento' => 'insumo',
            'tabla' => 'insumo',
            'solicitante' => $requester->id_usuario,
            'estado' => 'AP',
            'nro_autorizacion' => '1034437',
            'fecha' => '2024-11-15 15:51:17',
        ]);

        $this->getJson('/api/v1/authorizations/context')
            ->assertOk()
            ->assertJsonPath('data.statuses.0.code', 'PE')
            ->assertJsonPath('data.processable_statuses.1.code', 'NP')
            ->assertJsonFragment(['value' => 'insumo'])
            ->assertJsonPath('data.permissions.can_process', true);

        $this->getJson('/api/v1/authorizations?search=Fierro&status=AUTORIZADO&module=insumo&per_page=1')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.id', $authorization->id_autorizacion)
            ->assertJsonPath('data.items.0.element', 'Fierro liso 1/4"')
            ->assertJsonPath('data.items.0.requester', 'Erika Ayala Gonzales')
            ->assertJsonPath('data.items.0.status_label', 'AUTORIZADO')
            ->assertJsonPath('data.items.0.available_actions.process', false);

        $this->getJson("/api/v1/authorizations/{$authorization->id_autorizacion}")
            ->assertOk()
            ->assertJsonPath('data.authorization.id', $authorization->id_autorizacion)
            ->assertJsonPath('data.authorization.authorization_number', '1034437')
            ->assertJsonPath('data.authorization.module', 'insumo');
    }

    public function test_administrator_can_process_authorization_status(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $authorization = $this->createAuthorization([
            'id_autorizacion' => 2,
            'num_sec' => 45,
            'estado' => 'PE',
            'nro_autorizacion' => null,
        ]);

        $response = $this->patchJson("/api/v1/authorizations/{$authorization->id_autorizacion}/status", [
            'status' => 'AUTORIZADO',
        ])->assertOk()
            ->assertJsonPath('data.authorization.status', 'AP')
            ->assertJsonPath('data.authorization.status_label', 'AUTORIZADO');

        $generatedNumber = (string) $response->json('data.authorization.authorization_number');

        $this->assertStringStartsWith('45', $generatedNumber);
        $this->assertGreaterThanOrEqual(4, strlen($generatedNumber));

        $this->assertDatabaseHas('autorizaciones', [
            'id_autorizacion' => $authorization->id_autorizacion,
            'estado' => 'AP',
            'nro_autorizacion' => (int) $generatedNumber,
        ]);
    }

    public function test_processing_as_no_procede_clears_authorization_number(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $authorization = $this->createAuthorization([
            'id_autorizacion' => 2,
            'num_sec' => 45,
            'estado' => 'PE',
            'nro_autorizacion' => 7777,
        ]);

        $this->patchJson("/api/v1/authorizations/{$authorization->id_autorizacion}/status", [
            'status' => 'NO PROCEDE',
        ])->assertOk()
            ->assertJsonPath('data.authorization.status', 'NP')
            ->assertJsonPath('data.authorization.status_label', 'NO PROCEDE')
            ->assertJsonPath('data.authorization.authorization_number', null);

        $this->assertDatabaseHas('autorizaciones', [
            'id_autorizacion' => $authorization->id_autorizacion,
            'estado' => 'NP',
            'nro_autorizacion' => null,
        ]);
    }

    public function test_cannot_reprocess_authorization(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $authorization = $this->createAuthorization([
            'id_autorizacion' => 2,
            'estado' => 'AP',
            'nro_autorizacion' => 'AUTH-OK',
        ]);

        $this->patchJson("/api/v1/authorizations/{$authorization->id_autorizacion}/status", [
            'estado' => 'NP',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.authorization.0', 'La autorizacion ya fue procesada y no admite cambios adicionales.');
    }

    public function test_authorization_endpoints_require_authentication(): void
    {
        $authorization = $this->createAuthorization();

        $this->getJson('/api/v1/authorizations/context')->assertUnauthorized();
        $this->getJson('/api/v1/authorizations')->assertUnauthorized();
        $this->getJson("/api/v1/authorizations/{$authorization->id_autorizacion}")->assertUnauthorized();
        $this->patchJson("/api/v1/authorizations/{$authorization->id_autorizacion}/status", [
            'estado' => 'AP',
        ])->assertUnauthorized();
    }

    public function test_non_administrator_cannot_access_authorization_endpoints(): void
    {
        $this->createLegacyAuthUser();

        $role = Role::query()->create([
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
            'rol' => $role->id_rol,
            'fecha' => now()->toDateString(),
            'subalcaldia' => null,
        ]);

        $authorization = $this->createAuthorization([
            'id_autorizacion' => 2,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/authorizations/context')->assertForbidden();
        $this->getJson('/api/v1/authorizations')->assertForbidden();
        $this->getJson("/api/v1/authorizations/{$authorization->id_autorizacion}")->assertForbidden();
        $this->patchJson("/api/v1/authorizations/{$authorization->id_autorizacion}/status", [
            'estado' => 'AP',
        ])->assertForbidden();
    }
}

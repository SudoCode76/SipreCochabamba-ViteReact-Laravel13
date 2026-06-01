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
use Tests\Concerns\InteractsWithLegacyItems;
use Tests\Concerns\InteractsWithLegacyProjects;
use Tests\TestCase;

class AuthorizationApiTest extends TestCase
{
    use InteractsWithLegacyAuth;
    use InteractsWithLegacyInputs;
    use InteractsWithLegacyItems;
    use InteractsWithLegacyProjects;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLegacyAuthSchema();
        $this->setUpLegacyInputSchema();
        $this->setUpLegacyItemSchema();
        $this->setUpLegacyProjectSchema();
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

    public function test_administrator_can_view_authorization_impact_for_input_deletion(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createInput(['id_insumo' => 1, 'descripcion' => 'ARENA FINA']);
        $this->createGroup();
        $this->createSubgroup();
        $this->createItemRecord(['id_item' => 1, 'item' => 'ITEM CON ARENA']);
        $this->createItemInput(['id_item_insumo' => 1, 'id_insumo' => 1, 'id_item' => 1, 'estado' => 'AC']);
        $this->createProjectRecord(['id_proyecto' => 1, 'nombre_proyecto' => 'PROYECTO PENDIENTE', 'aprobado' => 'PD']);
        $this->createProjectRecord(['id_proyecto' => 2, 'nombre_proyecto' => 'PROYECTO APROBADO', 'aprobado' => 'AP']);
        $this->createProjectItemRecord(['id_proyecto_item' => 1, 'id_proyecto' => 1, 'id_item' => 1, 'estado' => 'AC']);
        $this->createProjectItemRecord(['id_proyecto_item' => 2, 'id_proyecto' => 2, 'id_item' => 1, 'estado' => 'AC']);

        $authorization = $this->createAuthorization([
            'id_autorizacion' => 3,
            'id_elemento' => 1,
            'elemento' => 'ARENA FINA',
            'tipo_elemento' => 'insumo',
            'tabla' => 'insumo',
            'estado' => 'PE',
        ]);

        $this->getJson("/api/v1/authorizations/{$authorization->id_autorizacion}/impact")
            ->assertOk()
            ->assertJsonPath('data.type', 'insumo')
            ->assertJsonPath('data.summary.items_count', 1)
            ->assertJsonPath('data.summary.pending_projects_count', 1)
            ->assertJsonPath('data.items.0.id_item', 1)
            ->assertJsonPath('data.pending_projects.0.id_proyecto', 1);
    }

    public function test_administrator_can_process_authorization_status(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $authorization = $this->createAuthorization([
            'id_autorizacion' => 2,
            'num_sec' => 45,
            'id_elemento' => 99,
            'elemento' => 'Grupo demo',
            'tipo_elemento' => 'grupo',
            'tabla' => 'grupo',
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

    public function test_approving_input_authorization_deletes_input_without_removing_item_compositions(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createInput(['id_insumo' => 1, 'descripcion' => 'ARENA FINA', 'estado' => 'AC']);
        $this->createInput(['id_insumo' => 2, 'descripcion' => 'CEMENTO PORTLAND', 'estado' => 'AC']);
        $this->createGroup();
        $this->createSubgroup();
        $this->createItemRecord(['id_item' => 1, 'item' => 'ITEM CON ARENA']);
        $this->createItemRecord(['id_item' => 2, 'item' => 'ITEM INACTIVO CON ARENA']);

        $this->createItemInput(['id_item_insumo' => 1, 'id_insumo' => 1, 'id_item' => 1, 'estado' => 'AC']);
        $this->createItemInput(['id_item_insumo' => 2, 'id_insumo' => 1, 'id_item' => 2, 'estado' => 'DC']);
        $this->createItemInput(['id_item_insumo' => 3, 'id_insumo' => 2, 'id_item' => 1, 'estado' => 'AC']);

        $authorization = $this->createAuthorization([
            'id_autorizacion' => 4,
            'id_elemento' => 1,
            'elemento' => 'ARENA FINA',
            'tipo_elemento' => 'insumo',
            'tabla' => 'insumo',
            'estado' => 'PE',
            'nro_autorizacion' => null,
        ]);

        $this->patchJson("/api/v1/authorizations/{$authorization->id_autorizacion}/status", [
            'status' => 'AUTORIZADO',
        ])->assertOk()
            ->assertJsonPath('data.authorization.status', 'AP')
            ->assertJsonPath('data.authorization.module', 'insumo');

        $this->assertDatabaseHas('insumo', [
            'id_insumo' => 1,
            'estado' => 'DP',
        ]);
        $this->assertDatabaseHas('insumo', [
            'id_insumo' => 2,
            'estado' => 'AC',
        ]);
        $this->assertDatabaseHas('item_insumo', [
            'id_item_insumo' => 1,
            'id_insumo' => 1,
            'estado' => 'AC',
        ]);
        $this->assertDatabaseHas('item_insumo', [
            'id_item_insumo' => 2,
            'id_insumo' => 1,
            'estado' => 'DC',
        ]);
        $this->assertDatabaseHas('item_insumo', [
            'id_item_insumo' => 3,
            'id_insumo' => 2,
            'estado' => 'AC',
        ]);
        $this->assertDatabaseHas('log_insumo', [
            'id_insumo' => 1,
            'accion' => 'MD',
            'estado' => 'DP',
        ]);
        $this->assertDatabaseHas('historial_insumo', [
            'id_insumo' => 1,
            'accion' => 'ELIMINADO',
            'estado' => 'DP',
        ]);

        $this->getJson("/api/v1/authorizations/{$authorization->id_autorizacion}/impact")
            ->assertOk()
            ->assertJsonPath('data.summary.items_count', 1)
            ->assertJsonPath('data.summary.pending_projects_count', 0);
    }

    public function test_processing_as_no_procede_clears_authorization_number(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createInput(['id_insumo' => 1, 'descripcion' => 'ARENA FINA', 'estado' => 'AC']);
        $this->createItemInput(['id_item_insumo' => 1, 'id_insumo' => 1, 'id_item' => 1, 'estado' => 'AC']);

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
        $this->assertDatabaseHas('insumo', [
            'id_insumo' => 1,
            'estado' => 'AC',
        ]);
        $this->assertDatabaseHas('item_insumo', [
            'id_item_insumo' => 1,
            'estado' => 'AC',
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
        $this->getJson("/api/v1/authorizations/{$authorization->id_autorizacion}/impact")->assertUnauthorized();
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
        $this->getJson("/api/v1/authorizations/{$authorization->id_autorizacion}/impact")->assertForbidden();
        $this->patchJson("/api/v1/authorizations/{$authorization->id_autorizacion}/status", [
            'estado' => 'AP',
        ])->assertForbidden();
    }
}

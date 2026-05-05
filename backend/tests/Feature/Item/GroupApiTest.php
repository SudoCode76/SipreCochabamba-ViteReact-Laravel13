<?php

namespace Tests\Feature\Item;

use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithLegacyAuth;
use Tests\Concerns\InteractsWithLegacyInputs;
use Tests\Concerns\InteractsWithLegacyItems;
use Tests\TestCase;

class GroupApiTest extends TestCase
{
    use InteractsWithLegacyAuth;
    use InteractsWithLegacyInputs;
    use InteractsWithLegacyItems;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLegacyAuthSchema();
        $this->setUpLegacyInputSchema();
        $this->setUpLegacyItemSchema();
    }

    public function test_admin_can_list_context_show_create_update_and_delete_groups(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createGroup(['id_grupo' => 1, 'codigo_grupo' => '001', 'nombre_grupo' => 'Grupo Uno', 'estado' => 'AC']);
        $this->createGroup(['id_grupo' => 2, 'codigo_grupo' => '002', 'nombre_grupo' => 'Grupo Dos', 'estado' => 'DC']);
        $this->createGroup(['id_grupo' => 3, 'codigo_grupo' => '003', 'nombre_grupo' => 'Grupo Eliminado', 'estado' => 'DP']);

        $this->getJson('/api/v1/groups')
            ->assertOk()
            ->assertJsonPath('data.items.0.id_grupo', 1)
            ->assertJsonPath('data.items.0.status_label', 'ACTIVO')
            ->assertJsonPath('data.items.0.available_actions.edit', true)
            ->assertJsonPath('data.items.0.available_actions.delete', true)
            ->assertJsonPath('data.items.1.id_grupo', 2)
            ->assertJsonMissing(['id_grupo' => 3]);

        $this->getJson('/api/v1/groups/context')
            ->assertOk()
            ->assertJsonPath('data.statuses.0.code', 'AC')
            ->assertJsonPath('data.permissions.can_delete', true);

        $this->getJson('/api/v1/groups/2')
            ->assertOk()
            ->assertJsonPath('data.group.id_grupo', 2)
            ->assertJsonPath('data.group.nombre_grupo', 'Grupo Dos');

        $this->postJson('/api/v1/groups', [
            'codigo_grupo' => '004',
            'nombre_grupo' => 'Grupo Cuatro',
            'estado' => 'AC',
        ])->assertCreated()
            ->assertJsonPath('data.group.codigo_grupo', '004')
            ->assertJsonPath('data.group.nombre_grupo', 'Grupo Cuatro');

        $this->putJson('/api/v1/groups/2', [
            'codigo_grupo' => '002-A',
            'nombre_grupo' => 'Grupo Dos Editado',
            'estado' => 'AC',
        ])->assertOk()
            ->assertJsonPath('data.group.codigo_grupo', '002-A')
            ->assertJsonPath('data.group.status_label', 'ACTIVO');

        $this->postJson('/api/v1/groups/2/delete-authorization-request', [
            'nro_autorizacion' => 'AUTH-GR-1',
        ])->assertCreated()
            ->assertJsonPath('data.authorization.estado', 'PE');

        $this->getJson('/api/v1/groups/2/delete-authorization-status')
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');

        $this->createAuthorization([
            'id_autorizacion' => 2,
            'id_elemento' => 2,
            'elemento' => 'Grupo Dos Editado',
            'tipo_elemento' => 'grupo',
            'tabla' => 'grupo',
            'solicitante' => 1,
            'estado' => 'AP',
            'nro_autorizacion' => 'AUTH-OK',
        ]);

        $this->deleteJson('/api/v1/groups/2', [
            'autorizacion' => 'AUTH-OK',
        ])->assertOk()
            ->assertJsonPath('data.group.estado', 'DP');
    }

    public function test_group_code_and_name_must_be_unique_except_deleted(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createGroup(['id_grupo' => 1, 'codigo_grupo' => '001', 'nombre_grupo' => 'Grupo Uno', 'estado' => 'AC']);
        $this->createGroup(['id_grupo' => 2, 'codigo_grupo' => '002', 'nombre_grupo' => 'Grupo Eliminado', 'estado' => 'DP']);

        $this->postJson('/api/v1/groups', [
            'codigo_grupo' => '001',
            'nombre_grupo' => 'Otro Grupo',
            'estado' => 'AC',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.codigo_grupo.0', 'Ya existe un grupo con el mismo codigo.');

        $this->postJson('/api/v1/groups', [
            'codigo_grupo' => '003',
            'nombre_grupo' => 'Grupo Uno',
            'estado' => 'AC',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.nombre_grupo.0', 'Ya existe un grupo con el mismo nombre.');

        $this->postJson('/api/v1/groups', [
            'codigo_grupo' => '002',
            'nombre_grupo' => 'Grupo Eliminado',
            'estado' => 'AC',
        ])->assertCreated();
    }

    public function test_cannot_delete_group_with_active_items(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createUnitMeasure();
        $this->createGroup();
        $this->createSubgroup();
        $this->createItemRecord(['grupo' => 1, 'estado' => 'AC']);

        $this->createAuthorization([
            'id_autorizacion' => 1,
            'id_elemento' => 1,
            'elemento' => 'OBRAS PRELIMINARES',
            'tipo_elemento' => 'grupo',
            'tabla' => 'grupo',
            'solicitante' => 1,
            'estado' => 'AP',
            'nro_autorizacion' => 'AUTH-OK',
        ]);

        $this->deleteJson('/api/v1/groups/1', [
            'autorizacion' => 'AUTH-OK',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.group.0', 'El grupo no puede eliminarse porque tiene items activos asociados.');
    }

    public function test_group_endpoints_require_authentication(): void
    {
        $this->createGroup();

        $this->getJson('/api/v1/groups')->assertUnauthorized();
        $this->getJson('/api/v1/groups/context')->assertUnauthorized();
        $this->postJson('/api/v1/groups', [])->assertUnauthorized();
        $this->getJson('/api/v1/groups/1')->assertUnauthorized();
        $this->putJson('/api/v1/groups/1', [])->assertUnauthorized();
        $this->deleteJson('/api/v1/groups/1', [])->assertUnauthorized();
        $this->postJson('/api/v1/groups/1/delete-authorization-request', [])->assertUnauthorized();
        $this->getJson('/api/v1/groups/1/delete-authorization-status')->assertUnauthorized();
    }

    public function test_non_admin_cannot_manage_groups(): void
    {
        $this->createLegacyAuthUser();
        $this->createGroup();

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

        $this->getJson('/api/v1/groups')->assertForbidden();
        $this->postJson('/api/v1/groups', [
            'codigo_grupo' => '004',
            'nombre_grupo' => 'Grupo Cuatro',
            'estado' => 'AC',
        ])->assertForbidden();
        $this->getJson('/api/v1/groups/1')->assertForbidden();
        $this->putJson('/api/v1/groups/1', [
            'codigo_grupo' => '001-X',
            'nombre_grupo' => 'Editado',
            'estado' => 'AC',
        ])->assertForbidden();
    }
}

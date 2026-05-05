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

class SubgroupApiTest extends TestCase
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

    public function test_admin_can_manage_subgroups_and_load_combo_data(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createGroup(['id_grupo' => 1, 'nombre_grupo' => 'Grupo Uno', 'estado' => 'AC']);
        $this->createGroup(['id_grupo' => 2, 'nombre_grupo' => 'Grupo Dos', 'estado' => 'AC']);
        $this->createSubgroup(['id_subgrupo' => 1, 'id_grupo' => 1, 'descripcion' => 'Subgrupo Uno', 'codigo' => 'SG-1', 'estado' => 'AC']);
        $this->createSubgroup(['id_subgrupo' => 2, 'id_grupo' => 2, 'descripcion' => 'Subgrupo Dos', 'codigo' => 'SG-2', 'estado' => 'DC']);
        $this->createSubgroup(['id_subgrupo' => 3, 'id_grupo' => 2, 'descripcion' => 'Subgrupo Eliminado', 'codigo' => 'SG-3', 'estado' => 'DP']);

        $this->getJson('/api/v1/subgroups')
            ->assertOk()
            ->assertJsonPath('data.items.0.id_subgrupo', 1)
            ->assertJsonPath('data.items.0.nombre_grupo', 'Grupo Uno')
            ->assertJsonPath('data.items.0.status_label', 'ACTIVO')
            ->assertJsonPath('data.items.0.available_actions.edit', true)
            ->assertJsonPath('data.items.0.available_actions.delete', true)
            ->assertJsonPath('data.items.1.id_subgrupo', 2)
            ->assertJsonPath('data.items.1.status_label', 'INACTIVO')
            ->assertJsonMissing(['id_subgrupo' => 3]);

        $this->getJson('/api/v1/subgroups/context')
            ->assertOk()
            ->assertJsonPath('data.groups.0.id_grupo', 2)
            ->assertJsonPath('data.statuses.0.code', 'AC')
            ->assertJsonPath('data.permissions.can_delete', true);

        $this->getJson('/api/v1/subgroups/2')
            ->assertOk()
            ->assertJsonPath('data.subgroup.id_subgrupo', 2)
            ->assertJsonPath('data.subgroup.nombre_grupo', 'Grupo Dos');

        $this->postJson('/api/v1/subgroups', [
            'codigo' => 'SG-4',
            'descripcion' => 'Subgrupo Cuatro',
            'id_grupo' => 1,
            'estado' => 'AC',
        ])->assertCreated()
            ->assertJsonPath('data.subgroup.codigo', 'SG-4')
            ->assertJsonPath('data.subgroup.nombre_grupo', 'Grupo Uno');

        $this->putJson('/api/v1/subgroups/2', [
            'codigo' => 'SG-2A',
            'descripcion' => 'Subgrupo Dos Editado',
            'id_grupo' => 1,
            'estado' => 'AC',
        ])->assertOk()
            ->assertJsonPath('data.subgroup.codigo', 'SG-2A')
            ->assertJsonPath('data.subgroup.id_grupo', 1)
            ->assertJsonPath('data.subgroup.status_label', 'ACTIVO');

        $this->postJson('/api/v1/subgroups/2/delete-authorization-request', [
            'nro_autorizacion' => 'AUTH-SG-1',
        ])->assertCreated()
            ->assertJsonPath('data.authorization.tabla', 'sub_grupo')
            ->assertJsonPath('data.authorization.estado', 'PE');

        $this->getJson('/api/v1/subgroups/2/delete-authorization-status')
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');

        $this->createAuthorization([
            'id_autorizacion' => 2,
            'id_elemento' => 2,
            'elemento' => 'Subgrupo Dos Editado',
            'tipo_elemento' => 'subgrupo',
            'tabla' => 'sub_grupo',
            'solicitante' => 1,
            'estado' => 'AP',
            'nro_autorizacion' => 'AUTH-SG-OK',
        ]);

        $this->deleteJson('/api/v1/subgroups/2', [
            'autorizacion' => 'AUTH-SG-OK',
        ])->assertOk()
            ->assertJsonPath('data.subgroup.estado', 'DP');

        $this->getJson('/api/v1/subgroups/by-group/1')
            ->assertOk()
            ->assertJsonPath('data.items.0.group_id', 1);

        $this->getJson('/api/v1/subgroups?group_id=1')
            ->assertOk()
            ->assertJsonPath('data.items.0.group_id', 1);
    }

    public function test_subgroup_code_and_description_must_be_unique_except_deleted(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createGroup();
        $this->createSubgroup(['id_subgrupo' => 1, 'codigo' => 'SG-1', 'descripcion' => 'Subgrupo Uno', 'estado' => 'AC']);
        $this->createSubgroup(['id_subgrupo' => 2, 'codigo' => 'SG-2', 'descripcion' => 'Subgrupo Eliminado', 'estado' => 'DP']);

        $this->postJson('/api/v1/subgroups', [
            'codigo' => 'SG-1',
            'descripcion' => 'Otro Subgrupo',
            'id_grupo' => 1,
            'estado' => 'AC',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.codigo.0', 'Ya existe un subgrupo con el mismo codigo.');

        $this->postJson('/api/v1/subgroups', [
            'codigo' => 'SG-3',
            'descripcion' => 'Subgrupo Uno',
            'id_grupo' => 1,
            'estado' => 'AC',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.descripcion.0', 'Ya existe un subgrupo con la misma descripcion.');

        $this->postJson('/api/v1/subgroups', [
            'codigo' => 'SG-2',
            'descripcion' => 'Subgrupo Eliminado',
            'id_grupo' => 1,
            'estado' => 'AC',
        ])->assertCreated();
    }

    public function test_subgroup_update_only_checks_duplicates_when_values_change(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createGroup();
        $this->createSubgroup(['id_subgrupo' => 1, 'codigo' => 'SG-1', 'descripcion' => 'Subgrupo Uno', 'estado' => 'AC']);
        $this->createSubgroup(['id_subgrupo' => 2, 'codigo' => 'SG-2', 'descripcion' => 'Subgrupo Dos', 'estado' => 'AC']);

        $this->putJson('/api/v1/subgroups/1', [
            'codigo' => 'SG-1',
            'descripcion' => 'Subgrupo Uno',
            'id_grupo' => 1,
            'estado' => 'DC',
        ])->assertOk()
            ->assertJsonPath('data.subgroup.estado', 'DC');

        $this->putJson('/api/v1/subgroups/1', [
            'codigo' => 'SG-2',
            'descripcion' => 'Subgrupo Uno',
            'id_grupo' => 1,
            'estado' => 'AC',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.codigo.0', 'Ya existe un subgrupo con el mismo codigo.');

        $this->putJson('/api/v1/subgroups/1', [
            'codigo' => 'SG-1',
            'descripcion' => 'Subgrupo Dos',
            'id_grupo' => 1,
            'estado' => 'AC',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.descripcion.0', 'Ya existe un subgrupo con la misma descripcion.');
    }

    public function test_cannot_delete_subgroup_with_active_items(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createUnitMeasure();
        $this->createGroup();
        $this->createSubgroup();
        $this->createItemRecord(['subgrupo' => 1, 'estado' => 'AC']);

        $this->createAuthorization([
            'id_autorizacion' => 1,
            'id_elemento' => 1,
            'elemento' => 'PRELIMINARES',
            'tipo_elemento' => 'subgrupo',
            'tabla' => 'sub_grupo',
            'solicitante' => 1,
            'estado' => 'AP',
            'nro_autorizacion' => 'AUTH-SG-OK',
        ]);

        $this->deleteJson('/api/v1/subgroups/1', [
            'autorizacion' => 'AUTH-SG-OK',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.subgroup.0', 'El subgrupo no puede eliminarse porque tiene items activos asociados.');
    }

    public function test_subgroup_endpoints_require_authentication(): void
    {
        $this->createGroup();
        $this->createSubgroup();

        $this->getJson('/api/v1/subgroups')->assertUnauthorized();
        $this->getJson('/api/v1/subgroups?group_id=1')->assertUnauthorized();
        $this->getJson('/api/v1/subgroups/by-group/1')->assertUnauthorized();
        $this->getJson('/api/v1/subgroups/context')->assertUnauthorized();
        $this->postJson('/api/v1/subgroups', [])->assertUnauthorized();
        $this->getJson('/api/v1/subgroups/1')->assertUnauthorized();
        $this->putJson('/api/v1/subgroups/1', [])->assertUnauthorized();
        $this->deleteJson('/api/v1/subgroups/1', [])->assertUnauthorized();
        $this->postJson('/api/v1/subgroups/1/delete-authorization-request', [])->assertUnauthorized();
        $this->getJson('/api/v1/subgroups/1/delete-authorization-status')->assertUnauthorized();
    }

    public function test_non_admin_cannot_manage_subgroups_but_can_use_combo_endpoint(): void
    {
        $this->createLegacyAuthUser();
        $this->createGroup();
        $this->createSubgroup();

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

        $this->getJson('/api/v1/subgroups')->assertForbidden();
        $this->postJson('/api/v1/subgroups', [
            'codigo' => 'SG-4',
            'descripcion' => 'Subgrupo Cuatro',
            'id_grupo' => 1,
            'estado' => 'AC',
        ])->assertForbidden();
        $this->getJson('/api/v1/subgroups/1')->assertForbidden();
        $this->putJson('/api/v1/subgroups/1', [
            'codigo' => 'SG-1A',
            'descripcion' => 'Editado',
            'id_grupo' => 1,
            'estado' => 'AC',
        ])->assertForbidden();
        $this->deleteJson('/api/v1/subgroups/1', [
            'autorizacion' => 'AUTH',
        ])->assertForbidden();

        $this->getJson('/api/v1/subgroups?group_id=1')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', 1);
    }
}

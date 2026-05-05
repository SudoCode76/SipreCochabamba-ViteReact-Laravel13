<?php

namespace Tests\Feature\Input;

use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithLegacyAuth;
use Tests\Concerns\InteractsWithLegacyInputs;
use Tests\TestCase;

class InputTypeApiTest extends TestCase
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

    public function test_admin_can_list_context_show_create_and_update_input_types(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createInputType(['id_tipo' => 1, 'descripcion' => 'MATERIAL', 'estado' => 'AC']);
        $this->createInputType(['id_tipo' => 2, 'descripcion' => 'MANO DE OBRA', 'estado' => 'DC']);

        $this->getJson('/api/v1/input-types')
            ->assertOk()
            ->assertJsonPath('data.items.0.id_tipo', 2)
            ->assertJsonPath('data.items.0.status_label', 'INACTIVO')
            ->assertJsonPath('data.items.0.available_actions.edit', true)
            ->assertJsonPath('data.items.1.id_tipo', 1)
            ->assertJsonPath('data.items.1.status_label', 'ACTIVO');

        $this->getJson('/api/v1/input-types/context')
            ->assertOk()
            ->assertJsonPath('data.statuses.0.code', 'AC')
            ->assertJsonPath('data.statuses.1.code', 'DC')
            ->assertJsonPath('data.permissions.can_create', true);

        $this->getJson('/api/v1/input-types/2')
            ->assertOk()
            ->assertJsonPath('data.input_type.id_tipo', 2)
            ->assertJsonPath('data.input_type.descripcion', 'MANO DE OBRA');

        $this->postJson('/api/v1/input-types', [
            'descripcion' => '  HERRAMIENTA  ',
            'estado' => 'AC',
        ])->assertCreated()
            ->assertJsonPath('data.input_type.descripcion', 'HERRAMIENTA')
            ->assertJsonPath('data.input_type.estado', 'AC');

        $this->putJson('/api/v1/input-types/2', [
            'descripcion' => 'SERVICIOS',
            'estado' => 'AC',
        ])->assertOk()
            ->assertJsonPath('data.input_type.descripcion', 'SERVICIOS')
            ->assertJsonPath('data.input_type.status_label', 'ACTIVO');
    }

    public function test_input_type_description_must_be_unique(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createInputType(['id_tipo' => 1, 'descripcion' => 'MATERIAL', 'estado' => 'AC']);
        $this->createInputType(['id_tipo' => 2, 'descripcion' => 'MANO DE OBRA', 'estado' => 'AC']);

        $this->postJson('/api/v1/input-types', [
            'descripcion' => 'MATERIAL',
            'estado' => 'AC',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.descripcion.0', 'Ya existe un tipo de insumo con la misma descripcion.');

        $this->putJson('/api/v1/input-types/2', [
            'descripcion' => 'MATERIAL',
            'estado' => 'DC',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.descripcion.0', 'Ya existe un tipo de insumo con la misma descripcion.');
    }

    public function test_input_type_endpoints_require_authentication(): void
    {
        $this->createInputType();

        $this->getJson('/api/v1/input-types')->assertUnauthorized();
        $this->getJson('/api/v1/input-types/context')->assertUnauthorized();
        $this->postJson('/api/v1/input-types', [])->assertUnauthorized();
        $this->getJson('/api/v1/input-types/1')->assertUnauthorized();
        $this->putJson('/api/v1/input-types/1', [])->assertUnauthorized();
    }

    public function test_non_admin_cannot_manage_input_types(): void
    {
        $this->createLegacyAuthUser();
        $this->createInputType();

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

        $this->getJson('/api/v1/input-types')->assertForbidden();
        $this->postJson('/api/v1/input-types', [
            'descripcion' => 'HERRAMIENTA',
            'estado' => 'AC',
        ])->assertForbidden();
        $this->getJson('/api/v1/input-types/1')->assertForbidden();
        $this->putJson('/api/v1/input-types/1', [
            'descripcion' => 'EDITADO',
            'estado' => 'AC',
        ])->assertForbidden();
    }
}

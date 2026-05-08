<?php

namespace Tests\Feature\User;

use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithLegacyAuth;
use Tests\TestCase;

class UnitApiTest extends TestCase
{
    use InteractsWithLegacyAuth;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLegacyAuthSchema();
    }

    public function test_admin_can_list_context_and_show_units(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        Unit::query()->create([
            'id_unidad' => 2,
            'descripcion' => 'Unidad Tecnica',
            'estado' => 'AC',
        ]);

        Unit::query()->create([
            'id_unidad' => 3,
            'descripcion' => 'Unidad Inactiva',
            'estado' => 'DC',
        ]);

        $this->getJson('/api/v1/units')
            ->assertOk()
            ->assertJsonPath('data.items.0.descripcion', 'Unidad Central')
            ->assertJsonPath('data.items.0.available_actions.select', true)
            ->assertJsonPath('data.items.1.descripcion', 'Unidad Inactiva')
            ->assertJsonPath('data.items.1.available_actions.select', false)
            ->assertJsonPath('data.items.2.descripcion', 'Unidad Tecnica');

        $this->getJson('/api/v1/units/context')
            ->assertOk()
            ->assertJsonPath('data.units.0.descripcion', 'Unidad Central')
            ->assertJsonPath('data.units.1.descripcion', 'Unidad Tecnica')
            ->assertJsonPath('data.permissions.can_select', true);

        $this->getJson('/api/v1/units/2')
            ->assertOk()
            ->assertJsonPath('data.unit.id_unidad', 2)
            ->assertJsonPath('data.unit.descripcion', 'Unidad Tecnica');

        $this->getJson('/api/v1/units?search=tecn')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.descripcion', 'Unidad Tecnica');
    }

    public function test_unit_endpoints_require_authentication(): void
    {
        $this->createLegacyAuthUser();

        $this->getJson('/api/v1/units')->assertUnauthorized();
        $this->getJson('/api/v1/units/context')->assertUnauthorized();
        $this->getJson('/api/v1/units/1')->assertUnauthorized();
    }

    public function test_non_admin_cannot_access_unit_endpoints(): void
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

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/units')->assertForbidden();
        $this->getJson('/api/v1/units/context')->assertForbidden();
        $this->getJson('/api/v1/units/1')->assertForbidden();
    }
}

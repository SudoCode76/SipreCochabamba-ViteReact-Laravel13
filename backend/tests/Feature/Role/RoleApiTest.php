<?php

namespace Tests\Feature\Role;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithLegacyAuth;
use Tests\TestCase;

class RoleApiTest extends TestCase
{
    use InteractsWithLegacyAuth;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLegacyAuthSchema();
    }

    public function test_administrator_can_create_role(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $response = $this->postJson('/api/v1/roles', [
            'nombre_rol' => 'Supervisor',
            'estado' => 'AC',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.role.name', 'Supervisor')
            ->assertJsonPath('data.role.status', 'AC');

        $this->assertDatabaseHas('rol', [
            'nombre_rol' => 'Supervisor',
            'estado' => 'AC',
        ]);
    }

    public function test_administrator_can_list_and_show_roles(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $role = Role::query()->create([
            'id_rol' => 2,
            'nombre_rol' => 'Tecnico',
            'estado' => 'AC',
        ]);

        $this->getJson('/api/v1/roles')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.items.0.id', 1)
            ->assertJsonPath('data.items.1.id', $role->id_rol)
            ->assertJsonPath('data.items.1.name', 'Tecnico')
            ->assertJsonPath('data.items.1.status', 'AC');

        $this->getJson("/api/v1/roles/{$role->id_rol}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.role.id', $role->id_rol)
            ->assertJsonPath('data.role.name', 'Tecnico')
            ->assertJsonPath('data.role.status', 'AC');
    }

    public function test_administrator_can_update_role_status(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $role = Role::query()->create([
            'id_rol' => 2,
            'nombre_rol' => 'Tecnico',
            'estado' => 'AC',
        ]);

        $response = $this->patchJson("/api/v1/roles/{$role->id_rol}/status", [
            'estado' => 'DC',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.role.id', $role->id_rol)
            ->assertJsonPath('data.role.status', 'DC');

        $this->assertDatabaseHas('rol', [
            'id_rol' => $role->id_rol,
            'estado' => 'DC',
        ]);
    }

    public function test_administrator_can_update_role_and_propagate_name_to_permissions(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $role = Role::query()->create([
            'id_rol' => 2,
            'nombre_rol' => 'Tecnico',
            'estado' => 'AC',
        ]);

        Permission::query()->create([
            'id_permiso' => 2,
            'id_rol' => $role->id_rol,
            'nombre_rol' => $role->nombre_rol,
            'id_funcion' => 1,
            'descripcion' => 'Puede autenticarse',
            'estado' => 'AC',
        ]);

        $response = $this->putJson("/api/v1/roles/{$role->id_rol}", [
            'nombre_rol' => 'Supervisor Tecnico',
            'estado' => 'DC',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.role.name', 'Supervisor Tecnico')
            ->assertJsonPath('data.role.status', 'DC')
            ->assertJsonPath('data.previous_name', 'Tecnico');

        $this->assertDatabaseHas('rol', [
            'id_rol' => $role->id_rol,
            'nombre_rol' => 'Supervisor Tecnico',
            'estado' => 'DC',
        ]);

        $this->assertDatabaseHas('permiso', [
            'id_rol' => $role->id_rol,
            'nombre_rol' => 'Supervisor Tecnico',
        ]);
    }

    public function test_role_create_and_update_require_authentication(): void
    {
        $role = Role::query()->create([
            'id_rol' => 2,
            'nombre_rol' => 'Tecnico',
            'estado' => 'AC',
        ]);

        $this->postJson('/api/v1/roles', [
            'nombre_rol' => 'Supervisor',
            'estado' => 'AC',
        ])->assertUnauthorized();

        $this->putJson("/api/v1/roles/{$role->id_rol}", [
            'nombre_rol' => 'Supervisor Tecnico',
            'estado' => 'DC',
        ])->assertUnauthorized();

        $this->getJson('/api/v1/roles')->assertUnauthorized();

        $this->getJson("/api/v1/roles/{$role->id_rol}")->assertUnauthorized();

        $this->patchJson("/api/v1/roles/{$role->id_rol}/status", [
            'estado' => 'DC',
        ])->assertUnauthorized();
    }

    public function test_non_administrator_cannot_create_or_update_roles(): void
    {
        $this->createLegacyAuthUser();

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

        $this->postJson('/api/v1/roles', [
            'nombre_rol' => 'Supervisor',
            'estado' => 'AC',
        ])->assertForbidden();

        $this->putJson('/api/v1/roles/1', [
            'nombre_rol' => 'Administrador Editado',
            'estado' => 'AC',
        ])->assertForbidden();

        $this->getJson('/api/v1/roles')->assertForbidden();

        $this->getJson('/api/v1/roles/1')->assertForbidden();

        $this->patchJson('/api/v1/roles/1/status', [
            'estado' => 'DC',
        ])->assertForbidden();
    }
}

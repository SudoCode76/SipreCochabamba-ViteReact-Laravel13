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

class UserManagementTest extends TestCase
{
    use InteractsWithLegacyAuth;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLegacyAuthSchema();
    }

    public function test_admin_can_list_users_with_filters_and_pagination(): void
    {
        $admin = $this->createAdminUser();
        $unit = Unit::query()->create([
            'id_unidad' => 2,
            'descripcion' => 'Unidad Tecnica',
            'estado' => 'AC',
        ]);

        User::query()->create([
            'id_usuario' => 2,
            'funcionario' => 'Juan Perez',
            'ci' => '11111111',
            'username' => 'JUAN',
            'clave' => Hash::make('secret123'),
            'estado' => 'AC',
            'id_unidad' => $unit->id_unidad,
            'rol' => 1,
            'fecha' => now()->toDateString(),
        ]);

        User::query()->create([
            'id_usuario' => 3,
            'funcionario' => 'Maria Gomez',
            'ci' => '22222222',
            'username' => 'MARIA',
            'clave' => Hash::make('secret123'),
            'estado' => 'DC',
            'id_unidad' => $unit->id_unidad,
            'rol' => 1,
            'fecha' => now()->toDateString(),
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/users?name=juan&status=AC&per_page=10');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.username', 'JUAN');

        $this->getJson('/api/v1/users?search=2222&per_page=10')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.username', 'MARIA');

        $this->getJson('/api/v1/users?search=maria&per_page=10')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.ci', '22222222');

        $this->getJson('/api/v1/users?search=tecnica&per_page=10')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 2);

        $this->getJson('/api/v1/users?search=administrador&per_page=10')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 3);
    }

    public function test_admin_can_create_user(): void
    {
        $admin = $this->createAdminUser();
        $role = Role::query()->create([
            'id_rol' => 2,
            'nombre_rol' => 'Tecnico',
            'estado' => 'AC',
        ]);
        $unit = Unit::query()->create([
            'id_unidad' => 2,
            'descripcion' => 'Unidad Tecnica',
            'estado' => 'AC',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/users', [
            'funcionario' => 'Nuevo Usuario',
            'ci' => '33333333',
            'username' => 'nuevo',
            'password' => 'newSecret123',
            'password_confirmation' => 'newSecret123',
            'estado' => 'AC',
            'role_id' => $role->id_rol,
            'unit_id' => $unit->id_unidad,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.username', 'NUEVO')
            ->assertJsonPath('data.user.role.id', $role->id_rol)
            ->assertJsonPath('data.user.unit.id', $unit->id_unidad);

        $this->assertDatabaseHas('usuario', [
            'username' => 'NUEVO',
            'ci' => '33333333',
            'rol' => $role->id_rol,
        ]);
    }

    public function test_admin_can_create_user_resolving_unit_by_description(): void
    {
        $admin = $this->createAdminUser();
        $role = Role::query()->create([
            'id_rol' => 2,
            'nombre_rol' => 'Tecnico',
            'estado' => 'AC',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/users', [
            'funcionario' => 'Nuevo Usuario Unidad Texto',
            'ci' => '33333334',
            'username' => 'nuevotexto',
            'password' => 'newSecret123',
            'password_confirmation' => 'newSecret123',
            'estado' => 'AC',
            'role_id' => $role->id_rol,
            'unidad' => [
                'descripcion' => 'DIRECCION DE AUDITORIA INTERNA',
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.unit.description', 'DIRECCION DE AUDITORIA INTERNA');

        $this->assertDatabaseHas('unidad', [
            'descripcion' => 'DIRECCION DE AUDITORIA INTERNA',
        ]);
    }

    public function test_admin_reuses_existing_unit_when_description_matches_with_case_or_extra_spaces(): void
    {
        $admin = $this->createAdminUser();
        $role = Role::query()->create([
            'id_rol' => 2,
            'nombre_rol' => 'Tecnico',
            'estado' => 'AC',
        ]);

        $unit = Unit::query()->create([
            'id_unidad' => 2,
            'descripcion' => 'DIRECCION DE AUDITORIA INTERNA',
            'estado' => 'AC',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/users', [
            'funcionario' => 'Usuario Sin Duplicar Unidad',
            'ci' => '33333335',
            'username' => 'norepiteunidad',
            'password' => 'newSecret123',
            'password_confirmation' => 'newSecret123',
            'estado' => 'AC',
            'role_id' => $role->id_rol,
            'unidad' => [
                'descripcion' => '  direccion   de auditoria interna  ',
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.unit.id', $unit->id_unidad);

        $this->assertDatabaseCount('unidad', 2);
    }

    public function test_admin_can_view_and_update_user(): void
    {
        $admin = $this->createAdminUser();
        $role = Role::query()->create([
            'id_rol' => 2,
            'nombre_rol' => 'Tecnico',
            'estado' => 'AC',
        ]);
        $unit = Unit::query()->create([
            'id_unidad' => 2,
            'descripcion' => 'Unidad Tecnica',
            'estado' => 'AC',
        ]);

        $user = User::query()->create([
            'id_usuario' => 2,
            'funcionario' => 'Usuario Inicial',
            'ci' => '44444444',
            'username' => 'INICIAL',
            'clave' => Hash::make('secret123'),
            'estado' => 'AC',
            'id_unidad' => $unit->id_unidad,
            'rol' => $role->id_rol,
            'fecha' => now()->toDateString(),
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/users/'.$user->id_usuario)
            ->assertOk()
            ->assertJsonPath('data.user.username', 'INICIAL');

        $response = $this->putJson('/api/v1/users/'.$user->id_usuario, [
            'funcionario' => 'Usuario Editado',
            'ci' => '55555555',
            'username' => 'editado',
            'estado' => 'DC',
            'role_id' => $role->id_rol,
            'unit_id' => $unit->id_unidad,
            'item' => 10,
            'subalcaldia' => 20,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.full_name', 'Usuario Editado')
            ->assertJsonPath('data.user.username', 'EDITADO')
            ->assertJsonPath('data.user.status', 'DC')
            ->assertJsonPath('data.user.item', 10)
            ->assertJsonPath('data.user.subalcaldia', 20);
    }

    public function test_admin_can_update_user_status_role_and_unit(): void
    {
        $admin = $this->createAdminUser();
        $role = Role::query()->create([
            'id_rol' => 2,
            'nombre_rol' => 'Tecnico',
            'estado' => 'AC',
        ]);
        $anotherRole = Role::query()->create([
            'id_rol' => 3,
            'nombre_rol' => 'Visualizacion',
            'estado' => 'AC',
        ]);
        $unit = Unit::query()->create([
            'id_unidad' => 2,
            'descripcion' => 'Unidad Tecnica',
            'estado' => 'AC',
        ]);
        $anotherUnit = Unit::query()->create([
            'id_unidad' => 3,
            'descripcion' => 'Unidad Operativa',
            'estado' => 'AC',
        ]);

        $user = User::query()->create([
            'id_usuario' => 2,
            'funcionario' => 'Usuario Inicial',
            'ci' => '66666666',
            'username' => 'INICIAL',
            'clave' => Hash::make('secret123'),
            'estado' => 'AC',
            'id_unidad' => $unit->id_unidad,
            'rol' => $role->id_rol,
            'fecha' => now()->toDateString(),
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson('/api/v1/users/'.$user->id_usuario.'/status', [
            'estado' => 'DC',
        ])->assertOk()->assertJsonPath('data.user.status', 'DC');

        $this->patchJson('/api/v1/users/'.$user->id_usuario.'/role', [
            'role_id' => $anotherRole->id_rol,
        ])->assertOk()->assertJsonPath('data.user.role.id', $anotherRole->id_rol);

        $this->patchJson('/api/v1/users/'.$user->id_usuario.'/unit', [
            'unit_id' => $anotherUnit->id_unidad,
        ])->assertOk()->assertJsonPath('data.user.unit.id', $anotherUnit->id_unidad);
    }

    public function test_non_admin_cannot_manage_users(): void
    {
        $role = Role::query()->create([
            'id_rol' => 2,
            'nombre_rol' => 'Tecnico',
            'estado' => 'AC',
        ]);
        $unit = Unit::query()->create([
            'id_unidad' => 2,
            'descripcion' => 'Unidad Tecnica',
            'estado' => 'AC',
        ]);

        $user = User::query()->create([
            'id_usuario' => 2,
            'funcionario' => 'Usuario Tecnico',
            'ci' => '77777777',
            'username' => 'TECNICO',
            'clave' => Hash::make('secret123'),
            'estado' => 'AC',
            'id_unidad' => $unit->id_unidad,
            'rol' => $role->id_rol,
            'fecha' => now()->toDateString(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/users')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    private function createAdminUser(): User
    {
        return $this->createLegacyAuthUser();
    }
}

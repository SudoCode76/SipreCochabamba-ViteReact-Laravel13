<?php

namespace Tests\Feature\RolePermission;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemFunction;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithLegacyAuth;
use Tests\TestCase;

class RolePermissionApiTest extends TestCase
{
    use InteractsWithLegacyAuth;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLegacyAuthSchema();
    }

    public function test_authenticated_user_can_get_role_permissions(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $role = Role::query()->create([
            'id_rol' => 2,
            'nombre_rol' => 'Tecnico',
            'estado' => 'AC',
        ]);

        $function = SystemFunction::query()->create([
            'id_funcion' => 2,
            'nombre_funcion' => 'insumos.index',
            'descripcion' => 'Listar insumos',
            'clase' => 'Insumos',
            'estado' => 'AC',
        ]);

        Permission::query()->create([
            'id_permiso' => 2,
            'id_rol' => $role->id_rol,
            'nombre_rol' => $role->nombre_rol,
            'id_funcion' => $function->id_funcion,
            'descripcion' => 'Puede listar insumos',
            'estado' => 'AC',
        ]);

        SystemFunction::query()->create([
            'id_funcion' => 3,
            'nombre_funcion' => 'insumos.store',
            'descripcion' => 'Crear insumos',
            'clase' => 'Insumos',
            'estado' => 'AC',
        ]);

        $response = $this->getJson("/api/v1/roles/{$role->id_rol}/permissions?search=insumos.index&per_page=1");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.role.id', $role->id_rol)
            ->assertJsonPath('data.role.name', 'Tecnico')
            ->assertJsonCount(1, 'data.permissions')
            ->assertJsonPath('data.permissions.0.function.name', 'insumos.index')
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonFragment(['name' => 'insumos.store']);
    }

    public function test_authenticated_user_can_get_role_permissions_context(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $role = Role::query()->create([
            'id_rol' => 2,
            'nombre_rol' => 'Tecnico',
            'estado' => 'AC',
        ]);

        SystemFunction::query()->create([
            'id_funcion' => 2,
            'nombre_funcion' => 'insumos.index',
            'descripcion' => 'Listar insumos',
            'clase' => 'Insumos',
            'estado' => 'AC',
        ]);

        $this->getJson("/api/v1/roles/{$role->id_rol}/permissions/context")
            ->assertOk()
            ->assertJsonPath('data.role.id', $role->id_rol)
            ->assertJsonFragment(['name' => 'insumos.index'])
            ->assertJsonPath('data.endpoints.attach', "/api/v1/roles/{$role->id_rol}/permissions/attach");
    }

    public function test_authenticated_user_can_sync_role_permissions(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $role = Role::query()->create([
            'id_rol' => 2,
            'nombre_rol' => 'Tecnico',
            'estado' => 'AC',
        ]);

        $functionOne = SystemFunction::query()->create([
            'id_funcion' => 2,
            'nombre_funcion' => 'insumos.index',
            'descripcion' => 'Listar insumos',
            'clase' => 'Insumos',
            'estado' => 'AC',
        ]);

        $functionTwo = SystemFunction::query()->create([
            'id_funcion' => 3,
            'nombre_funcion' => 'insumos.store',
            'descripcion' => 'Crear insumos',
            'clase' => 'Insumos',
            'estado' => 'AC',
        ]);

        Permission::query()->create([
            'id_permiso' => 2,
            'id_rol' => $role->id_rol,
            'nombre_rol' => $role->nombre_rol,
            'id_funcion' => $functionOne->id_funcion,
            'descripcion' => 'Puede listar insumos',
            'estado' => 'AC',
        ]);

        $response = $this->putJson("/api/v1/roles/{$role->id_rol}/permissions", [
            'function_ids' => [$functionTwo->id_funcion],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.role.id', $role->id_rol)
            ->assertJsonPath('data.function_ids.0', $functionTwo->id_funcion)
            ->assertJsonPath('data.permissions_count', 1);

        $this->assertDatabaseHas('permiso', [
            'id_rol' => $role->id_rol,
            'id_funcion' => $functionOne->id_funcion,
            'estado' => 'DC',
        ]);

        $this->assertDatabaseHas('permiso', [
            'id_rol' => $role->id_rol,
            'id_funcion' => $functionTwo->id_funcion,
            'estado' => 'AC',
        ]);
    }

    public function test_authenticated_user_can_sync_role_permissions_with_post_endpoint(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $role = Role::query()->create([
            'id_rol' => 2,
            'nombre_rol' => 'Tecnico',
            'estado' => 'AC',
        ]);

        $function = SystemFunction::query()->create([
            'id_funcion' => 2,
            'nombre_funcion' => 'insumos.index',
            'descripcion' => 'Listar insumos',
            'clase' => 'Insumos',
            'estado' => 'AC',
        ]);

        $response = $this->postJson("/api/v1/roles/{$role->id_rol}/permissions/sync", [
            'function_ids' => [$function->id_funcion],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Permisos del rol sincronizados correctamente.')
            ->assertJsonPath('data.role.id', $role->id_rol)
            ->assertJsonPath('data.function_ids.0', $function->id_funcion)
            ->assertJsonPath('data.permissions_count', 1);
    }

    public function test_authenticated_user_can_clone_permissions_from_another_role(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $targetRole = Role::query()->create([
            'id_rol' => 2,
            'nombre_rol' => 'Tecnico',
            'estado' => 'AC',
        ]);

        $sourceRole = Role::query()->create([
            'id_rol' => 3,
            'nombre_rol' => 'Supervisor',
            'estado' => 'AC',
        ]);

        $functionOne = SystemFunction::query()->create([
            'id_funcion' => 2,
            'nombre_funcion' => 'insumos.index',
            'descripcion' => 'Listar insumos',
            'clase' => 'Insumos',
            'estado' => 'AC',
        ]);

        $functionTwo = SystemFunction::query()->create([
            'id_funcion' => 3,
            'nombre_funcion' => 'insumos.store',
            'descripcion' => 'Crear insumos',
            'clase' => 'Insumos',
            'estado' => 'AC',
        ]);

        Permission::query()->create([
            'id_permiso' => 2,
            'id_rol' => $sourceRole->id_rol,
            'nombre_rol' => $sourceRole->nombre_rol,
            'id_funcion' => $functionOne->id_funcion,
            'descripcion' => 'Puede listar insumos',
            'estado' => 'AC',
        ]);

        Permission::query()->create([
            'id_permiso' => 3,
            'id_rol' => $sourceRole->id_rol,
            'nombre_rol' => $sourceRole->nombre_rol,
            'id_funcion' => $functionTwo->id_funcion,
            'descripcion' => 'Puede crear insumos',
            'estado' => 'AC',
        ]);

        $response = $this->postJson("/api/v1/roles/{$targetRole->id_rol}/permissions/clone-from/{$sourceRole->id_rol}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.role.id', $targetRole->id_rol)
            ->assertJsonPath('data.source_role.id', $sourceRole->id_rol)
            ->assertJsonPath('data.function_ids.0', $functionOne->id_funcion)
            ->assertJsonPath('data.function_ids.1', $functionTwo->id_funcion)
            ->assertJsonPath('data.permissions_count', 2);

        $this->assertDatabaseHas('permiso', [
            'id_rol' => $targetRole->id_rol,
            'id_funcion' => $functionOne->id_funcion,
            'estado' => 'AC',
        ]);

        $this->assertDatabaseHas('permiso', [
            'id_rol' => $targetRole->id_rol,
            'id_funcion' => $functionTwo->id_funcion,
            'estado' => 'AC',
        ]);
    }

    public function test_authenticated_user_can_get_permissions_matrix(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $role = Role::query()->create([
            'id_rol' => 2,
            'nombre_rol' => 'Tecnico',
            'estado' => 'AC',
        ]);

        $function = SystemFunction::query()->create([
            'id_funcion' => 2,
            'nombre_funcion' => 'insumos.index',
            'descripcion' => 'Listar insumos',
            'clase' => 'Insumos',
            'estado' => 'AC',
        ]);

        Permission::query()->create([
            'id_permiso' => 2,
            'id_rol' => $role->id_rol,
            'nombre_rol' => $role->nombre_rol,
            'id_funcion' => $function->id_funcion,
            'descripcion' => 'Puede listar insumos',
            'estado' => 'AC',
        ]);

        $response = $this->getJson('/api/v1/permissions/matrix');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.roles')
            ->assertJsonPath('data.functions.1.name', 'insumos.index')
            ->assertJsonPath('data.functions.1.assigned_role_ids.0', $role->id_rol)
            ->assertJsonPath('data.functions.1.permissions.1.role_id', $role->id_rol)
            ->assertJsonPath('data.functions.1.permissions.1.allowed', true);
    }

    public function test_authenticated_user_can_attach_a_permission_to_role(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $role = Role::query()->create([
            'id_rol' => 2,
            'nombre_rol' => 'Tecnico',
            'estado' => 'AC',
        ]);

        $function = SystemFunction::query()->create([
            'id_funcion' => 2,
            'nombre_funcion' => 'insumos.index',
            'descripcion' => 'Listar insumos',
            'clase' => 'Insumos',
            'estado' => 'AC',
        ]);

        $response = $this->postJson("/api/v1/roles/{$role->id_rol}/permissions/attach", [
            'function_id' => $function->id_funcion,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.role.id', $role->id_rol)
            ->assertJsonPath('data.function_id', $function->id_funcion);

        $this->assertDatabaseHas('permiso', [
            'id_rol' => $role->id_rol,
            'id_funcion' => $function->id_funcion,
            'estado' => 'AC',
        ]);
    }

    public function test_authenticated_user_can_detach_a_permission_from_role(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $role = Role::query()->create([
            'id_rol' => 2,
            'nombre_rol' => 'Tecnico',
            'estado' => 'AC',
        ]);

        $function = SystemFunction::query()->create([
            'id_funcion' => 2,
            'nombre_funcion' => 'insumos.index',
            'descripcion' => 'Listar insumos',
            'clase' => 'Insumos',
            'estado' => 'AC',
        ]);

        Permission::query()->create([
            'id_permiso' => 2,
            'id_rol' => $role->id_rol,
            'nombre_rol' => $role->nombre_rol,
            'id_funcion' => $function->id_funcion,
            'descripcion' => 'Puede listar insumos',
            'estado' => 'AC',
        ]);

        $response = $this->postJson("/api/v1/roles/{$role->id_rol}/permissions/detach", [
            'function_id' => $function->id_funcion,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.role.id', $role->id_rol)
            ->assertJsonPath('data.function_id', $function->id_funcion);

        $this->assertDatabaseHas('permiso', [
            'id_rol' => $role->id_rol,
            'id_funcion' => $function->id_funcion,
            'estado' => 'DC',
        ]);
    }

    public function test_role_permissions_endpoints_require_authentication(): void
    {
        $role = Role::query()->create([
            'id_rol' => 2,
            'nombre_rol' => 'Tecnico',
            'estado' => 'AC',
        ]);

        $this->getJson("/api/v1/roles/{$role->id_rol}/permissions")
            ->assertUnauthorized();

        $this->getJson("/api/v1/roles/{$role->id_rol}/permissions/context")
            ->assertUnauthorized();

        $this->putJson("/api/v1/roles/{$role->id_rol}/permissions", [
            'function_ids' => [],
        ])->assertUnauthorized();

        $this->postJson("/api/v1/roles/{$role->id_rol}/permissions/attach", [
            'function_id' => 1,
        ])->assertUnauthorized();

        $this->postJson("/api/v1/roles/{$role->id_rol}/permissions/detach", [
            'function_id' => 1,
        ])->assertUnauthorized();

        $this->postJson("/api/v1/roles/{$role->id_rol}/permissions/sync", [
            'function_ids' => [],
        ])->assertUnauthorized();

        $this->postJson('/api/v1/roles/2/permissions/clone-from/1')
            ->assertUnauthorized();

        $this->getJson('/api/v1/permissions/matrix')
            ->assertUnauthorized();
    }

    public function test_non_administrator_cannot_access_role_permission_endpoints(): void
    {
        $adminUser = $this->createLegacyAuthUser();

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

        $function = SystemFunction::query()->create([
            'id_funcion' => 2,
            'nombre_funcion' => 'insumos.index',
            'descripcion' => 'Listar insumos',
            'clase' => 'Insumos',
            'estado' => 'AC',
        ]);

        Permission::query()->create([
            'id_permiso' => 2,
            'id_rol' => $adminUser->rol,
            'nombre_rol' => 'Administrador',
            'id_funcion' => $function->id_funcion,
            'descripcion' => 'Puede listar insumos',
            'estado' => 'AC',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/permissions/matrix')
            ->assertForbidden()
            ->assertJsonPath('success', false);

        $this->getJson('/api/v1/roles/1/permissions')
            ->assertForbidden()
            ->assertJsonPath('success', false);

        $this->getJson('/api/v1/roles/1/permissions/context')
            ->assertForbidden()
            ->assertJsonPath('success', false);

        $this->putJson('/api/v1/roles/1/permissions', [
            'function_ids' => [$function->id_funcion],
        ])->assertForbidden();

        $this->postJson('/api/v1/roles/1/permissions/attach', [
            'function_id' => $function->id_funcion,
        ])->assertForbidden();

        $this->postJson('/api/v1/roles/1/permissions/detach', [
            'function_id' => $function->id_funcion,
        ])->assertForbidden();

        $this->postJson('/api/v1/roles/1/permissions/sync', [
            'function_ids' => [$function->id_funcion],
        ])->assertForbidden();

        $this->postJson('/api/v1/roles/1/permissions/clone-from/2')
            ->assertForbidden();
    }
}

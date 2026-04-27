<?php

namespace Tests\Feature\Auth;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemFunction;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_legacy_md5_password_and_it_is_migrated(): void
    {
        $user = $this->createUser([
            'clave' => strtoupper(md5('secret123')),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => $user->username,
            'clave' => 'secret123',
            'device_name' => 'prueba-web',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.username', $user->username)
            ->assertJsonCount(1, 'data.user.permissions');

        $user->refresh();

        $this->assertNotSame(strtoupper(md5('secret123')), $user->clave);
        $this->assertTrue(Hash::check('secret123', $user->clave));
    }

    public function test_user_can_login_with_username_in_different_case(): void
    {
        $user = $this->createUser([
            'username' => 'ADMINISTRADOR',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'administrador',
            'clave' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.username', $user->username);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $user = $this->createUser();

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => $user->username,
            'clave' => 'incorrecta',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Credenciales invalidas.');
    }

    public function test_login_fails_for_inactive_users(): void
    {
        $user = $this->createUser([
            'estado' => 'DC',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => $user->username,
            'clave' => 'secret123',
        ]);

        $response->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'El usuario no tiene acceso habilitado.');
    }

    public function test_authenticated_user_profile_can_be_retrieved(): void
    {
        $user = $this->createUser();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.username', $user->username)
            ->assertJsonPath('data.user.role.name', 'Administrador')
            ->assertJsonCount(1, 'data.user.permissions');
    }

    public function test_authenticated_user_can_logout_and_current_token_is_deleted(): void
    {
        $user = $this->createUser();
        $token = $user->createToken('prueba')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/v1/auth/logout');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Sesion cerrada correctamente.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    private function createUser(array $overrides = []): User
    {
        $unit = Unit::query()->create([
            'id_unidad' => 1,
            'descripcion' => 'Unidad Central',
            'estado' => 'AC',
        ]);

        $role = Role::query()->create([
            'id_rol' => 1,
            'nombre_rol' => 'Administrador',
            'estado' => 'AC',
        ]);

        $function = SystemFunction::query()->create([
            'id_funcion' => 1,
            'nombre_funcion' => 'auth.login',
            'descripcion' => 'Iniciar sesion',
            'clase' => 'Auth',
            'estado' => 'AC',
        ]);

        Permission::query()->create([
            'id_permiso' => 1,
            'id_rol' => $role->id_rol,
            'nombre_rol' => $role->nombre_rol,
            'id_funcion' => $function->id_funcion,
            'descripcion' => 'Puede iniciar sesion',
            'estado' => 'AC',
        ]);

        return User::query()->create(array_merge([
            'id_usuario' => 1,
            'funcionario' => 'Usuario Demo',
            'ci' => '12345678',
            'username' => 'demo',
            'clave' => Hash::make('secret123'),
            'estado' => 'AC',
            'id_unidad' => $unit->id_unidad,
            'rol' => $role->id_rol,
            'fecha' => now()->toDateString(),
            'subalcaldia' => null,
        ], $overrides));
    }
}

<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithLegacyAuth;
use Tests\TestCase;

class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithLegacyAuth;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLegacyAuthSchema();
    }

    public function test_authenticated_user_can_change_password(): void
    {
        $user = $this->createUser();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/auth/change-password', [
            'current_password' => 'secret123',
            'password' => 'newSecret123',
            'password_confirmation' => 'newSecret123',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Contrasena actualizada correctamente.');

        $user->refresh();

        $this->assertTrue(Hash::check('newSecret123', $user->clave));
        $this->assertFalse(Hash::check('secret123', $user->clave));
    }

    public function test_authenticated_user_can_change_legacy_md5_password(): void
    {
        $user = $this->createUser([
            'clave' => strtoupper(md5('secret123')),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/auth/change-password', [
            'current_password' => 'secret123',
            'password' => 'newSecret123',
            'password_confirmation' => 'newSecret123',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $user->refresh();

        $this->assertTrue(Hash::check('newSecret123', $user->clave));
    }

    public function test_change_password_fails_with_invalid_current_password(): void
    {
        $user = $this->createUser();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/auth/change-password', [
            'current_password' => 'incorrecta',
            'password' => 'newSecret123',
            'password_confirmation' => 'newSecret123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'La contrasena actual no es valida.');

        $user->refresh();

        $this->assertTrue(Hash::check('secret123', $user->clave));
    }

    public function test_change_password_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/auth/change-password', [
            'current_password' => 'secret123',
            'password' => 'newSecret123',
            'password_confirmation' => 'newSecret123',
        ]);

        $response->assertUnauthorized();
    }

    private function createUser(array $overrides = []): User
    {
        return $this->createLegacyAuthUser($overrides, 'auth.change-password', 'Cambiar contrasena');
    }
}

<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithLegacyAuth;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithLegacyAuth;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLegacyAuthSchema();
    }

    public function test_authenticated_user_can_view_profile(): void
    {
        $user = $this->createUser();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/profile');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Perfil obtenido correctamente.')
            ->assertJsonPath('data.user.username', $user->username)
            ->assertJsonPath('data.user.role.name', 'Administrador')
            ->assertJsonCount(1, 'data.user.permissions');
    }

    public function test_profile_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/profile');

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_can_change_password_from_profile(): void
    {
        $user = $this->createUser();

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/v1/profile/password', [
            'current_password' => 'secret123',
            'password' => 'newSecret123',
            'password_confirmation' => 'newSecret123',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Contrasena actualizada correctamente.');

        $user->refresh();

        $this->assertTrue(Hash::check('newSecret123', $user->clave));
    }

    public function test_profile_password_requires_authentication(): void
    {
        $response = $this->putJson('/api/v1/profile/password', [
            'current_password' => 'secret123',
            'password' => 'newSecret123',
            'password_confirmation' => 'newSecret123',
        ]);

        $response->assertUnauthorized();
    }

    private function createUser(array $overrides = []): User
    {
        return $this->createLegacyAuthUser($overrides, 'auth.profile', 'Ver perfil');
    }
}

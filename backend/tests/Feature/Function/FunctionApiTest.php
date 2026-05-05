<?php

namespace Tests\Feature\Function;

use App\Models\Role;
use App\Models\SystemFunction;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithLegacyAuth;
use Tests\TestCase;

class FunctionApiTest extends TestCase
{
    use InteractsWithLegacyAuth;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLegacyAuthSchema();
    }

    public function test_administrator_can_list_and_show_functions(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $function = SystemFunction::query()->create([
            'id_funcion' => 2,
            'nombre_funcion' => 'USUARIOS',
            'descripcion' => 'Administrar usuarios',
            'clase' => 'ADMINISTRADOR',
            'estado' => 'AC',
        ]);

        $this->getJson('/api/v1/functions')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.items.0.id', 2)
            ->assertJsonPath('data.items.0.name', 'USUARIOS')
            ->assertJsonPath('data.items.1.id', 1);

        $this->getJson("/api/v1/functions/{$function->id_funcion}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.function.id', $function->id_funcion)
            ->assertJsonPath('data.function.name', 'USUARIOS')
            ->assertJsonPath('data.function.class', 'ADMINISTRADOR');
    }

    public function test_administrator_can_create_function(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $response = $this->postJson('/api/v1/functions', [
            'nombre_funcion' => 'INPUT_QUOTES',
            'descripcion' => 'Gestionar cotizaciones',
            'clase' => 'INSUMO',
            'estado' => 'AC',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.function.name', 'INPUT_QUOTES')
            ->assertJsonPath('data.function.class', 'INSUMO')
            ->assertJsonPath('data.function.status', 'AC');

        $this->assertDatabaseHas('funcion', [
            'nombre_funcion' => 'INPUT_QUOTES',
            'descripcion' => 'Gestionar cotizaciones',
            'clase' => 'INSUMO',
            'estado' => 'AC',
        ]);
    }

    public function test_administrator_can_update_function_and_status(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $function = SystemFunction::query()->create([
            'id_funcion' => 2,
            'nombre_funcion' => 'INPUTS',
            'descripcion' => 'Administrar insumos',
            'clase' => 'INSUMO',
            'estado' => 'AC',
        ]);

        $this->putJson("/api/v1/functions/{$function->id_funcion}", [
            'nombre_funcion' => 'INPUTS_ADMIN',
            'descripcion' => 'Administrar insumos y cotizaciones',
            'clase' => 'INSUMO',
            'estado' => 'DC',
        ])->assertOk()
            ->assertJsonPath('data.function.name', 'INPUTS_ADMIN')
            ->assertJsonPath('data.function.status', 'DC');

        $this->patchJson("/api/v1/functions/{$function->id_funcion}/status", [
            'estado' => 'AC',
        ])->assertOk()
            ->assertJsonPath('data.function.status', 'AC');

        $this->assertDatabaseHas('funcion', [
            'id_funcion' => $function->id_funcion,
            'nombre_funcion' => 'INPUTS_ADMIN',
            'estado' => 'AC',
        ]);
    }

    public function test_function_name_must_be_unique_globally(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        SystemFunction::query()->create([
            'id_funcion' => 2,
            'nombre_funcion' => 'USUARIOS',
            'descripcion' => 'Administrar usuarios',
            'clase' => 'ADMINISTRADOR',
            'estado' => 'AC',
        ]);

        $this->postJson('/api/v1/functions', [
            'nombre_funcion' => 'USUARIOS',
            'descripcion' => 'Duplicada',
            'clase' => 'OTRA',
            'estado' => 'AC',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['nombre_funcion']);
    }

    public function test_function_endpoints_require_authentication(): void
    {
        $function = SystemFunction::query()->create([
            'id_funcion' => 2,
            'nombre_funcion' => 'USUARIOS',
            'descripcion' => 'Administrar usuarios',
            'clase' => 'ADMINISTRADOR',
            'estado' => 'AC',
        ]);

        $this->getJson('/api/v1/functions')->assertUnauthorized();
        $this->postJson('/api/v1/functions', [])->assertUnauthorized();
        $this->getJson("/api/v1/functions/{$function->id_funcion}")->assertUnauthorized();
        $this->putJson("/api/v1/functions/{$function->id_funcion}", [])->assertUnauthorized();
        $this->patchJson("/api/v1/functions/{$function->id_funcion}/status", [])->assertUnauthorized();
    }

    public function test_non_administrator_cannot_manage_functions(): void
    {
        $this->createLegacyAuthUser();

        $function = SystemFunction::query()->create([
            'id_funcion' => 2,
            'nombre_funcion' => 'USUARIOS',
            'descripcion' => 'Administrar usuarios',
            'clase' => 'ADMINISTRADOR',
            'estado' => 'AC',
        ]);

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

        $this->getJson('/api/v1/functions')->assertForbidden();
        $this->postJson('/api/v1/functions', [
            'nombre_funcion' => 'INPUT_QUOTES',
            'descripcion' => 'Gestionar cotizaciones',
            'clase' => 'INSUMO',
            'estado' => 'AC',
        ])->assertForbidden();
        $this->getJson("/api/v1/functions/{$function->id_funcion}")->assertForbidden();
        $this->putJson("/api/v1/functions/{$function->id_funcion}", [
            'nombre_funcion' => 'EDITADO',
            'descripcion' => 'Editado',
            'clase' => 'ADMINISTRADOR',
            'estado' => 'AC',
        ])->assertForbidden();
        $this->patchJson("/api/v1/functions/{$function->id_funcion}/status", [
            'estado' => 'DC',
        ])->assertForbidden();
    }
}

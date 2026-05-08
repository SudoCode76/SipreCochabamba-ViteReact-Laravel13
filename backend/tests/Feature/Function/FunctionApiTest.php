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
            ->assertJsonPath('data.meta.total', 2)
            ->assertJsonPath('data.meta.from', 1)
            ->assertJsonPath('data.meta.to', 2)
            ->assertJsonPath('data.meta.last_page', 1)
            ->assertJsonPath('data.meta.has_more_pages', false)
            ->assertJsonPath('data.items.0.id', 2)
            ->assertJsonPath('data.items.0.name', 'USUARIOS')
            ->assertJsonPath('data.items.0.controller', 'ADMINISTRADOR')
            ->assertJsonPath('data.items.0.status_label', 'ACTIVO')
            ->assertJsonPath('data.items.0.available_actions.edit', true)
            ->assertJsonPath('data.items.0.available_actions.deactivate', true)
            ->assertJsonPath('data.items.0.available_actions.activate', false)
            ->assertJsonPath('data.items.1.id', 1);

        $this->getJson("/api/v1/functions/{$function->id_funcion}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.function.id', $function->id_funcion)
            ->assertJsonPath('data.function.name', 'USUARIOS')
            ->assertJsonPath('data.function.class', 'ADMINISTRADOR')
            ->assertJsonPath('data.function.controller', 'ADMINISTRADOR');
    }

    public function test_administrator_can_get_context_and_filter_paginated_functions(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        SystemFunction::query()->create([
            'id_funcion' => 2,
            'nombre_funcion' => 'UNIDAD',
            'descripcion' => 'Administrar unidades',
            'clase' => 'ADMINISTRADOR',
            'estado' => 'DC',
        ]);

        SystemFunction::query()->create([
            'id_funcion' => 3,
            'nombre_funcion' => 'LISTA_INSUMO',
            'descripcion' => 'Listar insumos',
            'clase' => 'INSUMO',
            'estado' => 'AC',
        ]);

        $this->getJson('/api/v1/functions/context')
            ->assertOk()
            ->assertJsonPath('data.statuses.0.code', 'AC')
            ->assertJsonPath('data.statuses.0.label', 'ACTIVO')
            ->assertJsonFragment(['value' => 'ADMINISTRADOR'])
            ->assertJsonFragment(['value' => 'INSUMO']);

        $this->getJson('/api/v1/functions?search=unidad&controlador=ADMINISTRADOR&estado=INACTIVO&per_page=1')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.meta.per_page', 1)
            ->assertJsonPath('data.meta.from', 1)
            ->assertJsonPath('data.meta.to', 1)
            ->assertJsonPath('data.items.0.name', 'UNIDAD')
            ->assertJsonPath('data.items.0.status', 'DC')
            ->assertJsonPath('data.items.0.available_actions.activate', true)
            ->assertJsonPath('data.items.0.available_actions.deactivate', false)
            ->assertJsonPath('data.items.0.status_label', 'INACTIVO');
    }

    public function test_administrator_can_create_function(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $response = $this->postJson('/api/v1/functions', [
            'name' => 'INPUT_QUOTES',
            'description' => 'Gestionar cotizaciones',
            'controlador' => 'INSUMO',
            'status' => 'ACTIVO',
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
            'name' => 'INPUTS_ADMIN',
            'description' => 'Administrar insumos y cotizaciones',
            'class' => 'INSUMO',
            'status' => 'INACTIVO',
        ])->assertOk()
            ->assertJsonPath('data.function.name', 'INPUTS_ADMIN')
            ->assertJsonPath('data.function.status', 'DC');

        $this->patchJson("/api/v1/functions/{$function->id_funcion}/status", [
            'status' => 'ACTIVO',
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

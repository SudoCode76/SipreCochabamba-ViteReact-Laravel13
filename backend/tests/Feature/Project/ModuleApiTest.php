<?php

namespace Tests\Feature\Project;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemFunction;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithLegacyAuth;
use Tests\Concerns\InteractsWithLegacyInputs;
use Tests\Concerns\InteractsWithLegacyItems;
use Tests\Concerns\InteractsWithLegacyProjects;
use Tests\TestCase;

class ModuleApiTest extends TestCase
{
    use InteractsWithLegacyAuth;
    use InteractsWithLegacyInputs;
    use InteractsWithLegacyItems;
    use InteractsWithLegacyProjects;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLegacyAuthSchema();
        $this->setUpLegacyInputSchema();
        $this->setUpLegacyItemSchema();
        $this->setUpLegacyProjectSchema();
    }

    public function test_user_with_module_permission_can_manage_modules(): void
    {
        Sanctum::actingAs($this->createParameterUserWithPermissions(['MODULOS']));

        $this->getJson('/api/v1/modules/context')
            ->assertOk()
            ->assertJsonPath('data.permissions.can_create', true);

        $create = $this->postJson('/api/v1/modules', [
            'nombre_modulo' => 'Modulo 1',
            'estado' => 'AC',
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.module.nombre_modulo', 'Modulo 1');

        $moduleId = $create->json('data.module.id_modulo');

        $this->putJson('/api/v1/modules/'.$moduleId, [
            'nombre_modulo' => 'Modulo actualizado',
            'estado' => 'DC',
        ])->assertOk()
            ->assertJsonPath('data.module.nombre_modulo', 'Modulo actualizado')
            ->assertJsonPath('data.module.estado', 'DC');

        $this->getJson('/api/v1/modules')
            ->assertOk()
            ->assertJsonPath('data.items.0.nombre_modulo', 'General')
            ->assertJsonPath('data.items.1.nombre_modulo', 'Modulo actualizado');
    }

    public function test_user_without_module_permission_cannot_manage_modules(): void
    {
        Sanctum::actingAs($this->createParameterUserWithPermissions([]));

        $this->getJson('/api/v1/modules')->assertForbidden();
        $this->postJson('/api/v1/modules', [
            'nombre_modulo' => 'Modulo bloqueado',
            'estado' => 'AC',
        ])->assertForbidden();
    }

    private function createParameterUserWithPermissions(array $functionNames): User
    {
        $role = Role::query()->create([
            'id_rol' => 2,
            'nombre_rol' => 'Tecnico Parametros',
            'estado' => 'AC',
        ]);

        $unit = Unit::query()->firstOrCreate([
            'id_unidad' => 2,
        ], [
            'descripcion' => 'Unidad Parametros',
            'estado' => 'AC',
        ]);

        foreach (array_values($functionNames) as $index => $functionName) {
            $function = SystemFunction::query()->create([
                'id_funcion' => 300 + $index,
                'nombre_funcion' => $functionName,
                'descripcion' => $functionName,
                'clase' => 'PARAMETROS',
                'estado' => 'AC',
            ]);

            Permission::query()->create([
                'id_permiso' => 300 + $index,
                'id_rol' => $role->id_rol,
                'nombre_rol' => $role->nombre_rol,
                'id_funcion' => $function->id_funcion,
                'descripcion' => $function->descripcion,
                'estado' => 'AC',
            ]);
        }

        return User::query()->create([
            'id_usuario' => 2,
            'funcionario' => 'Usuario Parametros',
            'ci' => '98765432',
            'username' => 'parametros',
            'clave' => Hash::make('secret123'),
            'estado' => 'AC',
            'id_unidad' => $unit->id_unidad,
            'rol' => $role->id_rol,
            'fecha' => now()->toDateString(),
            'subalcaldia' => null,
        ]);
    }
}

<?php

namespace Tests\Feature\Item;

use App\Models\Role;
use App\Models\Unit;
use App\Models\UpreCalculationPercentage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithLegacyAuth;
use Tests\Concerns\InteractsWithLegacyInputs;
use Tests\Concerns\InteractsWithLegacyItems;
use Tests\TestCase;

class UpreCalculationPercentageApiTest extends TestCase
{
    use InteractsWithLegacyAuth;
    use InteractsWithLegacyInputs;
    use InteractsWithLegacyItems;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLegacyAuthSchema();
        $this->setUpLegacyInputSchema();
        $this->setUpLegacyItemSchema();
    }

    public function test_admin_can_list_context_show_create_and_update_upre_calculation_percentages(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        UpreCalculationPercentage::query()->create([
            'id_porcentaje' => 1,
            'codigo' => 'UPRE-001',
            'descripcion' => 'CARGAS SOCIALES',
            'porcentaje' => 30,
            'observacion' => 'Obs 1',
            'estado' => 'AC',
            'usuario' => 1,
        ]);

        UpreCalculationPercentage::query()->create([
            'id_porcentaje' => 2,
            'codigo' => 'UPRE-002',
            'descripcion' => 'IVA',
            'porcentaje' => 14.94,
            'observacion' => 'Obs 2',
            'estado' => 'DC',
            'usuario' => 1,
        ]);

        $this->getJson('/api/v1/calculation-percentages/upre')
            ->assertOk()
            ->assertJsonPath('data.items.0.id_porcentaje', 2)
            ->assertJsonPath('data.items.0.status_label', 'INACTIVO')
            ->assertJsonPath('data.items.0.available_actions.edit', true)
            ->assertJsonPath('data.items.1.id_porcentaje', 1)
            ->assertJsonPath('data.items.1.status_label', 'ACTIVO');

        $this->getJson('/api/v1/calculation-percentages/upre/context')
            ->assertOk()
            ->assertJsonPath('data.statuses.0.code', 'AC')
            ->assertJsonPath('data.permissions.can_update', true);

        $this->getJson('/api/v1/calculation-percentages/upre/2')
            ->assertOk()
            ->assertJsonPath('data.calculation_percentage.id_porcentaje', 2)
            ->assertJsonPath('data.calculation_percentage.codigo', 'UPRE-002');

        $this->postJson('/api/v1/calculation-percentages/upre', [
            'codigo' => 'UPRE-003',
            'descripcion' => 'HERRAMIENTAS MENORES',
            'porcentaje' => 5,
            'observacion' => 'Obs 3',
            'estado' => 'AC',
        ])->assertCreated()
            ->assertJsonPath('data.calculation_percentage.codigo', 'UPRE-003')
            ->assertJsonPath('data.calculation_percentage.status_label', 'ACTIVO');

        $this->putJson('/api/v1/calculation-percentages/upre/2', [
            'codigo' => 'UPRE-002A',
            'descripcion' => 'IVA ACTUALIZADO',
            'porcentaje' => 15.5,
            'observacion' => 'Obs editada',
            'estado' => 'AC',
        ])->assertOk()
            ->assertJsonPath('data.calculation_percentage.codigo', 'UPRE-002A')
            ->assertJsonPath('data.calculation_percentage.descripcion', 'IVA ACTUALIZADO')
            ->assertJsonPath('data.calculation_percentage.porcentaje', 15.5)
            ->assertJsonPath('data.calculation_percentage.status_label', 'ACTIVO');
    }

    public function test_upre_calculation_percentage_code_and_description_must_be_unique_within_upre_table(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        UpreCalculationPercentage::query()->create([
            'id_porcentaje' => 1,
            'codigo' => 'UPRE-001',
            'descripcion' => 'CARGAS SOCIALES',
            'porcentaje' => 30,
            'observacion' => null,
            'estado' => 'AC',
            'usuario' => 1,
        ]);

        $this->postJson('/api/v1/calculation-percentages/upre', [
            'codigo' => 'UPRE-001',
            'descripcion' => 'OTRO',
            'porcentaje' => 10,
            'observacion' => null,
            'estado' => 'AC',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.codigo.0', 'Ya existe un porcentaje de calculo UPRE con el mismo codigo.');

        $this->postJson('/api/v1/calculation-percentages/upre', [
            'codigo' => 'UPRE-002',
            'descripcion' => 'CARGAS SOCIALES',
            'porcentaje' => 10,
            'observacion' => null,
            'estado' => 'AC',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.descripcion.0', 'Ya existe un porcentaje de calculo UPRE con la misma descripcion.');
    }

    public function test_upre_calculation_percentage_update_only_checks_duplicates_when_values_change(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        UpreCalculationPercentage::query()->create([
            'id_porcentaje' => 1,
            'codigo' => 'UPRE-001',
            'descripcion' => 'CARGAS SOCIALES',
            'porcentaje' => 30,
            'observacion' => null,
            'estado' => 'AC',
            'usuario' => 1,
        ]);

        UpreCalculationPercentage::query()->create([
            'id_porcentaje' => 2,
            'codigo' => 'UPRE-002',
            'descripcion' => 'IVA',
            'porcentaje' => 14.94,
            'observacion' => null,
            'estado' => 'AC',
            'usuario' => 1,
        ]);

        $this->putJson('/api/v1/calculation-percentages/upre/1', [
            'codigo' => 'UPRE-001',
            'descripcion' => 'CARGAS SOCIALES',
            'porcentaje' => 31,
            'observacion' => 'Sin duplicado',
            'estado' => 'DC',
        ])->assertOk()
            ->assertJsonPath('data.calculation_percentage.estado', 'DC');

        $this->putJson('/api/v1/calculation-percentages/upre/1', [
            'codigo' => 'UPRE-002',
            'descripcion' => 'CARGAS SOCIALES',
            'porcentaje' => 31,
            'observacion' => 'Dup codigo',
            'estado' => 'AC',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.codigo.0', 'Ya existe un porcentaje de calculo UPRE con el mismo codigo.');

        $this->putJson('/api/v1/calculation-percentages/upre/1', [
            'codigo' => 'UPRE-001',
            'descripcion' => 'IVA',
            'porcentaje' => 31,
            'observacion' => 'Dup descripcion',
            'estado' => 'AC',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.descripcion.0', 'Ya existe un porcentaje de calculo UPRE con la misma descripcion.');
    }

    public function test_upre_calculation_percentage_endpoints_require_authentication(): void
    {
        UpreCalculationPercentage::query()->create([
            'id_porcentaje' => 1,
            'codigo' => 'UPRE-001',
            'descripcion' => 'CARGAS SOCIALES',
            'porcentaje' => 30,
            'observacion' => null,
            'estado' => 'AC',
            'usuario' => 1,
        ]);

        $this->getJson('/api/v1/calculation-percentages/upre')->assertUnauthorized();
        $this->getJson('/api/v1/calculation-percentages/upre/context')->assertUnauthorized();
        $this->postJson('/api/v1/calculation-percentages/upre', [])->assertUnauthorized();
        $this->getJson('/api/v1/calculation-percentages/upre/1')->assertUnauthorized();
        $this->putJson('/api/v1/calculation-percentages/upre/1', [])->assertUnauthorized();
    }

    public function test_non_admin_cannot_manage_upre_calculation_percentages(): void
    {
        $this->createLegacyAuthUser();

        UpreCalculationPercentage::query()->create([
            'id_porcentaje' => 1,
            'codigo' => 'UPRE-001',
            'descripcion' => 'CARGAS SOCIALES',
            'porcentaje' => 30,
            'observacion' => null,
            'estado' => 'AC',
            'usuario' => 1,
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

        $this->getJson('/api/v1/calculation-percentages/upre')->assertForbidden();
        $this->getJson('/api/v1/calculation-percentages/upre/context')->assertForbidden();
        $this->postJson('/api/v1/calculation-percentages/upre', [
            'codigo' => 'UPRE-002',
            'descripcion' => 'IVA',
            'porcentaje' => 14.94,
            'observacion' => null,
            'estado' => 'AC',
        ])->assertForbidden();
        $this->getJson('/api/v1/calculation-percentages/upre/1')->assertForbidden();
        $this->putJson('/api/v1/calculation-percentages/upre/1', [
            'codigo' => 'UPRE-001',
            'descripcion' => 'Editado',
            'porcentaje' => 50,
            'observacion' => null,
            'estado' => 'AC',
        ])->assertForbidden();
    }
}

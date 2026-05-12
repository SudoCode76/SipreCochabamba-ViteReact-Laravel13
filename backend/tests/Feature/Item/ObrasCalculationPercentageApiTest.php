<?php

namespace Tests\Feature\Item;

use App\Models\ObrasCalculationPercentage;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithLegacyAuth;
use Tests\Concerns\InteractsWithLegacyInputs;
use Tests\Concerns\InteractsWithLegacyItems;
use Tests\TestCase;

class ObrasCalculationPercentageApiTest extends TestCase
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

    public function test_admin_can_list_context_show_create_and_update_obras_calculation_percentages(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        ObrasCalculationPercentage::query()->create([
            'id_porcentaje' => 1,
            'codigo' => 'OBR-001',
            'descripcion' => 'CARGAS SOCIALES',
            'porcentaje' => 0,
            'observacion' => 'Obs 1',
            'estado' => 'AC',
            'usuario' => 1,
        ]);

        ObrasCalculationPercentage::query()->create([
            'id_porcentaje' => 2,
            'codigo' => 'OBR-002',
            'descripcion' => 'IVA',
            'porcentaje' => 0,
            'observacion' => 'Obs 2',
            'estado' => 'DC',
            'usuario' => 1,
        ]);

        $this->getJson('/api/v1/calculation-percentages/obras?search=IVA&status=INACTIVO&per_page=1')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.meta.per_page', 1)
            ->assertJsonPath('data.items.0.id_porcentaje', 2)
            ->assertJsonPath('data.items.0.id', 2)
            ->assertJsonPath('data.items.0.internal_id', 2)
            ->assertJsonPath('data.items.0.display_id', 'OBR-002')
            ->assertJsonPath('data.items.0.code', 'OBR-002')
            ->assertJsonPath('data.items.0.description', 'IVA')
            ->assertJsonPath('data.items.0.percentage', 0)
            ->assertJsonPath('data.items.0.status_label', 'INACTIVO')
            ->assertJsonPath('data.items.0.available_actions.edit', true)
            ->assertJsonPath('data.items.0.available_actions.select', true);

        $this->getJson('/api/v1/calculation-percentages/obras/context')
            ->assertOk()
            ->assertJsonPath('data.statuses.0.code', 'AC')
            ->assertJsonPath('data.permissions.can_update', true)
            ->assertJsonPath('data.filters.0', 'search')
            ->assertJsonPath('data.endpoints.list', '/api/v1/calculation-percentages/obras');

        $this->getJson('/api/v1/calculation-percentages/obras/2')
            ->assertOk()
            ->assertJsonPath('data.calculation_percentage.id_porcentaje', 2)
            ->assertJsonPath('data.calculation_percentage.id', 2)
            ->assertJsonPath('data.calculation_percentage.display_id', 'OBR-002')
            ->assertJsonPath('data.calculation_percentage.codigo', 'OBR-002')
            ->assertJsonPath('data.calculation_percentage.code', 'OBR-002');

        $this->postJson('/api/v1/calculation-percentages/obras', [
            'code' => 'OBR-003',
            'description' => 'HERRAMIENTAS MENORES',
            'percentage' => 5,
            'observation' => 'Obs 3',
            'status' => 'ACTIVO',
        ])->assertCreated()
            ->assertJsonPath('data.calculation_percentage.codigo', 'OBR-003')
            ->assertJsonPath('data.calculation_percentage.description', 'HERRAMIENTAS MENORES')
            ->assertJsonPath('data.calculation_percentage.status_label', 'ACTIVO');

        $this->putJson('/api/v1/calculation-percentages/obras/2', [
            'code' => 'OBR-002A',
            'description' => 'IVA ACTUALIZADO',
            'percentage' => 15.5,
            'observation' => 'Obs editada',
            'status' => 'ACTIVO',
        ])->assertOk()
            ->assertJsonPath('data.calculation_percentage.codigo', 'OBR-002A')
            ->assertJsonPath('data.calculation_percentage.descripcion', 'IVA ACTUALIZADO')
            ->assertJsonPath('data.calculation_percentage.porcentaje', 15.5)
            ->assertJsonPath('data.calculation_percentage.status_label', 'ACTIVO');
    }

    public function test_obras_calculation_percentage_code_and_description_must_be_unique_within_obras_table(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        ObrasCalculationPercentage::query()->create([
            'id_porcentaje' => 1,
            'codigo' => 'OBR-001',
            'descripcion' => 'CARGAS SOCIALES',
            'porcentaje' => 0,
            'observacion' => null,
            'estado' => 'AC',
            'usuario' => 1,
        ]);

        $this->postJson('/api/v1/calculation-percentages/obras', [
            'codigo' => 'OBR-001',
            'descripcion' => 'OTRO',
            'porcentaje' => 10,
            'observacion' => null,
            'estado' => 'AC',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.codigo.0', 'Ya existe un porcentaje de calculo Obras con el mismo codigo.');

        $this->postJson('/api/v1/calculation-percentages/obras', [
            'codigo' => 'OBR-002',
            'descripcion' => 'CARGAS SOCIALES',
            'porcentaje' => 10,
            'observacion' => null,
            'estado' => 'AC',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.descripcion.0', 'Ya existe un porcentaje de calculo Obras con la misma descripcion.');
    }

    public function test_obras_calculation_percentage_update_only_checks_duplicates_when_values_change(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        ObrasCalculationPercentage::query()->create([
            'id_porcentaje' => 1,
            'codigo' => 'OBR-001',
            'descripcion' => 'CARGAS SOCIALES',
            'porcentaje' => 0,
            'observacion' => null,
            'estado' => 'AC',
            'usuario' => 1,
        ]);

        ObrasCalculationPercentage::query()->create([
            'id_porcentaje' => 2,
            'codigo' => 'OBR-002',
            'descripcion' => 'IVA',
            'porcentaje' => 0,
            'observacion' => null,
            'estado' => 'AC',
            'usuario' => 1,
        ]);

        $this->putJson('/api/v1/calculation-percentages/obras/1', [
            'codigo' => 'OBR-001',
            'descripcion' => 'CARGAS SOCIALES',
            'porcentaje' => 1,
            'observacion' => 'Sin duplicado',
            'estado' => 'DC',
        ])->assertOk()
            ->assertJsonPath('data.calculation_percentage.estado', 'DC');

        $this->putJson('/api/v1/calculation-percentages/obras/1', [
            'codigo' => 'OBR-002',
            'descripcion' => 'CARGAS SOCIALES',
            'porcentaje' => 1,
            'observacion' => 'Dup codigo',
            'estado' => 'AC',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.codigo.0', 'Ya existe un porcentaje de calculo Obras con el mismo codigo.');

        $this->putJson('/api/v1/calculation-percentages/obras/1', [
            'codigo' => 'OBR-001',
            'descripcion' => 'IVA',
            'porcentaje' => 1,
            'observacion' => 'Dup descripcion',
            'estado' => 'AC',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.descripcion.0', 'Ya existe un porcentaje de calculo Obras con la misma descripcion.');
    }

    public function test_obras_calculation_percentage_endpoints_require_authentication(): void
    {
        ObrasCalculationPercentage::query()->create([
            'id_porcentaje' => 1,
            'codigo' => 'OBR-001',
            'descripcion' => 'CARGAS SOCIALES',
            'porcentaje' => 0,
            'observacion' => null,
            'estado' => 'AC',
            'usuario' => 1,
        ]);

        $this->getJson('/api/v1/calculation-percentages/obras')->assertUnauthorized();
        $this->getJson('/api/v1/calculation-percentages/obras/context')->assertUnauthorized();
        $this->postJson('/api/v1/calculation-percentages/obras', [])->assertUnauthorized();
        $this->getJson('/api/v1/calculation-percentages/obras/1')->assertUnauthorized();
        $this->putJson('/api/v1/calculation-percentages/obras/1', [])->assertUnauthorized();
    }

    public function test_non_admin_cannot_manage_obras_calculation_percentages(): void
    {
        $this->createLegacyAuthUser();

        ObrasCalculationPercentage::query()->create([
            'id_porcentaje' => 1,
            'codigo' => 'OBR-001',
            'descripcion' => 'CARGAS SOCIALES',
            'porcentaje' => 0,
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

        $this->getJson('/api/v1/calculation-percentages/obras')->assertForbidden();
        $this->getJson('/api/v1/calculation-percentages/obras/context')->assertForbidden();
        $this->postJson('/api/v1/calculation-percentages/obras', [
            'codigo' => 'OBR-002',
            'descripcion' => 'IVA',
            'porcentaje' => 0,
            'observacion' => null,
            'estado' => 'AC',
        ])->assertForbidden();
        $this->getJson('/api/v1/calculation-percentages/obras/1')->assertForbidden();
        $this->putJson('/api/v1/calculation-percentages/obras/1', [
            'codigo' => 'OBR-001',
            'descripcion' => 'Editado',
            'porcentaje' => 0,
            'observacion' => null,
            'estado' => 'AC',
        ])->assertForbidden();
    }
}

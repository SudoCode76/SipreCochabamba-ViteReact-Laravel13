<?php

namespace Tests\Feature\Item;

use App\Models\FndrCalculationPercentage;
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

class FndrCalculationPercentageApiTest extends TestCase
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

    public function test_admin_can_list_context_show_create_and_update_fndr_calculation_percentages(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        FndrCalculationPercentage::query()->create([
            'id_porcentaje' => 1,
            'codigo' => 'FNDR-001',
            'descripcion' => 'CARGAS SOCIALES',
            'porcentaje' => 50,
            'observacion' => 'Obs 1',
            'estado' => 'AC',
            'usuario' => 1,
        ]);

        FndrCalculationPercentage::query()->create([
            'id_porcentaje' => 2,
            'codigo' => 'FNDR-002',
            'descripcion' => 'IVA',
            'porcentaje' => 10,
            'observacion' => 'Obs 2',
            'estado' => 'DC',
            'usuario' => 1,
        ]);

        $this->getJson('/api/v1/calculation-percentages/fndr?search=IVA&status=INACTIVO&per_page=1')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.meta.per_page', 1)
            ->assertJsonPath('data.items.0.id_porcentaje', 2)
            ->assertJsonPath('data.items.0.id', 2)
            ->assertJsonPath('data.items.0.internal_id', 2)
            ->assertJsonPath('data.items.0.display_id', 'FNDR-002')
            ->assertJsonPath('data.items.0.code', 'FNDR-002')
            ->assertJsonPath('data.items.0.description', 'IVA')
            ->assertJsonPath('data.items.0.percentage', 10)
            ->assertJsonPath('data.items.0.status_label', 'INACTIVO')
            ->assertJsonPath('data.items.0.available_actions.edit', true)
            ->assertJsonPath('data.items.0.available_actions.select', true);

        $this->getJson('/api/v1/calculation-percentages/fndr/context')
            ->assertOk()
            ->assertJsonPath('data.statuses.0.code', 'AC')
            ->assertJsonPath('data.permissions.can_update', true)
            ->assertJsonPath('data.filters.0', 'search')
            ->assertJsonPath('data.endpoints.list', '/api/v1/calculation-percentages/fndr');

        $this->getJson('/api/v1/calculation-percentages/fndr/2')
            ->assertOk()
            ->assertJsonPath('data.calculation_percentage.id_porcentaje', 2)
            ->assertJsonPath('data.calculation_percentage.id', 2)
            ->assertJsonPath('data.calculation_percentage.display_id', 'FNDR-002')
            ->assertJsonPath('data.calculation_percentage.codigo', 'FNDR-002')
            ->assertJsonPath('data.calculation_percentage.code', 'FNDR-002');

        $this->postJson('/api/v1/calculation-percentages/fndr', [
            'code' => 'FNDR-003',
            'description' => 'HERRAMIENTAS MENORES',
            'percentage' => 5,
            'observation' => 'Obs 3',
            'status' => 'ACTIVO',
        ])->assertCreated()
            ->assertJsonPath('data.calculation_percentage.codigo', 'FNDR-003')
            ->assertJsonPath('data.calculation_percentage.description', 'HERRAMIENTAS MENORES')
            ->assertJsonPath('data.calculation_percentage.status_label', 'ACTIVO');

        $this->putJson('/api/v1/calculation-percentages/fndr/2', [
            'code' => 'FNDR-002A',
            'description' => 'IVA ACTUALIZADO',
            'percentage' => 15.5,
            'observation' => 'Obs editada',
            'status' => 'ACTIVO',
        ])->assertOk()
            ->assertJsonPath('data.calculation_percentage.codigo', 'FNDR-002A')
            ->assertJsonPath('data.calculation_percentage.descripcion', 'IVA ACTUALIZADO')
            ->assertJsonPath('data.calculation_percentage.porcentaje', 15.5)
            ->assertJsonPath('data.calculation_percentage.status_label', 'ACTIVO');
    }

    public function test_fndr_calculation_percentage_code_and_description_must_be_unique_within_fndr_table(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        FndrCalculationPercentage::query()->create([
            'id_porcentaje' => 1,
            'codigo' => 'FNDR-001',
            'descripcion' => 'CARGAS SOCIALES',
            'porcentaje' => 50,
            'observacion' => null,
            'estado' => 'AC',
            'usuario' => 1,
        ]);

        $this->postJson('/api/v1/calculation-percentages/fndr', [
            'codigo' => 'FNDR-001',
            'descripcion' => 'OTRO',
            'porcentaje' => 10,
            'observacion' => null,
            'estado' => 'AC',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.codigo.0', 'Ya existe un porcentaje de calculo FNDR con el mismo codigo.');

        $this->postJson('/api/v1/calculation-percentages/fndr', [
            'codigo' => 'FNDR-002',
            'descripcion' => 'CARGAS SOCIALES',
            'porcentaje' => 10,
            'observacion' => null,
            'estado' => 'AC',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.descripcion.0', 'Ya existe un porcentaje de calculo FNDR con la misma descripcion.');
    }

    public function test_fndr_calculation_percentage_update_only_checks_duplicates_when_values_change(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        FndrCalculationPercentage::query()->create([
            'id_porcentaje' => 1,
            'codigo' => 'FNDR-001',
            'descripcion' => 'CARGAS SOCIALES',
            'porcentaje' => 50,
            'observacion' => null,
            'estado' => 'AC',
            'usuario' => 1,
        ]);

        FndrCalculationPercentage::query()->create([
            'id_porcentaje' => 2,
            'codigo' => 'FNDR-002',
            'descripcion' => 'IVA',
            'porcentaje' => 10,
            'observacion' => null,
            'estado' => 'AC',
            'usuario' => 1,
        ]);

        $this->putJson('/api/v1/calculation-percentages/fndr/1', [
            'codigo' => 'FNDR-001',
            'descripcion' => 'CARGAS SOCIALES',
            'porcentaje' => 51,
            'observacion' => 'Sin duplicado',
            'estado' => 'DC',
        ])->assertOk()
            ->assertJsonPath('data.calculation_percentage.estado', 'DC');

        $this->putJson('/api/v1/calculation-percentages/fndr/1', [
            'codigo' => 'FNDR-002',
            'descripcion' => 'CARGAS SOCIALES',
            'porcentaje' => 51,
            'observacion' => 'Dup codigo',
            'estado' => 'AC',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.codigo.0', 'Ya existe un porcentaje de calculo FNDR con el mismo codigo.');

        $this->putJson('/api/v1/calculation-percentages/fndr/1', [
            'codigo' => 'FNDR-001',
            'descripcion' => 'IVA',
            'porcentaje' => 51,
            'observacion' => 'Dup descripcion',
            'estado' => 'AC',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.descripcion.0', 'Ya existe un porcentaje de calculo FNDR con la misma descripcion.');
    }

    public function test_fndr_calculation_percentage_endpoints_require_authentication(): void
    {
        FndrCalculationPercentage::query()->create([
            'id_porcentaje' => 1,
            'codigo' => 'FNDR-001',
            'descripcion' => 'CARGAS SOCIALES',
            'porcentaje' => 50,
            'observacion' => null,
            'estado' => 'AC',
            'usuario' => 1,
        ]);

        $this->getJson('/api/v1/calculation-percentages/fndr')->assertUnauthorized();
        $this->getJson('/api/v1/calculation-percentages/fndr/context')->assertUnauthorized();
        $this->postJson('/api/v1/calculation-percentages/fndr', [])->assertUnauthorized();
        $this->getJson('/api/v1/calculation-percentages/fndr/1')->assertUnauthorized();
        $this->putJson('/api/v1/calculation-percentages/fndr/1', [])->assertUnauthorized();
    }

    public function test_non_admin_cannot_manage_fndr_calculation_percentages(): void
    {
        $this->createLegacyAuthUser();

        FndrCalculationPercentage::query()->create([
            'id_porcentaje' => 1,
            'codigo' => 'FNDR-001',
            'descripcion' => 'CARGAS SOCIALES',
            'porcentaje' => 50,
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

        $this->getJson('/api/v1/calculation-percentages/fndr')->assertForbidden();
        $this->getJson('/api/v1/calculation-percentages/fndr/context')->assertForbidden();
        $this->postJson('/api/v1/calculation-percentages/fndr', [
            'codigo' => 'FNDR-002',
            'descripcion' => 'IVA',
            'porcentaje' => 10,
            'observacion' => null,
            'estado' => 'AC',
        ])->assertForbidden();
        $this->getJson('/api/v1/calculation-percentages/fndr/1')->assertForbidden();
        $this->putJson('/api/v1/calculation-percentages/fndr/1', [
            'codigo' => 'FNDR-001',
            'descripcion' => 'Editado',
            'porcentaje' => 50,
            'observacion' => null,
            'estado' => 'AC',
        ])->assertForbidden();
    }
}

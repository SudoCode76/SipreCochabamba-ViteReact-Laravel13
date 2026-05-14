<?php

namespace Tests\Feature\Item;

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
use Tests\TestCase;

class UpreItemApiTest extends TestCase
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

    public function test_user_with_upre_view_permission_can_get_context_with_functional_permissions(): void
    {
        $this->createGroup();
        $this->createSubgroup();
        $this->createUnitMeasure();

        $user = $this->createUpreUserWithPermissions(['UPRE']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/items/upre/context');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.permissions.can_view', true)
            ->assertJsonPath('data.permissions.can_create', false)
            ->assertJsonPath('data.permissions.can_view_price_analysis', false)
            ->assertJsonPath('data.permissions.can_recalculate', false)
            ->assertJsonPath('data.meta.screen', 'items/upre')
            ->assertJsonPath('data.meta.mode', 'upre');
    }

    public function test_upre_list_returns_paginated_filtered_items(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createUnitMeasure();
        $this->createGroup();
        $this->createSubgroup();
        $this->seedUprePercentages();

        $this->createInput(['id_insumo' => 1, 'tipo' => 1, 'precio' => 10, 'descripcion' => 'Material 1']);
        $this->createInput(['id_insumo' => 2, 'tipo' => 2, 'precio' => 5, 'descripcion' => 'Mano 1']);
        $this->createInput(['id_insumo' => 3, 'tipo' => 3, 'precio' => 4, 'descripcion' => 'Herramienta 1']);

        $this->createItemRecord();
        $this->createItemInputRecord(['id_item_insumo' => 1, 'id_item' => 1, 'id_insumo' => 1, 'cantidad' => 2]);
        $this->createItemInputRecord(['id_item_insumo' => 2, 'id_item' => 1, 'id_insumo' => 2, 'cantidad' => 3]);
        $this->createItemInputRecord(['id_item_insumo' => 3, 'id_item' => 1, 'id_insumo' => 3, 'cantidad' => 1]);
        $this->createInputLog(['id_log' => 1, 'id_insumo' => 2, 'precio' => 100, 'tipo' => 2, 'descripcion' => 'Mano 1', 'fecha' => '2026-05-01']);
        $this->createInputLog(['id_log' => 2, 'id_insumo' => 3, 'precio' => 200, 'tipo' => 3, 'descripcion' => 'Herramienta 1', 'fecha' => '2026-05-01']);

        $response = $this->getJson('/api/v1/items/upre?search=ITEM&group_id=1&subgroup_id=1&status=AC&per_page=10');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.id_item', 1)
            ->assertJsonPath('data.items.0.calculated_price', 56.1033);
    }

    public function test_upre_price_analysis_returns_current_breakdown(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createUnitMeasure();
        $this->createGroup();
        $this->createSubgroup();
        $this->seedUprePercentages();

        $this->createInput(['id_insumo' => 1, 'tipo' => 1, 'precio' => 10, 'descripcion' => 'Material 1']);
        $this->createInput(['id_insumo' => 2, 'tipo' => 2, 'precio' => 5, 'descripcion' => 'Mano 1']);
        $this->createInput(['id_insumo' => 3, 'tipo' => 3, 'precio' => 4, 'descripcion' => 'Herramienta 1']);

        $this->createItemRecord();
        $this->createItemInputRecord(['id_item_insumo' => 1, 'id_item' => 1, 'id_insumo' => 1, 'cantidad' => 2]);
        $this->createItemInputRecord(['id_item_insumo' => 2, 'id_item' => 1, 'id_insumo' => 2, 'cantidad' => 3]);
        $this->createItemInputRecord(['id_item_insumo' => 3, 'id_item' => 1, 'id_insumo' => 3, 'cantidad' => 1]);
        $this->createInputLog(['id_log' => 1, 'id_insumo' => 2, 'precio' => 100, 'tipo' => 2, 'descripcion' => 'Mano 1', 'fecha' => '2026-05-01']);
        $this->createInputLog(['id_log' => 2, 'id_insumo' => 3, 'precio' => 200, 'tipo' => 3, 'descripcion' => 'Herramienta 1', 'fecha' => '2026-05-01']);

        $response = $this->getJson('/api/v1/items/1/price-analysis?mode=upre');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.meta.mode', 'upre')
            ->assertJsonPath('data.totals.materials_total', 20)
            ->assertJsonPath('data.totals.labor_total', 22.4133)
            ->assertJsonPath('data.totals.tools_total', 5.1207)
            ->assertJsonPath('data.totals.total_price', 56.1033);
    }

    public function test_upre_price_recalculation_uses_latest_logs_until_date(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createUnitMeasure();
        $this->createGroup();
        $this->createSubgroup();
        $this->seedUprePercentages();

        $this->createInput(['id_insumo' => 1, 'tipo' => 1, 'precio' => 10, 'descripcion' => 'Material 1']);
        $this->createInput(['id_insumo' => 2, 'tipo' => 2, 'precio' => 5, 'descripcion' => 'Mano 1']);
        $this->createInput(['id_insumo' => 3, 'tipo' => 3, 'precio' => 4, 'descripcion' => 'Herramienta 1']);

        $this->createItemRecord();
        $this->createItemInputRecord(['id_item_insumo' => 1, 'id_item' => 1, 'id_insumo' => 1, 'cantidad' => 2]);
        $this->createItemInputRecord(['id_item_insumo' => 2, 'id_item' => 1, 'id_insumo' => 2, 'cantidad' => 3]);
        $this->createItemInputRecord(['id_item_insumo' => 3, 'id_item' => 1, 'id_insumo' => 3, 'cantidad' => 1]);

        $this->createInputLog(['id_log' => 1, 'id_insumo' => 1, 'precio' => 8, 'tipo' => 1, 'descripcion' => 'Material 1', 'fecha' => '2026-04-01']);
        $this->createInputLog(['id_log' => 2, 'id_insumo' => 1, 'precio' => 12, 'tipo' => 1, 'descripcion' => 'Material 1', 'fecha' => '2026-05-01']);
        $this->createInputLog(['id_log' => 3, 'id_insumo' => 2, 'precio' => 4, 'tipo' => 2, 'descripcion' => 'Mano 1', 'fecha' => '2026-04-01']);
        $this->createInputLog(['id_log' => 4, 'id_insumo' => 2, 'precio' => 6, 'tipo' => 2, 'descripcion' => 'Mano 1', 'fecha' => '2026-05-01']);
        $this->createInputLog(['id_log' => 5, 'id_insumo' => 3, 'precio' => 3, 'tipo' => 3, 'descripcion' => 'Herramienta 1', 'fecha' => '2026-04-01']);
        $this->createInputLog(['id_log' => 6, 'id_insumo' => 3, 'precio' => 5, 'tipo' => 3, 'descripcion' => 'Herramienta 1', 'fecha' => '2026-05-01']);

        $response = $this->postJson('/api/v1/items/1/price-recalculation?mode=upre', [
            'fecha' => '2026-04-30',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.meta.mode', 'upre')
            ->assertJsonPath('data.materials.0.log_id', 1)
            ->assertJsonPath('data.labor.0.log_id', 3)
            ->assertJsonPath('data.tools.0.log_id', 5)
            ->assertJsonPath('data.totals.materials_total', 16)
            ->assertJsonPath('data.totals.total_price', 44.6465);
    }

    private function createUpreUserWithPermissions(array $functionNames): User
    {
        $this->createLegacyAuthUser();

        $role = Role::query()->create([
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

        foreach ($functionNames as $index => $functionName) {
            $function = SystemFunction::query()->create([
                'id_funcion' => 200 + $index,
                'nombre_funcion' => $functionName,
                'descripcion' => $functionName,
                'clase' => 'ITEMS',
                'estado' => 'AC',
            ]);

            Permission::query()->create([
                'id_permiso' => 200 + $index,
                'id_rol' => $role->id_rol,
                'nombre_rol' => $role->nombre_rol,
                'id_funcion' => $function->id_funcion,
                'descripcion' => $function->descripcion,
                'estado' => 'AC',
            ]);
        }

        return User::query()->create([
            'id_usuario' => 2,
            'funcionario' => 'Usuario UPRE',
            'ci' => '87654321',
            'username' => 'upre',
            'clave' => Hash::make('secret123'),
            'estado' => 'AC',
            'id_unidad' => $unit->id_unidad,
            'rol' => $role->id_rol,
            'fecha' => now()->toDateString(),
            'subalcaldia' => null,
        ]);
    }
}

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

class FndrItemApiTest extends TestCase
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

    public function test_user_with_fndr_view_permission_can_get_context_with_functional_permissions(): void
    {
        $this->createGroup();
        $this->createSubgroup();
        $this->createUnitMeasure();

        $user = $this->createFndrUserWithPermissions(['FNDR']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/items/fndr/context');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.groups.0.id', 1)
            ->assertJsonPath('data.subgroups_by_group.1.0.id', 1)
            ->assertJsonPath('data.permissions.can_view', true)
            ->assertJsonPath('data.permissions.can_create', false)
            ->assertJsonPath('data.permissions.can_view_price_analysis', false)
            ->assertJsonPath('data.permissions.can_recalculate', false)
            ->assertJsonPath('data.meta.screen', 'items/fndr');
    }

    public function test_fndr_list_returns_paginated_filtered_items(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createUnitMeasure();
        $this->createGroup();
        $this->createSubgroup();
        $this->createGroup(['id_grupo' => 2, 'nombre_grupo' => 'OBRA GRUESA']);
        $this->createSubgroup(['id_subgrupo' => 2, 'id_grupo' => 2, 'descripcion' => 'ESTRUCTURAS', 'codigo' => 'EST']);
        $this->seedFndrPercentages();

        $this->createInput(['id_insumo' => 1, 'tipo' => 1, 'precio' => 10, 'descripcion' => 'Material 1']);
        $this->createInput(['id_insumo' => 2, 'tipo' => 2, 'precio' => 5, 'descripcion' => 'Mano 1']);
        $this->createInput(['id_insumo' => 3, 'tipo' => 3, 'precio' => 4, 'descripcion' => 'Herramienta 1']);

        $this->createItemRecord();
        $this->createItemRecord(['id_item' => 2, 'item' => 'SEGUNDO ITEM', 'grupo' => 2, 'subgrupo' => 2, 'estado' => 'DC']);

        $this->createItemInputRecord(['id_item_insumo' => 1, 'id_item' => 1, 'id_insumo' => 1, 'cantidad' => 2]);
        $this->createItemInputRecord(['id_item_insumo' => 2, 'id_item' => 1, 'id_insumo' => 2, 'cantidad' => 3]);
        $this->createItemInputRecord(['id_item_insumo' => 3, 'id_item' => 1, 'id_insumo' => 3, 'cantidad' => 1]);

        $this->createItemInputRecord(['id_item_insumo' => 4, 'id_item' => 2, 'id_insumo' => 1, 'cantidad' => 1]);
        $this->createItemInputRecord(['id_item_insumo' => 5, 'id_item' => 2, 'id_insumo' => 2, 'cantidad' => 1]);
        $this->createItemInputRecord(['id_item_insumo' => 6, 'id_item' => 2, 'id_insumo' => 3, 'cantidad' => 1]);
        $this->createInputLog(['id_log' => 1, 'id_insumo' => 2, 'precio' => 100, 'tipo' => 2, 'descripcion' => 'Mano 1', 'fecha' => '2026-05-01']);
        $this->createInputLog(['id_log' => 2, 'id_insumo' => 3, 'precio' => 200, 'tipo' => 3, 'descripcion' => 'Herramienta 1', 'fecha' => '2026-05-01']);

        $response = $this->getJson('/api/v1/items/fndr?search=ITEM&group_id=1&subgroup_id=1&status=AC&per_page=10');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.id_item', 1)
            ->assertJsonPath('data.items.0.status_label', 'HABILITADO')
            ->assertJsonPath('data.items.0.available_actions.edit', true)
            ->assertJsonPath('data.items.0.available_actions.materials', true)
            ->assertJsonPath('data.items.0.available_actions.breakdown_recalculation', true)
            ->assertJsonPath('data.items.0.group.id', 1)
            ->assertJsonPath('data.items.0.subgroup.id', 1)
            ->assertJsonPath('data.items.0.unit_measure.id', 1)
            ->assertJsonPath('data.items.0.calculated_price', 62.3)
            ->assertJsonPath('data.items.0.calculated_price_label', '62,30')
            ->assertJsonPath('data.items.0.precio_calculado', '62,30');

        $this->getJson('/api/v1/items/fndr?search=SEGUNDO&per_page=10')
            ->assertOk()
            ->assertJsonPath('data.items.0.id_item', 2)
            ->assertJsonPath('data.items.0.status', 'DC')
            ->assertJsonPath('data.items.0.status_label', 'INHABILITADO')
            ->assertJsonPath('data.items.0.available_actions.edit', true)
            ->assertJsonPath('data.items.0.available_actions.materials', false)
            ->assertJsonPath('data.items.0.available_actions.files', false)
            ->assertJsonPath('data.items.0.available_actions.breakdown_recalculation', false);
    }

    public function test_fndr_list_searches_items_by_independent_words(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createUnitMeasure();
        $this->createGroup();
        $this->createSubgroup();
        $this->seedFndrPercentages();

        $this->createItemRecord([
            'id_item' => 1,
            'item' => 'DEMO ITEM CR',
        ]);
        $this->createItemRecord([
            'id_item' => 2,
            'item' => 'OTRO TRABAJO',
        ]);

        $this->getJson('/api/v1/items/fndr?search=demo%20item&per_page=10')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.id_item', 1);

        $this->getJson('/api/v1/items/fndr?search=demo%20cr&per_page=10')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.id_item', 1);

        $this->getJson('/api/v1/items/fndr?search=demo%20%20%20cr&per_page=10')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.id_item', 1);

        $this->getJson('/api/v1/items/fndr?search=demo%20xyz&per_page=10')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 0);
    }

    public function test_fndr_list_can_filter_duplicate_items_by_normalized_name(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createUnitMeasure();
        $this->createGroup();
        $this->createSubgroup();
        $this->seedFndrPercentages();

        $this->createItemRecord([
            'id_item' => 1,
            'item' => 'Cubierta Panel Tipo Sandwich Inc/Est',
            'estado' => 'AC',
        ]);
        $this->createItemRecord([
            'id_item' => 2,
            'item' => ' cubierta   panel tipo sandwich inc/est ',
            'estado' => 'AC',
        ]);
        $this->createItemRecord([
            'id_item' => 3,
            'item' => 'Cubierta Calamina',
            'estado' => 'AC',
        ]);

        $this->getJson('/api/v1/items/fndr?duplicates=1&per_page=10')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 2)
            ->assertJsonPath('data.items.0.id_item', 1)
            ->assertJsonPath('data.items.0.is_duplicate', true)
            ->assertJsonPath('data.items.0.duplicate_count', 2)
            ->assertJsonPath('data.items.0.duplicate_key', 'cubierta panel tipo sandwich inc/est')
            ->assertJsonPath('data.items.1.id_item', 2);

        $this->getJson('/api/v1/items/fndr?duplicates=1&search=sandwich&status=AC&per_page=10')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 2);

        $this->getJson('/api/v1/items/fndr?duplicates=1&search=calamina&per_page=10')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 0);
    }

    public function test_fndr_list_calculated_price_matches_legacy_list_rules_with_log_join_multiplication(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createUnitMeasure();
        $this->createGroup();
        $this->createSubgroup();
        $this->seedGeneralPercentages();
        $this->seedFndrPercentages();

        $this->createInput(['id_insumo' => 1, 'tipo' => 1, 'precio' => 10, 'descripcion' => 'Material 1', 'estado' => 'DC']);
        $this->createInput(['id_insumo' => 2, 'tipo' => 2, 'precio' => 5, 'descripcion' => 'Mano 1', 'estado' => 'DC']);
        $this->createInput(['id_insumo' => 3, 'tipo' => 3, 'precio' => 4, 'descripcion' => 'Herramienta 1', 'estado' => 'DC']);

        $this->createItemRecord(['precio' => 999]);
        $this->createItemInputRecord(['id_item_insumo' => 1, 'id_item' => 1, 'id_insumo' => 1, 'cantidad' => 2]);
        $this->createItemInputRecord(['id_item_insumo' => 2, 'id_item' => 1, 'id_insumo' => 2, 'cantidad' => 3]);
        $this->createItemInputRecord(['id_item_insumo' => 3, 'id_item' => 1, 'id_insumo' => 3, 'cantidad' => 1]);

        $this->createInputLog(['id_log' => 1, 'id_insumo' => 2, 'precio' => 100, 'tipo' => 2, 'descripcion' => 'Mano 1', 'fecha' => '2026-04-01']);
        $this->createInputLog(['id_log' => 2, 'id_insumo' => 2, 'precio' => 200, 'tipo' => 2, 'descripcion' => 'Mano 1', 'fecha' => '2026-05-01']);
        $this->createInputLog(['id_log' => 3, 'id_insumo' => 3, 'precio' => 300, 'tipo' => 3, 'descripcion' => 'Herramienta 1', 'fecha' => '2026-04-01']);
        $this->createInputLog(['id_log' => 4, 'id_insumo' => 3, 'precio' => 400, 'tipo' => 3, 'descripcion' => 'Herramienta 1', 'fecha' => '2026-05-01']);

        $this->getJson('/api/v1/items/fndr?per_page=10')
            ->assertOk()
            ->assertJsonPath('data.items.0.calculated_price', 99.67)
            ->assertJsonPath('data.items.0.calculated_price_label', '99,67')
            ->assertJsonPath('data.items.0.precio_calculado', '99,67');
    }

    public function test_fndr_list_calculated_price_label_uses_legacy_thousands_format(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createUnitMeasure();
        $this->createGroup();
        $this->createSubgroup();
        $this->seedFndrPercentages();

        $this->createInput(['id_insumo' => 1, 'tipo' => 1, 'precio' => 1000, 'descripcion' => 'Material 1']);
        $this->createInput(['id_insumo' => 2, 'tipo' => 2, 'precio' => 500, 'descripcion' => 'Mano 1']);
        $this->createInput(['id_insumo' => 3, 'tipo' => 3, 'precio' => 400, 'descripcion' => 'Herramienta 1']);

        $this->createItemRecord();
        $this->createItemInputRecord(['id_item_insumo' => 1, 'id_item' => 1, 'id_insumo' => 1, 'cantidad' => 20]);
        $this->createItemInputRecord(['id_item_insumo' => 2, 'id_item' => 1, 'id_insumo' => 2, 'cantidad' => 30]);
        $this->createItemInputRecord(['id_item_insumo' => 3, 'id_item' => 1, 'id_insumo' => 3, 'cantidad' => 10]);
        $this->createInputLog(['id_log' => 1, 'id_insumo' => 2, 'precio' => 100, 'tipo' => 2, 'descripcion' => 'Mano 1', 'fecha' => '2026-05-01']);
        $this->createInputLog(['id_log' => 2, 'id_insumo' => 3, 'precio' => 200, 'tipo' => 3, 'descripcion' => 'Herramienta 1', 'fecha' => '2026-05-01']);

        $this->getJson('/api/v1/items/fndr?per_page=10')
            ->assertOk()
            ->assertJsonPath('data.items.0.calculated_price', 62299.42)
            ->assertJsonPath('data.items.0.calculated_price_label', '62.299,42')
            ->assertJsonPath('data.items.0.precio_calculado', '62.299,42');
    }

    public function test_fndr_list_returns_legacy_message_when_required_percentages_are_missing(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createUnitMeasure();
        $this->createGroup();
        $this->createSubgroup();
        $this->createInput(['id_insumo' => 1, 'tipo' => 1, 'precio' => 10, 'descripcion' => 'Material 1']);
        $this->createItemRecord();
        $this->createItemInputRecord(['id_item_insumo' => 1, 'id_item' => 1, 'id_insumo' => 1, 'cantidad' => 2]);

        $message = 'uno de los parametros de porcentaje no esta configurado adecuadamente';

        $this->getJson('/api/v1/items/fndr?per_page=10')
            ->assertOk()
            ->assertJsonPath('data.items.0.calculated_price', null)
            ->assertJsonPath('data.items.0.calculated_price_label', $message)
            ->assertJsonPath('data.items.0.precio_calculado', $message);
    }

    public function test_store_item_enforces_group_subgroup_item_duplicate_rule(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createUnitMeasure();
        $this->createGroup();
        $this->createSubgroup();
        $this->createSubgroup(['id_subgrupo' => 2, 'descripcion' => 'ESTRUCTURAS', 'codigo' => 'EST']);
        $this->createItemRecord(['item' => 'ITEM DUPLICADO']);

        $this->postJson('/api/v1/items', [
            'group_id' => 1,
            'subgroup_id' => 1,
            'item' => 'item duplicado',
            'unit_measure_id' => 1,
            'status' => 'AC',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['item']);

        $this->postJson('/api/v1/items', [
            'group_id' => 1,
            'subgroup_id' => 2,
            'item' => 'item duplicado',
            'unit_measure_id' => 1,
            'status' => 'AC',
        ])->assertCreated()
            ->assertJsonPath('data.item.name', 'ITEM DUPLICADO')
            ->assertJsonPath('data.item.subgroup.id', 2);
    }

    public function test_price_analysis_returns_current_fndr_breakdown(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createUnitMeasure();
        $this->createGroup();
        $this->createSubgroup();
        $this->seedFndrPercentages();

        $this->createInput(['id_insumo' => 1, 'tipo' => 1, 'precio' => 10, 'descripcion' => 'Material 1']);
        $this->createInput(['id_insumo' => 2, 'tipo' => 2, 'precio' => 5, 'descripcion' => 'Mano 1']);
        $this->createInput(['id_insumo' => 3, 'tipo' => 3, 'precio' => 4, 'descripcion' => 'Herramienta 1']);

        $this->createItemRecord();
        $this->createItemInputRecord(['id_item_insumo' => 1, 'id_item' => 1, 'id_insumo' => 1, 'cantidad' => 2]);
        $this->createItemInputRecord(['id_item_insumo' => 2, 'id_item' => 1, 'id_insumo' => 2, 'cantidad' => 3]);
        $this->createItemInputRecord(['id_item_insumo' => 3, 'id_item' => 1, 'id_insumo' => 3, 'cantidad' => 1]);
        $this->createInputLog(['id_log' => 1, 'id_insumo' => 2, 'precio' => 100, 'tipo' => 2, 'descripcion' => 'Mano 1', 'fecha' => '2026-05-01']);
        $this->createInputLog(['id_log' => 2, 'id_insumo' => 3, 'precio' => 200, 'tipo' => 3, 'descripcion' => 'Herramienta 1', 'fecha' => '2026-05-01']);

        $response = $this->getJson('/api/v1/items/1/price-analysis?mode=fndr');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.item.id_item', 1)
            ->assertJsonCount(1, 'data.materials')
            ->assertJsonCount(1, 'data.labor')
            ->assertJsonCount(1, 'data.tools')
            ->assertJsonPath('data.totals.materials_total', 20)
            ->assertJsonPath('data.totals.labor_total', 24.75)
            ->assertJsonPath('data.totals.tools_total', 5.2375)
            ->assertJsonPath('data.totals.total_price', 62.2994);
    }

    public function test_price_recalculation_uses_latest_logs_until_date(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createUnitMeasure();
        $this->createGroup();
        $this->createSubgroup();
        $this->seedFndrPercentages();

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

        $response = $this->postJson('/api/v1/items/1/price-recalculation?mode=fndr', [
            'fecha' => '2026-04-30',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.meta.reference_date', '2026-04-30')
            ->assertJsonPath('data.materials.0.log_id', 1)
            ->assertJsonPath('data.labor.0.log_id', 3)
            ->assertJsonPath('data.tools.0.log_id', 5)
            ->assertJsonPath('data.totals.materials_total', 16)
            ->assertJsonPath('data.totals.total_price', 49.5903);
    }

    public function test_subgroups_endpoint_filters_by_group(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createGroup();
        $this->createGroup(['id_grupo' => 2, 'nombre_grupo' => 'OBRA GRUESA']);
        $this->createSubgroup();
        $this->createSubgroup(['id_subgrupo' => 2, 'id_grupo' => 2, 'descripcion' => 'ESTRUCTURAS', 'codigo' => 'EST']);

        $this->getJson('/api/v1/subgroups?group_id=2')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', 2)
            ->assertJsonPath('data.items.0.group_id', 2);
    }

    private function createFndrUserWithPermissions(array $functionNames): User
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
                'id_funcion' => 100 + $index,
                'nombre_funcion' => $functionName,
                'descripcion' => $functionName,
                'clase' => 'ITEMS',
                'estado' => 'AC',
            ]);

            Permission::query()->create([
                'id_permiso' => 100 + $index,
                'id_rol' => $role->id_rol,
                'nombre_rol' => $role->nombre_rol,
                'id_funcion' => $function->id_funcion,
                'descripcion' => $function->descripcion,
                'estado' => 'AC',
            ]);
        }

        return User::query()->create([
            'id_usuario' => 2,
            'funcionario' => 'Usuario FNDR',
            'ci' => '87654321',
            'username' => 'fndr',
            'clave' => Hash::make('secret123'),
            'estado' => 'AC',
            'id_unidad' => $unit->id_unidad,
            'rol' => $role->id_rol,
            'fecha' => now()->toDateString(),
            'subalcaldia' => null,
        ]);
    }
}

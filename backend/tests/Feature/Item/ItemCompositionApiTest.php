<?php

namespace Tests\Feature\Item;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithLegacyAuth;
use Tests\Concerns\InteractsWithLegacyInputs;
use Tests\Concerns\InteractsWithLegacyItems;
use Tests\TestCase;

class ItemCompositionApiTest extends TestCase
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
        $this->createUnitMeasure();
        $this->createInputType();
        $this->createGroup();
        $this->createSubgroup();
        $this->seedGeneralPercentages();
    }

    public function test_can_get_item_composition_context_with_available_actions(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createItemRecord(['estado' => 'AC']);

        $this->getJson('/api/v1/items/1/composition/context')
            ->assertOk()
            ->assertJsonPath('data.item.id_item', 1)
            ->assertJsonPath('data.item.status_label', 'HABILITADO')
            ->assertJsonPath('data.available_actions.materials', true)
            ->assertJsonPath('data.meta.can_recalculate', true);

        $this->createItemRecord(['id_item' => 2, 'item' => 'ITEM INACTIVO', 'estado' => 'DC']);

        $this->getJson('/api/v1/items/2/composition/context')
            ->assertOk()
            ->assertJsonPath('data.item.status_label', 'INHABILITADO')
            ->assertJsonPath('data.available_actions.edit', true)
            ->assertJsonPath('data.available_actions.materials', false)
            ->assertJsonPath('data.available_actions.breakdown_recalculation', false);
    }

    public function test_can_manage_material_labor_and_machinery_composition_and_totals(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createInput(['id_insumo' => 1, 'descripcion' => 'Material 1', 'tipo' => 1, 'precio' => 10]);
        $this->createInputType(['id_tipo' => 2, 'descripcion' => 'MANO DE OBRA', 'estado' => 'AC']);
        $this->createInput(['id_insumo' => 2, 'descripcion' => 'Labor 1', 'tipo' => 2, 'precio' => 5]);
        $this->createInputType(['id_tipo' => 3, 'descripcion' => 'HERRAMIENTA', 'estado' => 'AC']);
        $this->createInput(['id_insumo' => 3, 'descripcion' => 'Tool 1', 'tipo' => 3, 'precio' => 4]);
        $this->createItemRecord();

        $this->postJson('/api/v1/items/1/materials', [
            'id_insumo' => 1,
            'cantidad' => 2,
        ])->assertCreated()
            ->assertJsonPath('data.item_input.id_insumo', 1)
            ->assertJsonPath('data.item_input.parcial', 20)
            ->assertJsonPath('data.totals.block', 20)
            ->assertJsonPath('data.totals.global', 20);

        $this->postJson('/api/v1/items/1/labor', [
            'id_insumo' => 2,
            'cantidad' => 3,
        ])->assertCreated()
            ->assertJsonPath('data.item_input.parcial', 15)
            ->assertJsonPath('data.totals.global', 35);

        $this->postJson('/api/v1/items/1/machinery', [
            'id_insumo' => 3,
            'cantidad' => 1,
        ])->assertCreated()
            ->assertJsonPath('data.item_input.parcial', 4)
            ->assertJsonPath('data.totals.global', 39);

        $this->getJson('/api/v1/items/1/materials')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.descripcion', 'Material 1');

        $this->getJson('/api/v1/items/1/labor/total')
            ->assertOk()
            ->assertJsonPath('data.total', 15);

        $this->getJson('/api/v1/items/1/machinery/total')
            ->assertOk()
            ->assertJsonPath('data.total', 4);

        $this->getJson('/api/v1/items/1/total')
            ->assertOk()
            ->assertJsonPath('data.global', 39);

        $this->getJson('/api/v1/items/1/composition')
            ->assertOk()
            ->assertJsonCount(1, 'data.materials')
            ->assertJsonCount(1, 'data.labor')
            ->assertJsonCount(1, 'data.machinery')
            ->assertJsonPath('data.totals.global', 39);

        $this->putJson('/api/v1/items/1/materials/1', [
            'cantidad' => 5,
        ])->assertOk()
            ->assertJsonPath('data.item_input.parcial', 50)
            ->assertJsonPath('data.totals.block', 50);

        $this->deleteJson('/api/v1/items/1/labor/2')
            ->assertOk()
            ->assertJsonPath('data.totals.block', 0)
            ->assertJsonPath('data.totals.global', 54);
    }

    public function test_cannot_mutate_composition_of_inactive_item(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createInput(['id_insumo' => 1, 'descripcion' => 'Material 1', 'tipo' => 1, 'precio' => 10]);
        $this->createItemRecord(['estado' => 'DC']);

        $this->postJson('/api/v1/items/1/materials', [
            'id_insumo' => 1,
            'cantidad' => 2,
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'El item se encuentra inhabilitado y no permite operaciones de composicion.');
    }

    public function test_input_search_supports_type_filter_and_general_analysis_aliases(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createInput(['id_insumo' => 1, 'descripcion' => 'Material 1', 'tipo' => 1, 'precio' => 10]);
        $this->createInputType(['id_tipo' => 2, 'descripcion' => 'MANO DE OBRA', 'estado' => 'AC']);
        $this->createInput(['id_insumo' => 2, 'descripcion' => 'Labor 1', 'tipo' => 2, 'precio' => 5]);
        $this->createInputType(['id_tipo' => 3, 'descripcion' => 'HERRAMIENTA', 'estado' => 'AC']);
        $this->createInput(['id_insumo' => 3, 'descripcion' => 'Tool 1', 'tipo' => 3, 'precio' => 4]);
        $this->createItemRecord();
        $this->createItemInputRecord(['id_item_insumo' => 1, 'id_item' => 1, 'id_insumo' => 1, 'cantidad' => 2]);
        $this->createItemInputRecord(['id_item_insumo' => 2, 'id_item' => 1, 'id_insumo' => 2, 'cantidad' => 3]);
        $this->createItemInputRecord(['id_item_insumo' => 3, 'id_item' => 1, 'id_insumo' => 3, 'cantidad' => 1]);

        $this->getJson('/api/v1/search/inputs?type=2&search=labor')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', 2)
            ->assertJsonPath('data.items.0.tipo', 2)
            ->assertJsonPath('data.items.0.precio', 5);

        $this->getJson('/api/v1/items/1/price-analysis')
            ->assertOk()
            ->assertJsonPath('data.meta.mode', 'general');

        $this->postJson('/api/v1/items/1/price-recalculation', [
            'fecha' => '2026-04-30',
        ])->assertOk()
            ->assertJsonPath('data.meta.mode', 'general');

        $this->postJson('/api/v1/items/1/breakdowns/recalculate')
            ->assertOk()
            ->assertJsonPath('data.meta.mode', 'general');
    }
}

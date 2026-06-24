<?php

namespace Tests\Feature\Item;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
        $this->createItemRecord(['fecha_item' => '2025-01-01']);

        $this->postJson('/api/v1/items/1/materials', [
            'id_insumo' => 1,
            'cantidad' => 2,
        ])->assertCreated()
            ->assertJsonPath('data.item_input.id_insumo', 1)
            ->assertJsonPath('data.item_input.parcial', 20)
            ->assertJsonPath('data.totals.block', 20)
            ->assertJsonPath('data.totals.global', 20);

        $this->assertSame(
            Carbon::today()->toDateString(),
            Carbon::parse(DB::table('item')->where('id_item', 1)->value('fecha_item'))->toDateString(),
        );

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

        $this->postJson('/api/v1/items/1/materials/sync', [
            'items' => [
                ['id_insumo' => 1, 'cantidad' => 2],
            ],
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'El item se encuentra inhabilitado y no permite operaciones de composicion.');
    }

    public function test_can_sync_materials_as_legacy_batch_flow(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createInput(['id_insumo' => 1, 'descripcion' => 'Material 1', 'tipo' => 1, 'precio' => 10]);
        $this->createInput(['id_insumo' => 2, 'descripcion' => 'Material 2', 'tipo' => 1, 'precio' => 5]);
        $this->createInput(['id_insumo' => 3, 'descripcion' => 'Material 3', 'tipo' => 1, 'precio' => 2.5]);
        $this->createInputType(['id_tipo' => 2, 'descripcion' => 'MANO DE OBRA', 'estado' => 'AC']);
        $this->createInput(['id_insumo' => 4, 'descripcion' => 'Labor 1', 'tipo' => 2, 'precio' => 7]);
        $this->createItemRecord(['precio' => 999]);
        $this->createItemInputRecord(['id_item_insumo' => 1, 'id_item' => 1, 'id_insumo' => 1, 'cantidad' => 1, 'estado' => 'AC']);
        $this->createItemInputRecord(['id_item_insumo' => 2, 'id_item' => 1, 'id_insumo' => 2, 'cantidad' => 3, 'estado' => 'AC']);

        $this->postJson('/api/v1/items/1/materials/sync', [
            'items' => [
                ['id_insumo' => 1, 'cantidad' => 4],
                ['id_insumo' => 3, 'cantidad' => 2],
            ],
            'deleted_input_ids' => [2],
        ])->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.totals.block', 45)
            ->assertJsonPath('data.item.price', 45);

        $this->assertDatabaseHas('item_insumo', [
            'id_item' => 1,
            'id_insumo' => 1,
            'cantidad' => 4,
            'estado' => 'AC',
        ]);
        $this->assertDatabaseHas('item_insumo', [
            'id_item' => 1,
            'id_insumo' => 2,
            'estado' => 'DC',
        ]);
        $this->assertDatabaseHas('item_insumo', [
            'id_item' => 1,
            'id_insumo' => 3,
            'cantidad' => 2,
            'estado' => 'AC',
        ]);
        $this->assertDatabaseHas('item', [
            'id_item' => 1,
            'precio' => 45,
        ]);

        $this->postJson('/api/v1/items/1/materials/sync', [
            'items' => [
                ['id_insumo' => 4, 'cantidad' => 1],
            ],
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'El insumo seleccionado no corresponde al bloque solicitado.');
    }

    public function test_can_sync_empty_materials_and_clear_item_price(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createInput(['id_insumo' => 1, 'descripcion' => 'Material 1', 'tipo' => 1, 'precio' => 10]);
        $this->createInput(['id_insumo' => 2, 'descripcion' => 'Material 2', 'tipo' => 1, 'precio' => 5]);
        $this->createItemRecord(['precio' => 25]);
        $this->createItemInputRecord(['id_item_insumo' => 1, 'id_item' => 1, 'id_insumo' => 1, 'cantidad' => 1, 'estado' => 'AC']);
        $this->createItemInputRecord(['id_item_insumo' => 2, 'id_item' => 1, 'id_insumo' => 2, 'cantidad' => 3, 'estado' => 'AC']);

        $this->postJson('/api/v1/items/1/materials/sync', [
            'items' => [],
            'deleted_input_ids' => [1, 2],
        ])->assertOk()
            ->assertJsonCount(0, 'data.items')
            ->assertJsonPath('data.totals.block', 0)
            ->assertJsonPath('data.item.price', 0);

        $this->assertDatabaseHas('item_insumo', [
            'id_item' => 1,
            'id_insumo' => 1,
            'estado' => 'DC',
        ]);
        $this->assertDatabaseHas('item_insumo', [
            'id_item' => 1,
            'id_insumo' => 2,
            'estado' => 'DC',
        ]);
        $this->assertDatabaseHas('item', [
            'id_item' => 1,
            'precio' => 0,
        ]);
    }

    public function test_sync_rejects_invalid_rows_even_when_empty_payloads_are_allowed(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createInput(['id_insumo' => 1, 'descripcion' => 'Material 1', 'tipo' => 1, 'precio' => 10]);
        $this->createItemRecord();

        $this->postJson('/api/v1/items/1/materials/sync', [
            'items' => [
                ['id_insumo' => 1, 'cantidad' => 0],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.cantidad']);

        $this->postJson('/api/v1/items/1/materials/sync', [
            'items' => [
                ['id_insumo' => 999, 'cantidad' => 1],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.id_insumo']);
    }

    public function test_can_sync_labor_as_legacy_batch_flow(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createInputType(['id_tipo' => 2, 'descripcion' => 'MANO DE OBRA', 'estado' => 'AC']);
        $this->createInput(['id_insumo' => 1, 'descripcion' => 'Albanil', 'tipo' => 2, 'precio' => 25.2]);
        $this->createInput(['id_insumo' => 2, 'descripcion' => 'Ayudante', 'tipo' => 2, 'precio' => 18.6]);
        $this->createItemRecord();
        $this->createItemInputRecord(['id_item_insumo' => 1, 'id_insumo' => 1, 'cantidad' => 0.3, 'tipo' => 2]);

        $this->postJson('/api/v1/items/1/labor/sync', [
            'items' => [
                ['id_insumo' => 1, 'cantidad' => 0.3],
                ['id_insumo' => 2, 'cantidad' => 0.4],
            ],
        ])->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.totals.block', 15);
    }

    public function test_can_sync_empty_labor_block(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createInputType(['id_tipo' => 2, 'descripcion' => 'MANO DE OBRA', 'estado' => 'AC']);
        $this->createInput(['id_insumo' => 1, 'descripcion' => 'Albanil', 'tipo' => 2, 'precio' => 25.2]);
        $this->createItemRecord();
        $this->createItemInputRecord(['id_item_insumo' => 1, 'id_insumo' => 1, 'cantidad' => 0.3, 'tipo' => 2]);

        $this->postJson('/api/v1/items/1/labor/sync', [
            'items' => [],
            'deleted_input_ids' => [1],
        ])->assertOk()
            ->assertJsonCount(0, 'data.items')
            ->assertJsonPath('data.totals.block', 0);

        $this->assertDatabaseHas('item_insumo', [
            'id_item' => 1,
            'id_insumo' => 1,
            'estado' => 'DC',
        ]);
    }

    public function test_can_sync_machinery_as_legacy_batch_flow(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createInputType(['id_tipo' => 3, 'descripcion' => 'HERRAMIENTA', 'estado' => 'AC']);
        $this->createInput(['id_insumo' => 1, 'descripcion' => 'Equipo de soldadura', 'tipo' => 3, 'precio' => 50]);
        $this->createInput(['id_insumo' => 2, 'descripcion' => 'Taladro', 'tipo' => 3, 'precio' => 30]);
        $this->createItemRecord();
        $this->createItemInputRecord(['id_item_insumo' => 1, 'id_insumo' => 1, 'cantidad' => 0.5, 'tipo' => 3]);

        $this->postJson('/api/v1/items/1/machinery/sync', [
            'items' => [
                ['id_insumo' => 1, 'cantidad' => 0.5],
                ['id_insumo' => 2, 'cantidad' => 2],
            ],
        ])->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.totals.block', 85);
    }

    public function test_can_sync_empty_machinery_block(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createInputType(['id_tipo' => 3, 'descripcion' => 'HERRAMIENTA', 'estado' => 'AC']);
        $this->createInput(['id_insumo' => 1, 'descripcion' => 'Equipo de soldadura', 'tipo' => 3, 'precio' => 50]);
        $this->createItemRecord();
        $this->createItemInputRecord(['id_item_insumo' => 1, 'id_insumo' => 1, 'cantidad' => 0.5, 'tipo' => 3]);

        $this->postJson('/api/v1/items/1/machinery/sync', [
            'items' => [],
            'deleted_input_ids' => [1],
        ])->assertOk()
            ->assertJsonCount(0, 'data.items')
            ->assertJsonPath('data.totals.block', 0);

        $this->assertDatabaseHas('item_insumo', [
            'id_item' => 1,
            'id_insumo' => 1,
            'estado' => 'DC',
        ]);
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
        $this->createInputLog(['id_log' => 1, 'id_insumo' => 1, 'precio' => 8, 'tipo' => 1, 'descripcion' => 'Material 1', 'fecha' => '2026-04-01']);
        $this->createInputLog(['id_log' => 2, 'id_insumo' => 2, 'precio' => 4, 'tipo' => 2, 'descripcion' => 'Labor 1', 'fecha' => '2026-04-01']);
        $this->createInputLog(['id_log' => 3, 'id_insumo' => 3, 'precio' => 3, 'tipo' => 3, 'descripcion' => 'Tool 1', 'fecha' => '2026-04-01']);

        $this->getJson('/api/v1/search/inputs?type=2&search=labor')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', 2)
            ->assertJsonPath('data.items.0.tipo', 2)
            ->assertJsonPath('data.items.0.precio', 5)
            ->assertJsonPath('data.items.0.unidad', 'pza.')
            ->assertJsonPath('data.items.0.id_unidad', 1);

        $this->getJson('/api/v1/items/1/price-analysis')
            ->assertOk()
            ->assertJsonPath('data.meta.mode', 'general');

        $this->postJson('/api/v1/items/1/price-recalculation', [
            'fecha' => '2026-04-30',
        ])->assertOk()
            ->assertJsonPath('data.meta.mode', 'general');

        $response = $this->postJson('/api/v1/items/1/breakdowns/recalculate', [
            'fecha' => '2026-04-30',
            'tipo_desglose' => 'materiales',
        ]);

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $response->assertHeader('content-disposition', 'inline; filename="desgloce_recalculado.pdf"');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }
}

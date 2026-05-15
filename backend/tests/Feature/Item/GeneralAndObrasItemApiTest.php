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

class GeneralAndObrasItemApiTest extends TestCase
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

    public function test_general_context_and_list_respect_legacy_order(): void
    {
        Sanctum::actingAs($this->createGeneralUserWithPermissions(['INDEX']));

        $this->createUnitMeasure();
        $this->createGroup(['id_grupo' => 1, 'nombre_grupo' => 'B GRUPO']);
        $this->createGroup(['id_grupo' => 2, 'nombre_grupo' => 'A GRUPO']);
        $this->createSubgroup(['id_subgrupo' => 1, 'id_grupo' => 1, 'descripcion' => 'B SUB']);
        $this->createSubgroup(['id_subgrupo' => 2, 'id_grupo' => 1, 'descripcion' => 'A SUB']);
        $this->createSubgroup(['id_subgrupo' => 3, 'id_grupo' => 2, 'descripcion' => 'C SUB']);
        $this->seedGeneralPercentages();

        $this->createInput(['id_insumo' => 1, 'tipo' => 1, 'precio' => 1, 'descripcion' => 'Material']);
        $this->createInput(['id_insumo' => 2, 'tipo' => 2, 'precio' => 1, 'descripcion' => 'Mano']);
        $this->createInput(['id_insumo' => 3, 'tipo' => 3, 'precio' => 1, 'descripcion' => 'Herramienta']);

        $this->createItemRecord(['id_item' => 1, 'item' => 'Z ITEM', 'grupo' => 1, 'subgrupo' => 1]);
        $this->createItemRecord(['id_item' => 2, 'item' => 'A ITEM', 'grupo' => 1, 'subgrupo' => 2]);
        $this->createItemRecord(['id_item' => 3, 'item' => 'M ITEM', 'grupo' => 2, 'subgrupo' => 3]);

        foreach ([1, 2, 3] as $itemId) {
            $this->createItemInputRecord(['id_item_insumo' => ($itemId * 10) + 1, 'id_item' => $itemId, 'id_insumo' => 1, 'cantidad' => 1]);
            $this->createItemInputRecord(['id_item_insumo' => ($itemId * 10) + 2, 'id_item' => $itemId, 'id_insumo' => 2, 'cantidad' => 1]);
            $this->createItemInputRecord(['id_item_insumo' => ($itemId * 10) + 3, 'id_item' => $itemId, 'id_insumo' => 3, 'cantidad' => 1]);
        }

        $this->getJson('/api/v1/items/context')
            ->assertOk()
            ->assertJsonPath('data.meta.screen', 'items')
            ->assertJsonPath('data.meta.mode', 'general');

        $response = $this->getJson('/api/v1/items?per_page=10');

        $response->assertOk()
            ->assertJsonPath('data.items.0.id_item', 3)
            ->assertJsonPath('data.items.0.status_label', 'HABILITADO')
            ->assertJsonPath('data.items.0.available_actions.price_analysis', true)
            ->assertJsonPath('data.items.1.id_item', 2)
            ->assertJsonPath('data.items.1.status_label', 'HABILITADO')
            ->assertJsonPath('data.items.2.id_item', 1);
    }

    public function test_general_price_analysis_and_recalculation_work(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createUnitMeasure();
        $this->createGroup();
        $this->createSubgroup();
        $this->seedGeneralPercentages();

        $this->createInput(['id_insumo' => 1, 'tipo' => 1, 'precio' => 10, 'descripcion' => 'Material 1']);
        $this->createInput(['id_insumo' => 2, 'tipo' => 2, 'precio' => 5, 'descripcion' => 'Mano 1']);
        $this->createInput(['id_insumo' => 3, 'tipo' => 3, 'precio' => 4, 'descripcion' => 'Herramienta 1']);

        $this->createItemRecord();
        $this->createItemInputRecord(['id_item_insumo' => 1, 'id_item' => 1, 'id_insumo' => 1, 'cantidad' => 2]);
        $this->createItemInputRecord(['id_item_insumo' => 2, 'id_item' => 1, 'id_insumo' => 2, 'cantidad' => 3]);
        $this->createItemInputRecord(['id_item_insumo' => 3, 'id_item' => 1, 'id_insumo' => 3, 'cantidad' => 1]);

        $this->getJson('/api/v1/items/1/price-analysis?mode=general')
            ->assertOk()
            ->assertJsonPath('data.meta.mode', 'general')
            ->assertJsonPath('data.totals.total_price', 63.8266);

        $this->createInputLog(['id_log' => 1, 'id_insumo' => 1, 'precio' => 8, 'tipo' => 1, 'descripcion' => 'Material 1', 'fecha' => '2026-04-01']);
        $this->createInputLog(['id_log' => 2, 'id_insumo' => 2, 'precio' => 4, 'tipo' => 2, 'descripcion' => 'Mano 1', 'fecha' => '2026-04-01']);
        $this->createInputLog(['id_log' => 3, 'id_insumo' => 3, 'precio' => 3, 'tipo' => 3, 'descripcion' => 'Herramienta 1', 'fecha' => '2026-04-01']);

        $this->postJson('/api/v1/items/1/price-recalculation?mode=general', [
            'fecha' => '2026-04-30',
        ])->assertOk()
            ->assertJsonPath('data.meta.mode', 'general')
            ->assertJsonPath('data.materials.0.log_id', 1)
            ->assertJsonPath('data.totals.total_price', 50.8185);
    }

    public function test_obras_context_list_analysis_and_recalculation_work(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createUnitMeasure();
        $this->createGroup();
        $this->createSubgroup();
        $this->seedObrasPercentages();

        $this->createInput(['id_insumo' => 1, 'tipo' => 1, 'precio' => 10, 'descripcion' => 'Material 1']);
        $this->createInput(['id_insumo' => 2, 'tipo' => 2, 'precio' => 5, 'descripcion' => 'Mano 1']);
        $this->createInput(['id_insumo' => 3, 'tipo' => 3, 'precio' => 4, 'descripcion' => 'Herramienta 1']);

        $this->createItemRecord();
        $this->createItemInputRecord(['id_item_insumo' => 1, 'id_item' => 1, 'id_insumo' => 1, 'cantidad' => 2]);
        $this->createItemInputRecord(['id_item_insumo' => 2, 'id_item' => 1, 'id_insumo' => 2, 'cantidad' => 3]);
        $this->createItemInputRecord(['id_item_insumo' => 3, 'id_item' => 1, 'id_insumo' => 3, 'cantidad' => 1]);
        $this->createInputLog(['id_log' => 1, 'id_insumo' => 2, 'precio' => 100, 'tipo' => 2, 'descripcion' => 'Mano 1', 'fecha' => '2026-05-01']);
        $this->createInputLog(['id_log' => 2, 'id_insumo' => 3, 'precio' => 200, 'tipo' => 3, 'descripcion' => 'Herramienta 1', 'fecha' => '2026-05-01']);

        $this->getJson('/api/v1/items/obras/context')
            ->assertOk()
            ->assertJsonPath('data.meta.screen', 'items/obras')
            ->assertJsonPath('data.meta.mode', 'obras');

        $this->getJson('/api/v1/items/obras?per_page=10')
            ->assertOk()
            ->assertJsonPath('data.items.0.calculated_price', 39);

        $this->getJson('/api/v1/items/1/price-analysis?mode=obras')
            ->assertOk()
            ->assertJsonPath('data.meta.mode', 'obras')
            ->assertJsonPath('data.totals.materials_total', 20)
            ->assertJsonPath('data.totals.labor_total', 15)
            ->assertJsonPath('data.totals.tools_total', 4)
            ->assertJsonPath('data.totals.total_price', 39);

        $this->createInputLog(['id_log' => 1, 'id_insumo' => 1, 'precio' => 8, 'tipo' => 1, 'descripcion' => 'Material 1', 'fecha' => '2026-04-01']);
        $this->createInputLog(['id_log' => 2, 'id_insumo' => 2, 'precio' => 4, 'tipo' => 2, 'descripcion' => 'Mano 1', 'fecha' => '2026-04-01']);
        $this->createInputLog(['id_log' => 3, 'id_insumo' => 3, 'precio' => 3, 'tipo' => 3, 'descripcion' => 'Herramienta 1', 'fecha' => '2026-04-01']);

        $this->postJson('/api/v1/items/1/price-recalculation?mode=obras', [
            'fecha' => '2026-04-30',
        ])->assertOk()
            ->assertJsonPath('data.meta.mode', 'obras')
            ->assertJsonPath('data.materials.0.log_id', 3)
            ->assertJsonPath('data.totals.total_price', 31);
    }

    private function createGeneralUserWithPermissions(array $functionNames): User
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
                'id_funcion' => 400 + $index,
                'nombre_funcion' => $functionName,
                'descripcion' => $functionName,
                'clase' => 'ITEMS',
                'estado' => 'AC',
            ]);

            Permission::query()->create([
                'id_permiso' => 400 + $index,
                'id_rol' => $role->id_rol,
                'nombre_rol' => $role->nombre_rol,
                'id_funcion' => $function->id_funcion,
                'descripcion' => $function->descripcion,
                'estado' => 'AC',
            ]);
        }

        return User::query()->create([
            'id_usuario' => 2,
            'funcionario' => 'Usuario General',
            'ci' => '87654321',
            'username' => 'general',
            'clave' => Hash::make('secret123'),
            'estado' => 'AC',
            'id_unidad' => $unit->id_unidad,
            'rol' => $role->id_rol,
            'fecha' => now()->toDateString(),
            'subalcaldia' => null,
        ]);
    }
}

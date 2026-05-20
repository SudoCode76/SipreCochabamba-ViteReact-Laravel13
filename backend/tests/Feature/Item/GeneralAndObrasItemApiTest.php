<?php

namespace Tests\Feature\Item;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemFunction;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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

    public function test_general_list_can_be_sorted_by_recent_and_oldest_dates(): void
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

        $this->createItemRecord(['id_item' => 1, 'item' => 'Z ITEM', 'grupo' => 1, 'subgrupo' => 1, 'fecha_item' => '2026-01-10']);
        $this->createItemRecord(['id_item' => 2, 'item' => 'A ITEM', 'grupo' => 1, 'subgrupo' => 2, 'fecha_item' => '2026-02-10']);
        $this->createItemRecord(['id_item' => 3, 'item' => 'M ITEM', 'grupo' => 2, 'subgrupo' => 3, 'fecha_item' => '2026-03-10']);

        foreach ([1, 2, 3] as $itemId) {
            $this->createItemInputRecord(['id_item_insumo' => ($itemId * 10) + 1, 'id_item' => $itemId, 'id_insumo' => 1, 'cantidad' => 1]);
            $this->createItemInputRecord(['id_item_insumo' => ($itemId * 10) + 2, 'id_item' => $itemId, 'id_insumo' => 2, 'cantidad' => 1]);
            $this->createItemInputRecord(['id_item_insumo' => ($itemId * 10) + 3, 'id_item' => $itemId, 'id_insumo' => 3, 'cantidad' => 1]);
        }

        $this->getJson('/api/v1/items?per_page=10&order=recent')
            ->assertOk()
            ->assertJsonPath('data.items.0.id_item', 3)
            ->assertJsonPath('data.items.1.id_item', 2)
            ->assertJsonPath('data.items.2.id_item', 1);

        $this->getJson('/api/v1/items?per_page=10&order=oldest')
            ->assertOk()
            ->assertJsonPath('data.items.0.id_item', 1)
            ->assertJsonPath('data.items.1.id_item', 2)
            ->assertJsonPath('data.items.2.id_item', 3);
    }

    public function test_general_list_calculated_price_matches_legacy_pca_rules(): void
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

        $this->getJson('/api/v1/items?per_page=10')
            ->assertOk()
            ->assertJsonPath('data.items.0.calculated_price', 103.39)
            ->assertJsonPath('data.items.0.calculated_price_label', '103,39')
            ->assertJsonPath('data.items.0.precio_calculado', '103,39');
    }

    public function test_general_list_calculated_price_label_uses_legacy_thousands_format(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createUnitMeasure();
        $this->createGroup();
        $this->createSubgroup();
        $this->seedGeneralPercentages();

        $this->createInput(['id_insumo' => 1, 'tipo' => 1, 'precio' => 1000, 'descripcion' => 'Material 1']);
        $this->createInput(['id_insumo' => 2, 'tipo' => 2, 'precio' => 500, 'descripcion' => 'Mano 1']);
        $this->createInput(['id_insumo' => 3, 'tipo' => 3, 'precio' => 400, 'descripcion' => 'Herramienta 1']);

        $this->createItemRecord();
        $this->createItemInputRecord(['id_item_insumo' => 1, 'id_item' => 1, 'id_insumo' => 1, 'cantidad' => 20]);
        $this->createItemInputRecord(['id_item_insumo' => 2, 'id_item' => 1, 'id_insumo' => 2, 'cantidad' => 30]);
        $this->createItemInputRecord(['id_item_insumo' => 3, 'id_item' => 1, 'id_insumo' => 3, 'cantidad' => 10]);
        $this->createInputLog(['id_log' => 1, 'id_insumo' => 2, 'precio' => 100, 'tipo' => 2, 'descripcion' => 'Mano 1', 'fecha' => '2026-05-01']);
        $this->createInputLog(['id_log' => 2, 'id_insumo' => 3, 'precio' => 200, 'tipo' => 3, 'descripcion' => 'Herramienta 1', 'fecha' => '2026-05-01']);

        $this->getJson('/api/v1/items?per_page=10')
            ->assertOk()
            ->assertJsonPath('data.items.0.calculated_price', 63826.65)
            ->assertJsonPath('data.items.0.calculated_price_label', '63.826,65')
            ->assertJsonPath('data.items.0.precio_calculado', '63.826,65');
    }

    public function test_general_list_returns_legacy_message_when_required_percentages_are_missing(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createUnitMeasure();
        $this->createGroup();
        $this->createSubgroup();
        $this->createInput(['id_insumo' => 1, 'tipo' => 1, 'precio' => 10, 'descripcion' => 'Material 1']);
        $this->createItemRecord();
        $this->createItemInputRecord(['id_item_insumo' => 1, 'id_item' => 1, 'id_insumo' => 1, 'cantidad' => 2]);

        $this->getJson('/api/v1/items?per_page=10')
            ->assertOk()
            ->assertJsonPath('data.items.0.calculated_price', null)
            ->assertJsonPath('data.items.0.calculated_price_label', 'uno de los parametros de porcentaje no esta configurado adecuadamente')
            ->assertJsonPath('data.items.0.precio_calculado', 'uno de los parametros de porcentaje no esta configurado adecuadamente');
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

    public function test_user_with_register_permission_can_create_item_following_legacy_rules(): void
    {
        Sanctum::actingAs($this->createGeneralUserWithPermissions(['INDEX', 'REGISTRAR_ITEM']));

        $this->createUnitMeasure(['id_unidad_medida' => 1, 'descripcion' => 'Metro Cuadrado', 'abreviatura' => 'M2']);
        $this->createGroup(['id_grupo' => 1]);
        $this->createGroup(['id_grupo' => 2, 'nombre_grupo' => 'OTRO GRUPO']);
        $this->createSubgroup(['id_subgrupo' => 1, 'id_grupo' => 1]);
        $this->createSubgroup(['id_subgrupo' => 2, 'id_grupo' => 2, 'descripcion' => 'OTRO SUBGRUPO']);

        $response = $this->postJson('/api/v1/items', [
            'group_id' => 1,
            'subgroup_id' => 1,
            'item' => 'excavacion comun',
            'unit_measure_id' => 1,
            'status' => 'ac',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.item.name', 'EXCAVACION COMUN')
            ->assertJsonPath('data.item.status', 'AC')
            ->assertJsonPath('data.item.date', now()->toDateString())
            ->assertJsonPath('data.item.group.id', 1)
            ->assertJsonPath('data.item.subgroup.id', 1)
            ->assertJsonPath('data.item.unit_measure.id', 1);

        $this->assertDatabaseHas('auditoria', [
            'nombre_completo' => 'Usuario General',
            'proceso' => 'ITEMS: se creo el item EXCAVACION COMUN',
        ]);

        $this->assertDatabaseHas('item', [
            'item' => 'EXCAVACION COMUN',
            'id_unidad' => 1,
            'precio' => null,
            'estado' => 'AC',
            'id_usuario' => 2,
            'grupo' => 1,
            'subgrupo' => 1,
            'fecha_item' => now()->toDateString(),
        ]);

        $this->postJson('/api/v1/items', [
            'group_id' => 2,
            'subgroup_id' => 2,
            'item' => 'EXCAVACION COMUN',
            'unit_measure_id' => 1,
            'status' => 'AC',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['item']);

        $this->postJson('/api/v1/items', [
            'group_id' => 1,
            'subgroup_id' => 2,
            'item' => 'RELLENO COMUN',
            'unit_measure_id' => 1,
            'status' => 'AC',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['subgroup_id']);
    }

    public function test_user_without_register_permission_cannot_create_item(): void
    {
        Sanctum::actingAs($this->createGeneralUserWithPermissions(['INDEX']));

        $this->createUnitMeasure();
        $this->createGroup();
        $this->createSubgroup();

        $this->postJson('/api/v1/items', [
            'group_id' => 1,
            'subgroup_id' => 1,
            'item' => 'ITEM SIN PERMISO',
            'unit_measure_id' => 1,
            'status' => 'AC',
        ])->assertForbidden();
    }

    public function test_edit_item_updates_only_base_information_following_legacy_rules(): void
    {
        Sanctum::actingAs($this->createGeneralUserWithPermissions(['INDEX', 'EDITAR_ITEM']));

        $this->createUnitMeasure(['id_unidad_medida' => 1, 'descripcion' => 'Metro', 'abreviatura' => 'M']);
        $this->createUnitMeasure(['id_unidad_medida' => 2, 'descripcion' => 'Global', 'abreviatura' => 'GLB']);
        $this->createGroup(['id_grupo' => 1]);
        $this->createGroup(['id_grupo' => 2, 'nombre_grupo' => 'OBRA GRUESA']);
        $this->createSubgroup(['id_subgrupo' => 1, 'id_grupo' => 1]);
        $this->createSubgroup(['id_subgrupo' => 2, 'id_grupo' => 2, 'descripcion' => 'ESTRUCTURAS']);
        $this->createItemRecord([
            'id_item' => 1,
            'item' => 'ITEM ORIGINAL',
            'id_unidad' => 1,
            'precio' => 123.45,
            'estado' => 'AC',
            'grupo' => 1,
            'subgrupo' => 1,
            'especificacion' => 'original.pdf',
            'ficha' => 'ficha.pdf',
        ]);

        $this->putJson('/api/v1/items/1', [
            'group_id' => 2,
            'subgroup_id' => 2,
            'item' => 'item editado',
            'unit_measure_id' => 2,
            'status' => 'dc',
        ])->assertOk()
            ->assertJsonPath('data.item.name', 'ITEM EDITADO')
            ->assertJsonPath('data.item.status', 'DC')
            ->assertJsonPath('data.item.group.id', 2)
            ->assertJsonPath('data.item.subgroup.id', 2)
            ->assertJsonPath('data.item.unit_measure.id', 2);

        $this->assertDatabaseHas('auditoria', [
            'nombre_completo' => 'Usuario General',
            'proceso' => 'ITEMS: se actualizo el item ITEM EDITADO',
        ]);

        $this->assertDatabaseHas('item', [
            'id_item' => 1,
            'item' => 'ITEM EDITADO',
            'id_unidad' => 2,
            'precio' => 123.45,
            'estado' => 'DC',
            'id_usuario' => 2,
            'grupo' => 2,
            'subgrupo' => 2,
            'especificacion' => 'original.pdf',
            'ficha' => 'ficha.pdf',
        ]);
    }

    public function test_edit_item_preserves_existing_unit_when_unit_measure_is_not_sent(): void
    {
        Sanctum::actingAs($this->createGeneralUserWithPermissions(['INDEX', 'EDITAR_ITEM']));

        $this->createUnitMeasure(['id_unidad_medida' => 1]);
        $this->createGroup();
        $this->createSubgroup();
        $this->createItemRecord(['id_item' => 1, 'id_unidad' => 1]);

        $this->putJson('/api/v1/items/1', [
            'group_id' => 1,
            'subgroup_id' => 1,
            'item' => 'mismo item editado',
            'status' => 'AC',
        ])->assertOk()
            ->assertJsonPath('data.item.unit_measure.id', 1);

        $this->assertDatabaseHas('item', [
            'id_item' => 1,
            'item' => 'MISMO ITEM EDITADO',
            'id_unidad' => 1,
        ]);
    }

    public function test_edit_item_rejects_invalid_subgroup_and_global_duplicate_name(): void
    {
        Sanctum::actingAs($this->createGeneralUserWithPermissions(['INDEX', 'EDITAR_ITEM']));

        $this->createUnitMeasure(['id_unidad_medida' => 1]);
        $this->createGroup(['id_grupo' => 1]);
        $this->createGroup(['id_grupo' => 2, 'nombre_grupo' => 'OTRO GRUPO']);
        $this->createSubgroup(['id_subgrupo' => 1, 'id_grupo' => 1]);
        $this->createSubgroup(['id_subgrupo' => 2, 'id_grupo' => 2, 'descripcion' => 'OTRO SUBGRUPO']);
        $this->createItemRecord(['id_item' => 1, 'item' => 'ITEM UNO']);
        $this->createItemRecord(['id_item' => 2, 'item' => 'ITEM DOS']);

        $this->putJson('/api/v1/items/1', [
            'group_id' => 1,
            'subgroup_id' => 2,
            'item' => 'ITEM UNO EDITADO',
            'unit_measure_id' => 1,
            'status' => 'AC',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['subgroup_id']);

        $this->putJson('/api/v1/items/1', [
            'group_id' => 1,
            'subgroup_id' => 1,
            'item' => 'ITEM DOS',
            'unit_measure_id' => 1,
            'status' => 'AC',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['item']);
    }

    public function test_can_show_item_file_data_and_update_files_without_replacing_missing_attachment(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->createGeneralUserWithPermissions(['INDEX', 'EDITAR_ITEM']));

        $this->createUnitMeasure();
        $this->createGroup();
        $this->createSubgroup();
        $this->createItemRecord([
            'especificacion' => 'archivos/items/especificaciones/anterior.pdf',
            'ficha' => 'archivos/items/fichas/ficha-anterior.pdf',
        ]);

        $this->getJson('/api/v1/items/1')
            ->assertOk()
            ->assertJsonPath('data.item.id_item', 1)
            ->assertJsonPath('data.item.specification', 'archivos/items/especificaciones/anterior.pdf')
            ->assertJsonPath('data.item.sheet', 'archivos/items/fichas/ficha-anterior.pdf');

        $response = $this->post('/api/v1/items/1/files', [
            'item' => 'ITEM FNDR TEST',
            'specification_file' => UploadedFile::fake()->create('nueva-especificacion.pdf', 100, 'application/pdf'),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.item.sheet', 'archivos/items/fichas/ficha-anterior.pdf');

        $this->assertDatabaseHas('item', [
            'id_item' => 1,
            'ficha' => 'archivos/items/fichas/ficha-anterior.pdf',
        ]);
        Storage::disk('public')->assertExists($response->json('data.item.specification'));
    }

    public function test_can_update_both_item_files_and_reject_oversized_upload(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->createGeneralUserWithPermissions(['INDEX', 'EDITAR_ITEM']));

        $this->createUnitMeasure();
        $this->createGroup();
        $this->createSubgroup();
        $this->createItemRecord();

        $response = $this->post('/api/v1/items/1/files', [
            'item' => 'ITEM FNDR TEST',
            'specification_file' => UploadedFile::fake()->create('especificacion.pdf', 100, 'application/pdf'),
            'sheet_file' => UploadedFile::fake()->create('ficha.pdf', 100, 'application/pdf'),
        ]);

        $response->assertOk();
        Storage::disk('public')->assertExists($response->json('data.item.specification'));
        Storage::disk('public')->assertExists($response->json('data.item.sheet'));

        $this->withHeaders(['Accept' => 'application/json'])->post('/api/v1/items/1/files', [
            'item' => 'ITEM FNDR TEST',
            'sheet_file' => UploadedFile::fake()->create('muy-grande.pdf', 10241, 'application/pdf'),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['sheet_file']);
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

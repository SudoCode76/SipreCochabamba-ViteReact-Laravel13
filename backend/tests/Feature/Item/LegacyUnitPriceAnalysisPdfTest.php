<?php

namespace Tests\Feature\Item;

use App\Models\Item;
use App\Services\Items\LegacyUnitPriceAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithLegacyAuth;
use Tests\Concerns\InteractsWithLegacyInputs;
use Tests\Concerns\InteractsWithLegacyItems;
use Tests\TestCase;

class LegacyUnitPriceAnalysisPdfTest extends TestCase
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

    public function test_legacy_unit_price_analysis_json_uses_general_analysis_structure(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->seedAnalysisFixture();

        $this->getJson('/api/v1/items/1/analisis-precios-unitarios')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.item.id_item', 1)
            ->assertJsonPath('data.blocks.0.title', 'Materiales')
            ->assertJsonPath('data.blocks.0.rows.0.description', 'Material 1')
            ->assertJsonPath('data.parameters.0.amount', 8.7)
            ->assertJsonPath('data.summary.13.code', 'TOTAL')
            ->assertJsonPath('data.totals.total_price', 63.8266);
    }

    public function test_legacy_unit_price_analysis_pdf_streams_pdf_document(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->seedAnalysisFixture();

        $response = $this->get('/api/v1/items/1/analisis-precios-unitarios/pdf');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_legacy_unit_price_analysis_pdf_supports_fndr_mode(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->seedAnalysisFixture();
        $this->seedFndrPercentages();

        $response = $this->get('/api/v1/items/1/analisis-precios-unitarios/pdf?mode=fndr');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $response->assertHeader('content-disposition', 'inline; filename="analisis_de_precios_unitarios.pdf"');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_legacy_unit_price_analysis_service_uses_fndr_percentages_when_requested(): void
    {
        $this->seedAnalysisFixture();
        $this->seedFndrPercentages();

        $service = app(LegacyUnitPriceAnalysisService::class);
        $general = $service->build(Item::query()->findOrFail(1), 'general');
        $fndr = $service->build(Item::query()->findOrFail(1), 'fndr');

        $this->assertSame(63.8266, $general['totals']['total_price']);
        $this->assertSame(62.2994, $fndr['totals']['total_price']);
        $this->assertNotSame($general['totals']['total_price'], $fndr['totals']['total_price']);
    }

    private function seedAnalysisFixture(): void
    {
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
    }
}

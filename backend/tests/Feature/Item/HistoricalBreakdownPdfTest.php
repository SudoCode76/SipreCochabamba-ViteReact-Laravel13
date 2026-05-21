<?php

namespace Tests\Feature\Item;

use App\Models\Item;
use App\Modules\Items\Services\HistoricalBreakdownPdfService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithLegacyAuth;
use Tests\Concerns\InteractsWithLegacyInputs;
use Tests\Concerns\InteractsWithLegacyItems;
use Tests\TestCase;

class HistoricalBreakdownPdfTest extends TestCase
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

    public function test_historical_breakdown_pdf_streams_material_pdf_document(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->seedHistoricalBreakdownFixture();

        $response = $this->postJson('/api/v1/items/1/breakdowns/recalculate', [
            'fecha' => '2026-04-30',
            'tipo_desglose' => 'materiales',
        ]);

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $response->assertHeader('content-disposition', 'inline; filename="desgloce_recalculado.pdf"');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_historical_breakdown_pdf_accepts_all_legacy_types(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->seedHistoricalBreakdownFixture();

        foreach ([1, 2, 3] as $type) {
            $response = $this->postJson('/api/v1/items/1/breakdowns/recalculate', [
                'fecha' => '2026-04-30',
                'tipo' => $type,
            ]);

            $response->assertOk();
            $response->assertHeader('content-type', 'application/pdf');
            $this->assertStringStartsWith('%PDF', $response->getContent());
        }
    }

    public function test_historical_breakdown_uses_latest_log_before_selected_date(): void
    {
        $this->seedHistoricalBreakdownFixture();

        $components = app(HistoricalBreakdownPdfService::class)->historicalComponents(
            Item::query()->findOrFail(1),
            1,
            CarbonImmutable::parse('2026-04-30'),
        );

        $this->assertCount(1, $components);
        $this->assertSame(2, (int) $components->first()->id_log);
        $this->assertSame(12.0, (float) $components->first()->precio_insumo);
    }

    public function test_historical_breakdown_pdf_streams_legacy_message_when_no_historical_logs_exist(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createUnitMeasure();
        $this->createGroup();
        $this->createSubgroup();
        $this->createInput(['id_insumo' => 1, 'tipo' => 1, 'precio' => 10, 'descripcion' => 'Material 1']);
        $this->createItemRecord();
        $this->createItemInputRecord(['id_item_insumo' => 1, 'id_item' => 1, 'id_insumo' => 1, 'cantidad' => 2]);

        $response = $this->postJson('/api/v1/items/1/breakdowns/recalculate', [
            'fecha' => '2026-01-01',
            'tipo_desglose' => 'materiales',
        ]);

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    private function seedHistoricalBreakdownFixture(): void
    {
        $this->createUnitMeasure();
        $this->createGroup();
        $this->createSubgroup();

        $this->createInputType(['id_tipo' => 1, 'descripcion' => 'MATERIAL']);
        $this->createInputType(['id_tipo' => 2, 'descripcion' => 'MANO DE OBRA']);
        $this->createInputType(['id_tipo' => 3, 'descripcion' => 'MAQUINARIA Y HERRAMIENTAS']);

        $this->createInput(['id_insumo' => 1, 'tipo' => 1, 'precio' => 100, 'descripcion' => 'Material 1']);
        $this->createInput(['id_insumo' => 2, 'tipo' => 2, 'precio' => 50, 'descripcion' => 'Mano 1']);
        $this->createInput(['id_insumo' => 3, 'tipo' => 3, 'precio' => 40, 'descripcion' => 'Herramienta 1']);
        $this->createItemRecord();
        $this->createItemInputRecord(['id_item_insumo' => 1, 'id_item' => 1, 'id_insumo' => 1, 'cantidad' => 2]);
        $this->createItemInputRecord(['id_item_insumo' => 2, 'id_item' => 1, 'id_insumo' => 2, 'cantidad' => 3]);
        $this->createItemInputRecord(['id_item_insumo' => 3, 'id_item' => 1, 'id_insumo' => 3, 'cantidad' => 1]);

        $this->createInputLog(['id_log' => 1, 'id_insumo' => 1, 'precio' => 8, 'tipo' => 1, 'descripcion' => 'Material 1', 'fecha' => '2026-04-01']);
        $this->createInputLog(['id_log' => 2, 'id_insumo' => 1, 'precio' => 12, 'tipo' => 1, 'descripcion' => 'Material 1', 'fecha' => '2026-04-20']);
        $this->createInputLog(['id_log' => 3, 'id_insumo' => 1, 'precio' => 99, 'tipo' => 1, 'descripcion' => 'Material 1', 'fecha' => '2026-05-01']);
        $this->createInputLog(['id_log' => 4, 'id_insumo' => 2, 'precio' => 4, 'tipo' => 2, 'descripcion' => 'Mano 1', 'fecha' => '2026-04-01']);
        $this->createInputLog(['id_log' => 5, 'id_insumo' => 3, 'precio' => 3, 'tipo' => 3, 'descripcion' => 'Herramienta 1', 'fecha' => '2026-04-01']);
    }
}

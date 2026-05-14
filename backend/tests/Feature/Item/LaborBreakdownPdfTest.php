<?php

namespace Tests\Feature\Item;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithLegacyAuth;
use Tests\Concerns\InteractsWithLegacyInputs;
use Tests\Concerns\InteractsWithLegacyItems;
use Tests\TestCase;

class LaborBreakdownPdfTest extends TestCase
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

    public function test_labor_breakdown_pdf_streams_pdf_document(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->seedLaborBreakdownFixture();

        $response = $this->get('/api/v1/items/1/labor/pdf');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $response->assertHeader('content-disposition', 'inline; filename="desglose_mano_obra.pdf"');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_labor_breakdown_pdf_streams_legacy_message_when_item_has_no_labor(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createUnitMeasure();
        $this->createGroup();
        $this->createSubgroup();
        $this->createItemRecord();

        $response = $this->get('/api/v1/items/1/labor/pdf');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    private function seedLaborBreakdownFixture(): void
    {
        $this->createUnitMeasure();
        $this->createGroup();
        $this->createSubgroup();

        $this->createInputType(['descripcion' => 'MATERIAL']);
        $this->createInputType(['descripcion' => 'MANO DE OBRA']);
        $this->createInput(['id_insumo' => 1, 'tipo' => 2, 'precio' => 25, 'descripcion' => 'Labor 1']);
        $this->createItemRecord();
        $this->createItemInputRecord(['id_item_insumo' => 1, 'id_item' => 1, 'id_insumo' => 1, 'cantidad' => 3]);
    }
}

<?php

namespace Tests\Feature\Item;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithLegacyAuth;
use Tests\Concerns\InteractsWithLegacyInputs;
use Tests\Concerns\InteractsWithLegacyItems;
use Tests\TestCase;

class MachineryBreakdownPdfTest extends TestCase
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

    public function test_machinery_breakdown_pdf_streams_pdf_document(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->seedMachineryBreakdownFixture();

        $response = $this->get('/api/v1/items/1/machinery/pdf');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $response->assertHeader('content-disposition', 'inline; filename="desglose_maquinaria.pdf"');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_machinery_breakdown_pdf_streams_legacy_message_when_item_has_no_machinery(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createUnitMeasure();
        $this->createGroup();
        $this->createSubgroup();
        $this->createItemRecord();

        $response = $this->get('/api/v1/items/1/machinery/pdf');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    private function seedMachineryBreakdownFixture(): void
    {
        $this->createUnitMeasure();
        $this->createGroup();
        $this->createSubgroup();

        $this->createInputType(['descripcion' => 'MATERIAL']);
        $this->createInputType(['descripcion' => 'MANO DE OBRA']);
        $this->createInputType(['descripcion' => 'MAQUINARIA Y HERRAMIENTAS']);
        $this->createInput(['id_insumo' => 1, 'tipo' => 3, 'precio' => 30, 'descripcion' => 'Herramienta 1']);
        $this->createItemRecord();
        $this->createItemInputRecord(['id_item_insumo' => 1, 'id_item' => 1, 'id_insumo' => 1, 'cantidad' => 4]);
    }
}

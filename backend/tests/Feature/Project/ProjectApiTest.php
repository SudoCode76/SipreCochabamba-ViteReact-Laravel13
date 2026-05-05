<?php

namespace Tests\Feature\Project;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithLegacyAuth;
use Tests\Concerns\InteractsWithLegacyInputs;
use Tests\Concerns\InteractsWithLegacyItems;
use Tests\Concerns\InteractsWithLegacyProjects;
use Tests\TestCase;

class ProjectApiTest extends TestCase
{
    use InteractsWithLegacyAuth;
    use InteractsWithLegacyInputs;
    use InteractsWithLegacyItems;
    use InteractsWithLegacyProjects;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLegacyAuthSchema();
        $this->setUpLegacyInputSchema();
        $this->setUpLegacyItemSchema();
        $this->setUpLegacyProjectSchema();
    }

    public function test_can_list_context_create_update_and_show_projects(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createProjectRecord([
            'id_proyecto' => 2,
            'nombre_proyecto' => 'B PROYECTO',
        ]);
        $this->createProjectRecord([
            'id_proyecto' => 3,
            'nombre_proyecto' => 'A PROYECTO',
            'aprobado' => 'AP',
        ]);

        $this->getJson('/api/v1/projects/create-context')
            ->assertOk()
            ->assertJsonPath('data.statuses.0.code', 'AC')
            ->assertJsonPath('data.conditions.2.code', 'AP')
            ->assertJsonPath('data.permissions.can_create', true)
            ->assertJsonPath('data.responsible_options.0.id_usuario', 1)
            ->assertJsonPath('data.requester_options.0.funcionario', 'Usuario Demo');

        $this->getJson('/api/v1/projects?per_page=10')
            ->assertOk()
            ->assertJsonPath('data.items.0.nombre_proyecto', 'A PROYECTO')
            ->assertJsonPath('data.items.1.nombre_proyecto', 'B PROYECTO');

        $create = $this->postJson('/api/v1/projects', [
            'nombre_proyecto' => 'nuevo proyecto',
            'fecha' => '2026-04-30',
            'latitud' => '111',
            'longitud' => '222',
            'distrito' => '2',
            'zona' => 'zona sur',
            'otb' => 'otb central',
            'ubicacion' => 'ubicacion',
            'responsable' => 1,
            'solicitante' => 1,
            'observaciones' => 'obs',
            'estado' => 'AC',
            'aprobado' => 'PD',
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.project.nombre_proyecto', 'NUEVO PROYECTO')
            ->assertJsonPath('data.project.ubicacion', 'UBICACION')
            ->assertJsonPath('data.project.distrito', '2')
            ->assertJsonPath('data.project.zona', 'ZONA SUR')
            ->assertJsonPath('data.project.otb', 'OTB CENTRAL')
            ->assertJsonPath('data.project.id_usuario', 1);

        $projectId = $create->json('data.project.id_proyecto');

        $this->putJson('/api/v1/projects/'.$projectId, [
            'nombre_proyecto' => 'PROYECTO EDITADO',
            'fecha' => '2026-05-01',
            'ubicacion' => 'UBICACION 2',
            'responsable' => 1,
            'solicitante' => 1,
            'observaciones' => 'OBS 2',
            'estado' => 'DC',
            'aprobado' => 'RV',
        ])->assertOk()
            ->assertJsonPath('data.project.nombre_proyecto', 'PROYECTO EDITADO')
            ->assertJsonPath('data.project.estado', 'DC');

        $this->getJson('/api/v1/projects/'.$projectId)
            ->assertOk()
            ->assertJsonPath('data.project.id_proyecto', $projectId)
            ->assertJsonPath('data.project.aprobado', 'RV');
    }

    public function test_cannot_create_duplicate_project_name_even_with_different_case(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createProjectRecord([
            'nombre_proyecto' => 'PROYECTO DUPLICADO',
        ]);

        $this->postJson('/api/v1/projects', [
            'nombre_proyecto' => 'proyecto duplicado',
            'fecha' => '2026-04-30',
            'latitud' => '111',
            'longitud' => '222',
            'ubicacion' => 'ubicacion repetida',
            'responsable' => 1,
            'solicitante' => 1,
            'observaciones' => 'observaciones',
            'estado' => 'AC',
            'aprobado' => 'PD',
        ])->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.nombre_proyecto.0', 'Ya existe un proyecto con el mismo nombre.');
    }

    public function test_can_sync_project_items_and_list_them_with_incidence_prices(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createUnitMeasure();
        $this->createGroup();
        $this->createSubgroup();
        $this->seedGeneralPercentages();
        $this->createProjectRecord();

        $this->createInput(['id_insumo' => 1, 'tipo' => 1, 'precio' => 10, 'descripcion' => 'Material 1']);
        $this->createInput(['id_insumo' => 2, 'tipo' => 2, 'precio' => 5, 'descripcion' => 'Mano 1']);
        $this->createInput(['id_insumo' => 3, 'tipo' => 3, 'precio' => 4, 'descripcion' => 'Herramienta 1']);
        $this->createItemRecord();
        $this->createItemRecord(['id_item' => 2, 'item' => 'ITEM DOS']);

        $this->createItemInputRecord(['id_item_insumo' => 1, 'id_item' => 1, 'id_insumo' => 1, 'cantidad' => 2]);
        $this->createItemInputRecord(['id_item_insumo' => 2, 'id_item' => 1, 'id_insumo' => 2, 'cantidad' => 3]);
        $this->createItemInputRecord(['id_item_insumo' => 3, 'id_item' => 1, 'id_insumo' => 3, 'cantidad' => 1]);
        $this->createItemInputRecord(['id_item_insumo' => 4, 'id_item' => 2, 'id_insumo' => 1, 'cantidad' => 1]);
        $this->createItemInputRecord(['id_item_insumo' => 5, 'id_item' => 2, 'id_insumo' => 2, 'cantidad' => 1]);
        $this->createItemInputRecord(['id_item_insumo' => 6, 'id_item' => 2, 'id_insumo' => 3, 'cantidad' => 1]);

        $this->postJson('/api/v1/projects/1/items/sync', [
            'items' => [
                ['id_item' => 1, 'precio' => 10, 'cantidad' => 2, 'prioridad' => 2],
                ['id_item' => 2, 'precio' => 12, 'cantidad' => 1, 'prioridad' => 1],
            ],
        ])->assertOk();

        $this->getJson('/api/v1/projects/1/items?format=PCA')
            ->assertOk()
            ->assertJsonPath('data.items.0.id_item', 2)
            ->assertJsonPath('data.items.1.id_item', 1);

        $this->getJson('/api/v1/projects/items/1/incidence-price?format=PCA')
            ->assertOk()
            ->assertJsonPath('data.item.id_item', 1)
            ->assertJsonPath('data.item.precio', 63.8266);

        $this->getJson('/api/v1/search/items?search=ITEM')
            ->assertOk()
            ->assertJsonCount(2, 'data.items');
    }

    public function test_can_calculate_project_budgets_and_reports(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createUnitMeasure();
        $this->createGroup();
        $this->createSubgroup();
        $this->seedGeneralPercentages();
        $this->seedObrasPercentages();
        $this->createProjectRecord();
        $this->createInput(['id_insumo' => 1, 'tipo' => 1, 'precio' => 10, 'descripcion' => 'Material 1']);
        $this->createInput(['id_insumo' => 2, 'tipo' => 2, 'precio' => 5, 'descripcion' => 'Mano 1']);
        $this->createInput(['id_insumo' => 3, 'tipo' => 3, 'precio' => 4, 'descripcion' => 'Herramienta 1']);
        $this->createItemRecord();
        $this->createItemInputRecord(['id_item_insumo' => 1, 'id_item' => 1, 'id_insumo' => 1, 'cantidad' => 2]);
        $this->createItemInputRecord(['id_item_insumo' => 2, 'id_item' => 1, 'id_insumo' => 2, 'cantidad' => 3]);
        $this->createItemInputRecord(['id_item_insumo' => 3, 'id_item' => 1, 'id_insumo' => 3, 'cantidad' => 1]);
        $this->createProjectItemRecord(['id_item' => 1, 'cantidad' => 2, 'precio' => 63.8266, 'prioridad' => 1]);

        $this->getJson('/api/v1/projects/1/budget-by-group')
            ->assertOk()
            ->assertJsonPath('data.items.0.materiales', 20)
            ->assertJsonPath('data.items.0.mano_obra', 15)
            ->assertJsonPath('data.items.0.herramientas', 4);

        $this->createInputLog(['id_log' => 1, 'id_insumo' => 1, 'precio' => 8, 'tipo' => 1, 'descripcion' => 'Material 1', 'fecha' => '2026-04-01']);
        $this->createInputLog(['id_log' => 2, 'id_insumo' => 2, 'precio' => 4, 'tipo' => 2, 'descripcion' => 'Mano 1', 'fecha' => '2026-04-01']);
        $this->createInputLog(['id_log' => 3, 'id_insumo' => 3, 'precio' => 3, 'tipo' => 3, 'descripcion' => 'Herramienta 1', 'fecha' => '2026-04-01']);

        $this->postJson('/api/v1/projects/1/budget-recalculation', [
            'fecha' => '2026-04-30',
        ])->assertOk()
            ->assertJsonPath('data.items.0.materiales', 16)
            ->assertJsonPath('data.items.0.mano_obra', 12)
            ->assertJsonPath('data.items.0.herramientas', 3);

        $this->getJson('/api/v1/projects/1/incidence-summary?format=PCA')
            ->assertOk()
            ->assertJsonPath('data.items.0.precio', 63.8266);

        $this->postJson('/api/v1/projects/1/breakdown-calculation', [
            'format' => 'PC_OBRAS',
        ])->assertOk()
            ->assertJsonPath('data.items.0.precio', 39);

        $this->getJson('/api/v1/projects/1/unit-prices?format=PCA')
            ->assertOk()
            ->assertJsonPath('data.items.0.analysis.meta.mode', 'general');

        $this->getJson('/api/v1/users/1/display-name')
            ->assertOk()
            ->assertJsonPath('data.id_usuario', 1)
            ->assertJsonPath('data.funcionario', 'Usuario Demo');
    }
}

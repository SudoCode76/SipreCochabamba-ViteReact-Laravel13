<?php

namespace Tests\Feature\Project;

use App\Models\Permission;
use App\Models\ProjectItem;
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

        $this->assertDatabaseHas('auditoria', [
            'nombre_completo' => 'Usuario Demo',
            'proceso' => 'PROYECTOS: se creo el proyecto NUEVO PROYECTO',
        ]);

        $projectId = $create->json('data.project.id_proyecto');

        $this->assertDatabaseHas('proyecto_historial', [
            'id_proyecto' => $projectId,
            'id_usuario' => 1,
            'usuario_nombre' => 'Usuario Demo',
            'accion' => 'created',
        ]);

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

        $this->assertDatabaseHas('auditoria', [
            'nombre_completo' => 'Usuario Demo',
            'proceso' => 'PROYECTOS: se actualizo el proyecto PROYECTO EDITADO',
        ]);

        $this->assertDatabaseHas('proyecto_historial', [
            'id_proyecto' => $projectId,
            'id_usuario' => 1,
            'usuario_nombre' => 'Usuario Demo',
            'accion' => 'updated',
        ]);

        $this->getJson('/api/v1/projects/'.$projectId)
            ->assertOk()
            ->assertJsonPath('data.project.id_proyecto', $projectId)
            ->assertJsonPath('data.project.aprobado', 'RV');

        $this->getJson('/api/v1/projects/'.$projectId.'/history')
            ->assertOk()
            ->assertJsonPath('data.items.0.action', 'updated')
            ->assertJsonPath('data.items.0.user_name', 'Usuario Demo')
            ->assertJsonPath('data.items.1.action', 'created')
            ->assertJsonPath('data.meta.total', 2);
    }

    public function test_can_sort_projects_by_recent_and_oldest_registration(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createProjectRecord([
            'id_proyecto' => 1,
            'nombre_proyecto' => 'PROYECTO UNO',
            'fecha' => '82023-02-10',
        ]);
        $this->createProjectRecord([
            'id_proyecto' => 2,
            'nombre_proyecto' => 'PROYECTO DOS',
            'fecha' => '2026-02-10',
        ]);
        $this->createProjectRecord([
            'id_proyecto' => 3,
            'nombre_proyecto' => 'PROYECTO TRES',
            'fecha' => '2025-03-10',
        ]);

        $this->getJson('/api/v1/projects?per_page=10&order=recent')
            ->assertOk()
            ->assertJsonPath('data.items.0.id_proyecto', 3)
            ->assertJsonPath('data.items.1.id_proyecto', 2)
            ->assertJsonPath('data.items.2.id_proyecto', 1);

        $this->getJson('/api/v1/projects?per_page=10&order=oldest')
            ->assertOk()
            ->assertJsonPath('data.items.0.id_proyecto', 1)
            ->assertJsonPath('data.items.1.id_proyecto', 2)
            ->assertJsonPath('data.items.2.id_proyecto', 3);
    }

    public function test_can_search_projects_by_independent_words(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createProjectRecord([
            'id_proyecto' => 1,
            'nombre_proyecto' => 'DEMO PROYECTO CR',
        ]);
        $this->createProjectRecord([
            'id_proyecto' => 2,
            'nombre_proyecto' => 'OTRO TRABAJO',
        ]);

        $this->getJson('/api/v1/projects?search=demo%20proyecto')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.id_proyecto', 1);

        $this->getJson('/api/v1/projects?search=demo%20cr')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.id_proyecto', 1);

        $this->getJson('/api/v1/projects?search=demo%20%20%20cr')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.id_proyecto', 1);

        $this->getJson('/api/v1/projects?search=demo%20xyz')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 0);
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

        $this->assertDatabaseHas('proyecto_historial', [
            'id_proyecto' => 1,
            'id_usuario' => 1,
            'accion' => 'items_synced',
        ]);

        $this->getJson('/api/v1/projects/1/items?format=PCA')
            ->assertOk()
            ->assertJsonPath('data.items.0.id_item', 2)
            ->assertJsonPath('data.items.1.id_item', 1);

        $this->getJson('/api/v1/projects/items/1/incidence-price?format=PCA')
            ->assertOk()
            ->assertJsonPath('data.item.id_item', 1)
            ->assertJsonPath('data.item.precio', 63.83);

        $this->getJson('/api/v1/search/items?search=ITEM')
            ->assertOk()
            ->assertJsonCount(2, 'data.items');
    }

    public function test_project_items_can_be_grouped_by_module_and_repeated(): void
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
        $this->createItemInputRecord(['id_item_insumo' => 1, 'id_item' => 1, 'id_insumo' => 1, 'cantidad' => 2]);
        $this->createItemInputRecord(['id_item_insumo' => 2, 'id_item' => 1, 'id_insumo' => 2, 'cantidad' => 3]);
        $this->createItemInputRecord(['id_item_insumo' => 3, 'id_item' => 1, 'id_insumo' => 3, 'cantidad' => 1]);

        \Illuminate\Support\Facades\DB::table('modulo')->insert([
            ['id_modulo' => 2, 'nombre_modulo' => 'Modulo 1', 'estado' => 'AC', 'id_usuario' => 1, 'fecha' => now()->toDateString()],
            ['id_modulo' => 3, 'nombre_modulo' => 'Modulo 2', 'estado' => 'AC', 'id_usuario' => 1, 'fecha' => now()->toDateString()],
        ]);

        $this->postJson('/api/v1/projects/1/items/sync', [
            'items' => [
                ['id_item' => 1, 'id_modulo' => 2, 'precio' => 10, 'cantidad' => 2, 'prioridad' => 1],
                ['id_item' => 1, 'id_modulo' => 3, 'precio' => 10, 'cantidad' => 3, 'prioridad' => 2],
            ],
        ])->assertOk()
            ->assertJsonPath('data.project.precio', 50);

        $this->getJson('/api/v1/projects/1/items?format=PCA')
            ->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.id_item', 1)
            ->assertJsonPath('data.items.0.modulo.nombre_modulo', 'Modulo 1')
            ->assertJsonPath('data.items.1.id_item', 1)
            ->assertJsonPath('data.items.1.modulo.nombre_modulo', 'Modulo 2');

        $this->postJson('/api/v1/projects/1/items/sync', [
            'items' => [
                ['id_proyecto_item' => 1, 'id_item' => 1, 'id_modulo' => 2, 'precio' => 10, 'cantidad' => 5, 'prioridad' => 1],
            ],
        ])->assertOk()
            ->assertJsonPath('data.project.precio', 50);

        $this->assertDatabaseHas('proyecto_item', [
            'id_proyecto_item' => 1,
            'id_modulo' => 2,
            'cantidad' => 5,
            'estado' => 'AC',
        ]);

        $this->assertDatabaseHas('proyecto_item', [
            'id_proyecto_item' => 2,
            'id_modulo' => 3,
            'estado' => 'DC',
        ]);
    }

    public function test_can_create_project_without_optional_coordinates_and_observations(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->postJson('/api/v1/projects', [
            'nombre_proyecto' => 'proyecto sin coordenadas',
            'fecha' => '2026-04-30',
            'ubicacion' => 'ubicacion base',
            'responsable' => 1,
            'solicitante' => 1,
            'estado' => 'AC',
            'aprobado' => 'PD',
        ])->assertCreated()
            ->assertJsonPath('data.project.nombre_proyecto', 'PROYECTO SIN COORDENADAS')
            ->assertJsonPath('data.project.latitud', null)
            ->assertJsonPath('data.project.longitud', null)
            ->assertJsonPath('data.project.observaciones', null);
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

        $pdf = $this->get('/api/v1/projects/1/budget-by-group/pdf');

        $pdf->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'inline; filename="presupuesto_por_rubros.pdf"');

        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $xlsx = $this->get('/api/v1/projects/1/budget-by-group/xlsx');

        $xlsx->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertHeader('content-disposition', 'attachment; filename="presupuesto_por_rubros.xlsx"');

        $this->assertStringStartsWith('PK', $xlsx->getContent());

        $this->assertDatabaseHas('proyecto_historial', [
            'id_proyecto' => 1,
            'id_usuario' => 1,
            'accion' => 'pdf_generated',
        ]);

        $this->createInputLog(['id_log' => 1, 'id_insumo' => 1, 'precio' => 8, 'tipo' => 1, 'descripcion' => 'Material 1', 'fecha' => '2026-04-01']);
        $this->createInputLog(['id_log' => 2, 'id_insumo' => 2, 'precio' => 4, 'tipo' => 2, 'descripcion' => 'Mano 1', 'fecha' => '2026-04-01']);
        $this->createInputLog(['id_log' => 3, 'id_insumo' => 3, 'precio' => 3, 'tipo' => 3, 'descripcion' => 'Herramienta 1', 'fecha' => '2026-04-01']);

        $this->postJson('/api/v1/projects/1/budget-recalculation', [
            'fecha' => '2026-04-30',
        ])->assertOk()
            ->assertJsonPath('data.items.0.materiales', 16)
            ->assertJsonPath('data.items.0.mano_obra', 12)
            ->assertJsonPath('data.items.0.herramientas', 3);

        $historicalPdf = $this->get('/api/v1/projects/1/budget-recalculation/pdf?fecha=2026-04-30');

        $historicalPdf->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'inline; filename="presupuesto_por_rubros.pdf"');

        $this->assertStringStartsWith('%PDF', $historicalPdf->getContent());

        $historicalXlsx = $this->get('/api/v1/projects/1/budget-recalculation/xlsx?fecha=2026-04-30');

        $historicalXlsx->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertHeader('content-disposition', 'attachment; filename="presupuesto_recalculado.xlsx"');

        $this->assertStringStartsWith('PK', $historicalXlsx->getContent());

        $this->assertDatabaseHas('proyecto_historial', [
            'id_proyecto' => 1,
            'id_usuario' => 1,
            'accion' => 'budget_recalculated',
        ]);

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

    public function test_budget_by_group_pdf_is_valid_when_project_has_no_items(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());
        $this->createProjectRecord();

        $response = $this->get('/api/v1/projects/1/budget-by-group/pdf');

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_budget_by_group_pdf_data_uses_legacy_complete_items_dataset(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());
        $this->createUnitMeasure();
        $this->createInputType(['descripcion' => 'MATERIAL']);
        $this->createInputType(['descripcion' => 'MANO DE OBRA']);
        $this->createInputType(['descripcion' => 'HERRAMIENTA']);
        $this->createGroup();
        $this->createSubgroup();
        $this->createProjectRecord();
        $this->createItemRecord();
        $this->createProjectItemRecord(['id_item' => 1, 'cantidad' => 1, 'precio' => 0, 'prioridad' => 1]);

        $data = app(\App\Modules\Projects\Services\ProjectBudgetService::class)
            ->budgetByGroupPdfData(\App\Models\Project::findOrFail(1));

        $this->assertSame(1, $data['items_proyecto_count']);
        $this->assertSame([], $data['items']);
        $this->assertSame([
            'materiales' => 0.0,
            'mano_obra' => 0.0,
            'herramientas' => 0.0,
        ], $data['totals']);

        $response = $this->get('/api/v1/projects/1/budget-by-group/pdf');

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_budget_recalculation_uses_legacy_complete_items_dataset(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());
        $this->createUnitMeasure();
        $this->createProjectRecord();
        $this->createInput(['id_insumo' => 1, 'tipo' => 1, 'precio' => 10, 'descripcion' => 'Material 1']);
        $this->createItemRecord(['id_item' => 1, 'grupo' => 999, 'subgrupo' => 999]);
        $this->createItemInputRecord(['id_item_insumo' => 1, 'id_item' => 1, 'id_insumo' => 1, 'cantidad' => 2]);
        $this->createProjectItemRecord(['id_item' => 1, 'cantidad' => 1, 'precio' => 0, 'prioridad' => 1]);
        $this->createInputLog(['id_log' => 1, 'id_insumo' => 1, 'precio' => 8, 'tipo' => 1, 'descripcion' => 'Material 1', 'fecha' => '2026-04-01']);

        $data = app(\App\Modules\Projects\Services\ProjectBudgetService::class)
            ->budgetRecalculation(\App\Models\Project::findOrFail(1), \Carbon\Carbon::parse('2026-04-30'));

        $this->assertSame([], $data['items']);
        $this->assertSame([
            'materiales' => 0.0,
            'mano_obra' => 0.0,
            'herramientas' => 0.0,
        ], $data['totals']);
    }

    public function test_budget_recalculation_reproduces_legacy_tools_log_selection(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());
        $this->createUnitMeasure();
        \Illuminate\Support\Facades\DB::table('tipo_insumo')->insert([
            ['id_tipo' => 1, 'descripcion' => 'MATERIAL', 'estado' => 'AC'],
            ['id_tipo' => 2, 'descripcion' => 'MANO DE OBRA', 'estado' => 'AC'],
            ['id_tipo' => 3, 'descripcion' => 'HERRAMIENTA', 'estado' => 'AC'],
        ]);
        $this->createGroup();
        $this->createSubgroup();
        $this->createProjectRecord();
        $this->createInput(['id_insumo' => 1, 'tipo' => 3, 'precio' => 50, 'descripcion' => 'Herramienta 1']);
        $this->createInput(['id_insumo' => 2, 'tipo' => 3, 'precio' => 999, 'descripcion' => 'Herramienta 2']);
        $this->createItemRecord();
        $this->createItemInputRecord(['id_item_insumo' => 1, 'id_item' => 1, 'id_insumo' => 1, 'cantidad' => 1]);
        $this->createItemInputRecord(['id_item_insumo' => 2, 'id_item' => 1, 'id_insumo' => 2, 'cantidad' => 1]);
        $this->createProjectItemRecord(['id_item' => 1, 'cantidad' => 1, 'precio' => 0, 'prioridad' => 1]);
        \Illuminate\Support\Facades\DB::table('log_insumo')->insert([
            ['id_log' => 10, 'id_insumo' => 1, 'precio' => 100, 'tipo' => 3, 'descripcion' => 'Herramienta 1', 'fecha' => '2026-04-01', 'estado' => 'AC'],
            ['id_log' => 11, 'id_insumo' => 1, 'precio' => 50, 'tipo' => 3, 'descripcion' => 'Herramienta 1', 'fecha' => '2026-04-02', 'estado' => 'AC'],
            ['id_log' => 5, 'id_insumo' => 2, 'precio' => 999, 'tipo' => 3, 'descripcion' => 'Herramienta 2', 'fecha' => '2026-04-03', 'estado' => 'AC'],
        ]);

        $data = app(\App\Modules\Projects\Services\ProjectBudgetService::class)
            ->budgetRecalculation(\App\Models\Project::findOrFail(1), \Carbon\Carbon::parse('2026-04-30'));

        $this->assertSame(50.0, $data['items'][0]['herramientas']);
        $this->assertSame(50.0, $data['totals']['herramientas']);
    }

    public function test_budget_by_group_pdf_data_orders_by_group_then_subgroup_like_legacy(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());
        $this->createUnitMeasure();
        $this->createGroup(['id_grupo' => 1, 'nombre_grupo' => 'ZETA']);
        $this->createGroup(['id_grupo' => 2, 'nombre_grupo' => 'ALFA', 'codigo_grupo' => '002-ALF']);
        $this->createSubgroup(['id_subgrupo' => 1, 'id_grupo' => 1, 'descripcion' => 'BETA']);
        $this->createSubgroup(['id_subgrupo' => 2, 'id_grupo' => 2, 'descripcion' => 'OMEGA', 'codigo' => 'OMG']);
        $this->createProjectRecord();
        $this->createInput(['id_insumo' => 1, 'tipo' => 1, 'precio' => 10, 'descripcion' => 'Material 1']);
        $this->createItemRecord(['id_item' => 1, 'item' => 'ITEM ZETA', 'grupo' => 1, 'subgrupo' => 1]);
        $this->createItemRecord(['id_item' => 2, 'item' => 'ITEM ALFA', 'grupo' => 2, 'subgrupo' => 2, 'cod' => 'ITM-002']);
        $this->createItemInputRecord(['id_item_insumo' => 1, 'id_item' => 1, 'id_insumo' => 1, 'cantidad' => 1]);
        $this->createItemInputRecord(['id_item_insumo' => 2, 'id_item' => 2, 'id_insumo' => 1, 'cantidad' => 1]);
        $this->createProjectItemRecord(['id_proyecto_item' => 1, 'id_item' => 1, 'prioridad' => 1]);
        $this->createProjectItemRecord(['id_proyecto_item' => 2, 'id_item' => 2, 'prioridad' => 99]);

        $data = app(\App\Modules\Projects\Services\ProjectBudgetService::class)
            ->budgetByGroupPdfData(\App\Models\Project::findOrFail(1));

        $this->assertSame(['ITEM ALFA', 'ITEM ZETA'], array_column($data['items'], 'descripcion'));
        $this->assertSame(['ALFA', 'ZETA'], array_column($data['items'], 'grupo'));
    }

    public function test_can_generate_incidence_summary_pdf(): void
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
        $this->createItemInputRecord(['id_item_insumo' => 1, 'id_item' => 1, 'id_insumo' => 1, 'cantidad' => 2]);
        $this->createItemInputRecord(['id_item_insumo' => 2, 'id_item' => 1, 'id_insumo' => 2, 'cantidad' => 3]);
        $this->createItemInputRecord(['id_item_insumo' => 3, 'id_item' => 1, 'id_insumo' => 3, 'cantidad' => 1]);
        $this->createProjectItemRecord(['id_item' => 1, 'cantidad' => 2, 'precio' => 63.8266, 'prioridad' => 1]);

        $response = $this->get('/api/v1/projects/1/incidence-summary/pdf?format=PCA');

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'inline; filename="resumen_incidencia.pdf"');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_incidence_summary_pdf_is_valid_when_percentages_are_incomplete(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());
        $this->createProjectRecord();

        $response = $this->get('/api/v1/projects/1/incidence-summary/pdf?format=PCA');

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_can_generate_general_budget_pdf(): void
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
        $this->createItemRecord(['precio' => 9999]);
        $this->createItemInputRecord(['id_item_insumo' => 1, 'id_item' => 1, 'id_insumo' => 1, 'cantidad' => 2]);
        $this->createItemInputRecord(['id_item_insumo' => 2, 'id_item' => 1, 'id_insumo' => 2, 'cantidad' => 3]);
        $this->createItemInputRecord(['id_item_insumo' => 3, 'id_item' => 1, 'id_insumo' => 3, 'cantidad' => 1]);
        $this->createProjectItemRecord(['id_item' => 1, 'cantidad' => 2, 'precio' => 9999, 'prioridad' => 1]);

        $items = app(\App\Modules\Projects\Services\ProjectBudgetService::class)->generalBudgetPdfItems(
            \App\Models\Project::findOrFail(1),
            'PCA',
            app(\App\Modules\Projects\Services\ProjectLegacyUnitPriceService::class),
        );

        $this->assertEqualsWithDelta(63.826645668056706, $items[0]['precio'], 0.000001);

        $response = $this->get('/api/v1/projects/1/general-budget/pdf?format=PCA');

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'inline; filename="presupuesto_general.pdf"');
        $this->assertStringStartsWith('%PDF', $response->getContent());

        $xlsx = $this->get('/api/v1/projects/1/general-budget/xlsx?format=PCA');

        $xlsx->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertHeader('content-disposition', 'attachment; filename="presupuesto_general.xlsx"');
        $this->assertStringStartsWith('PK', $xlsx->getContent());
    }

    public function test_general_budget_pdf_is_valid_when_project_has_no_items(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());
        $this->createProjectRecord();

        $response = $this->get('/api/v1/projects/1/general-budget/pdf?format=PCA');

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_can_generate_project_input_breakdown_pdf_by_type(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());
        $this->createUnitMeasure();
        $this->createGroup();
        $this->createSubgroup();
        $this->createProjectRecord();
        $this->createInput(['id_insumo' => 1, 'tipo' => 1, 'precio' => 10, 'descripcion' => 'Material 1']);
        $this->createInput(['id_insumo' => 2, 'tipo' => 2, 'precio' => 5, 'descripcion' => 'Mano 1']);
        $this->createInput(['id_insumo' => 3, 'tipo' => 3, 'precio' => 4, 'descripcion' => 'Herramienta 1']);
        $this->createItemRecord();
        $this->createItemInputRecord(['id_item_insumo' => 1, 'id_item' => 1, 'id_insumo' => 1, 'cantidad' => 2]);
        $this->createItemInputRecord(['id_item_insumo' => 2, 'id_item' => 1, 'id_insumo' => 2, 'cantidad' => 3]);
        $this->createItemInputRecord(['id_item_insumo' => 3, 'id_item' => 1, 'id_insumo' => 3, 'cantidad' => 1]);
        $this->createProjectItemRecord(['id_item' => 1, 'cantidad' => 2, 'prioridad' => 1]);

        $service = app(\App\Modules\Projects\Services\ProjectInputBreakdownPdfService::class);
        $this->assertSame('Material 1', $service->rows(\App\Models\Project::findOrFail(1), 1)[0]['descripcion']);
        $this->assertSame(20.0, $service->rows(\App\Models\Project::findOrFail(1), 1)[0]['parcial']);
        $this->assertSame('Mano 1', $service->rows(\App\Models\Project::findOrFail(1), 2)[0]['descripcion']);
        $this->assertSame('Herramienta 1', $service->rows(\App\Models\Project::findOrFail(1), 3)[0]['descripcion']);

        foreach ([1 => 'desglose_materiales.pdf', 2 => 'desglose_mano_obra.pdf', 3 => 'desglose_maquinaria.pdf'] as $type => $filename) {
            $response = $this->get('/api/v1/projects/1/input-breakdown/pdf?type='.$type);

            $response->assertOk()
                ->assertHeader('content-type', 'application/pdf')
                ->assertHeader('content-disposition', 'inline; filename="'.$filename.'"');
            $this->assertStringStartsWith('%PDF', $response->getContent());

            $xlsx = $this->get('/api/v1/projects/1/input-breakdown/xlsx?type='.$type);

            $xlsx->assertOk()
                ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $this->assertStringStartsWith('PK', $xlsx->getContent());
        }
    }

    public function test_project_input_breakdown_pdf_rows_follow_project_item_priority(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());
        $this->createUnitMeasure();
        $this->createGroup();
        $this->createSubgroup();
        $this->createProjectRecord();
        $this->createInput(['id_insumo' => 1, 'tipo' => 1, 'precio' => 10, 'descripcion' => 'Material Item Uno']);
        $this->createInput(['id_insumo' => 2, 'tipo' => 1, 'precio' => 20, 'descripcion' => 'Material Item Dos']);
        $this->createItemRecord(['id_item' => 1, 'item' => 'ITEM UNO']);
        $this->createItemRecord(['id_item' => 2, 'item' => 'ITEM DOS', 'cod' => 'ITM-002']);
        $this->createItemInputRecord(['id_item_insumo' => 1, 'id_item' => 1, 'id_insumo' => 1, 'cantidad' => 1]);
        $this->createItemInputRecord(['id_item_insumo' => 2, 'id_item' => 2, 'id_insumo' => 2, 'cantidad' => 1]);
        $this->createProjectItemRecord(['id_proyecto_item' => 1, 'id_item' => 1, 'prioridad' => 20]);
        $this->createProjectItemRecord(['id_proyecto_item' => 2, 'id_item' => 2, 'prioridad' => 10]);

        $rows = app(\App\Modules\Projects\Services\ProjectInputBreakdownPdfService::class)
            ->rows(\App\Models\Project::findOrFail(1), 1);

        $this->assertSame(['ITEM DOS', 'ITEM UNO'], array_column($rows, 'nombre_item'));
        $this->assertSame([10, 20], array_column($rows, 'prioridad'));
    }

    public function test_project_input_breakdown_pdf_uses_only_active_inputs_like_legacy(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());
        $this->createUnitMeasure();
        $this->createGroup();
        $this->createSubgroup();
        $this->createProjectRecord();
        $this->createInput(['id_insumo' => 1, 'tipo' => 1, 'precio' => 10, 'descripcion' => 'Material Activo', 'estado' => 'AC']);
        $this->createInput(['id_insumo' => 2, 'tipo' => 1, 'precio' => 20, 'descripcion' => 'Material Inactivo', 'estado' => 'DC']);
        $this->createItemRecord();
        $this->createItemInputRecord(['id_item_insumo' => 1, 'id_item' => 1, 'id_insumo' => 1, 'cantidad' => 1]);
        $this->createItemInputRecord(['id_item_insumo' => 2, 'id_item' => 1, 'id_insumo' => 2, 'cantidad' => 1]);
        $this->createProjectItemRecord(['id_item' => 1, 'prioridad' => 1]);

        $rows = app(\App\Modules\Projects\Services\ProjectInputBreakdownPdfService::class)
            ->rows(\App\Models\Project::findOrFail(1), 1);

        $this->assertSame(['Material Activo'], array_column($rows, 'descripcion'));
    }

    public function test_can_generate_consolidated_project_inputs_report_pdf(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());
        $this->createUnitMeasure();
        $this->createGroup();
        $this->createSubgroup();
        $this->createProjectRecord();
        $this->createInput(['id_insumo' => 1, 'tipo' => 1, 'precio' => 10, 'descripcion' => 'Material Repetido']);
        $this->createInput(['id_insumo' => 2, 'tipo' => 2, 'precio' => 5, 'descripcion' => 'Mano Consolidada']);
        $this->createInput(['id_insumo' => 3, 'tipo' => 3, 'precio' => 4, 'descripcion' => 'Herramienta Consolidada']);
        $this->createItemRecord(['id_item' => 1, 'item' => 'ITEM UNO']);
        $this->createItemRecord(['id_item' => 2, 'item' => 'ITEM DOS', 'cod' => 'ITM-002']);
        $this->createItemInputRecord(['id_item_insumo' => 1, 'id_item' => 1, 'id_insumo' => 1, 'cantidad' => 2]);
        $this->createItemInputRecord(['id_item_insumo' => 2, 'id_item' => 2, 'id_insumo' => 1, 'cantidad' => 3]);
        $this->createItemInputRecord(['id_item_insumo' => 3, 'id_item' => 1, 'id_insumo' => 2, 'cantidad' => 1]);
        $this->createItemInputRecord(['id_item_insumo' => 4, 'id_item' => 2, 'id_insumo' => 3, 'cantidad' => 1]);
        $this->createProjectItemRecord(['id_proyecto_item' => 1, 'id_item' => 1, 'cantidad' => 2, 'prioridad' => 1]);
        $this->createProjectItemRecord(['id_proyecto_item' => 2, 'id_item' => 2, 'cantidad' => 1, 'prioridad' => 2]);

        $rows = app(\App\Modules\Projects\Services\ProjectInputsReportPdfService::class)
            ->rows(\App\Models\Project::findOrFail(1));

        $this->assertSame(['Material Repetido', 'Mano Consolidada', 'Herramienta Consolidada'], array_column($rows, 'descripcion'));
        $this->assertSame(7.0, $rows[0]['cantidad']);
        $this->assertSame(70.0, $rows[0]['parcial']);

        $response = $this->get('/api/v1/projects/1/inputs-report/pdf');

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'inline; filename="reporte_consolidado_insumos.pdf"');
        $this->assertStringStartsWith('%PDF', $response->getContent());

        $xlsx = $this->get('/api/v1/projects/1/inputs-report/xlsx');

        $xlsx->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertHeader('content-disposition', 'attachment; filename="reporte_consolidado_insumos.xlsx"');
        $this->assertStringStartsWith('PK', $xlsx->getContent());

        $this->assertDatabaseHas('proyecto_historial', [
            'id_proyecto' => 1,
            'id_usuario' => 1,
            'accion' => 'pdf_generated',
        ]);
    }

    public function test_consolidated_project_inputs_report_excludes_inactive_records(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());
        $this->createUnitMeasure();
        $this->createGroup();
        $this->createSubgroup();
        $this->createProjectRecord();
        $this->createInput(['id_insumo' => 1, 'tipo' => 1, 'precio' => 10, 'descripcion' => 'Material Activo', 'estado' => 'AC']);
        $this->createInput(['id_insumo' => 2, 'tipo' => 1, 'precio' => 20, 'descripcion' => 'Insumo Inactivo', 'estado' => 'DC']);
        $this->createInput(['id_insumo' => 3, 'tipo' => 1, 'precio' => 30, 'descripcion' => 'Relacion Inactiva', 'estado' => 'AC']);
        $this->createInput(['id_insumo' => 4, 'tipo' => 1, 'precio' => 40, 'descripcion' => 'Proyecto Item Inactivo', 'estado' => 'AC']);
        $this->createItemRecord(['id_item' => 1, 'item' => 'ITEM ACTIVO']);
        $this->createItemRecord(['id_item' => 2, 'item' => 'ITEM INACTIVO PROYECTO', 'cod' => 'ITM-002']);
        $this->createItemInputRecord(['id_item_insumo' => 1, 'id_item' => 1, 'id_insumo' => 1, 'cantidad' => 1]);
        $this->createItemInputRecord(['id_item_insumo' => 2, 'id_item' => 1, 'id_insumo' => 2, 'cantidad' => 1]);
        $this->createItemInputRecord(['id_item_insumo' => 3, 'id_item' => 1, 'id_insumo' => 3, 'cantidad' => 1, 'estado' => 'DC']);
        $this->createItemInputRecord(['id_item_insumo' => 4, 'id_item' => 2, 'id_insumo' => 4, 'cantidad' => 1]);
        $this->createProjectItemRecord(['id_proyecto_item' => 1, 'id_item' => 1, 'cantidad' => 2, 'estado' => 'AC']);
        $this->createProjectItemRecord(['id_proyecto_item' => 2, 'id_item' => 2, 'cantidad' => 2, 'estado' => 'DC']);

        $rows = app(\App\Modules\Projects\Services\ProjectInputsReportPdfService::class)
            ->rows(\App\Models\Project::findOrFail(1));

        $this->assertSame(['Material Activo'], array_column($rows, 'descripcion'));
        $this->assertSame(2.0, $rows[0]['cantidad']);
    }

    public function test_consolidated_project_inputs_report_pdf_is_valid_when_project_has_no_inputs(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());
        $this->createProjectRecord();

        $response = $this->get('/api/v1/projects/1/inputs-report/pdf');

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_non_admin_without_project_permissions_cannot_manage_projects(): void
    {
        $this->createLegacyAuthUser();
        $this->createProjectRecord();

        Sanctum::actingAs($this->createProjectUserWithPermissions([]));

        $this->getJson('/api/v1/projects/context')->assertForbidden();
        $this->getJson('/api/v1/projects')->assertForbidden();
        $this->getJson('/api/v1/projects/1/history')->assertForbidden();
        $this->get('/api/v1/projects/1/inputs-report/pdf')->assertForbidden();
        $this->get('/api/v1/projects/1/budget-recalculation/pdf?fecha=2026-04-30')->assertForbidden();
        $this->postJson('/api/v1/projects', [
            'nombre_proyecto' => 'nuevo proyecto',
            'fecha' => '2026-04-30',
            'latitud' => '111',
            'longitud' => '222',
            'ubicacion' => 'ubicacion',
            'responsable' => 1,
            'solicitante' => 1,
            'observaciones' => 'obs',
            'estado' => 'AC',
            'aprobado' => 'PD',
        ])->assertForbidden();
    }

    public function test_can_create_template_from_project_and_create_project_from_template(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());
        $this->createProjectRecord();
        $this->createUnitMeasure();
        $this->createItemRecord();
        $this->createProjectItemRecord([
            'id_proyecto_item' => 1,
            'estado' => 'AC',
            'cantidad' => 2,
            'precio' => 15,
        ]);
        $this->createProjectItemRecord([
            'id_proyecto_item' => 2,
            'estado' => 'DC',
            'cantidad' => 5,
            'precio' => 99,
        ]);

        $templateResponse = $this->postJson('/api/v1/projects/1/template', [
            'nombre_proyecto' => 'planilla base',
        ])->assertCreated()
            ->assertJsonPath('data.template.nombre_proyecto', 'PLANILLA BASE')
            ->assertJsonPath('data.template.es_plantilla', true);

        $templateId = $templateResponse->json('data.template.id_proyecto');

        $this->assertDatabaseHas('proyecto', [
            'id_proyecto' => $templateId,
            'es_plantilla' => true,
            'estado' => 'AC',
        ]);
        $this->assertSame(1, ProjectItem::query()->where('id_proyecto', $templateId)->where('estado', 'AC')->count());

        $this->getJson('/api/v1/projects?per_page=10')
            ->assertOk()
            ->assertJsonMissingPath('data.items.1');

        $this->getJson('/api/v1/project-templates?per_page=10')
            ->assertOk()
            ->assertJsonPath('data.items.0.id_proyecto', $templateId);

        $projectResponse = $this->postJson("/api/v1/project-templates/{$templateId}/create-project", [
            'nombre_proyecto' => 'proyecto desde planilla',
            'fecha' => '2026-05-21',
            'latitud' => '111',
            'longitud' => '222',
            'ubicacion' => 'ubicacion nueva',
            'responsable' => 1,
            'solicitante' => 1,
            'observaciones' => 'obs nueva',
            'estado' => 'AC',
            'aprobado' => 'PD',
            'distrito' => 'D1',
            'zona' => 'ZONA NUEVA',
            'otb' => 'OTB NUEVA',
        ])->assertCreated()
            ->assertJsonPath('data.project.nombre_proyecto', 'PROYECTO DESDE PLANILLA')
            ->assertJsonPath('data.project.es_plantilla', false)
            ->assertJsonPath('data.project.precio', 30);

        $projectId = $projectResponse->json('data.project.id_proyecto');

        $this->assertSame(1, ProjectItem::query()->where('id_proyecto', $projectId)->where('estado', 'AC')->count());
        $this->assertDatabaseHas('proyecto_historial', [
            'id_proyecto' => 1,
            'accion' => 'template_created',
        ]);
        $this->assertDatabaseHas('proyecto_historial', [
            'id_proyecto' => $projectId,
            'accion' => 'created_from_template',
        ]);
    }

    public function test_non_admin_with_project_permissions_can_access_allowed_endpoints(): void
    {
        $this->createLegacyAuthUser();
        $this->createProjectRecord();

        Sanctum::actingAs($this->createProjectUserWithPermissions(['INDEX', 'REGISTRAR_PROYECTO']));

        $this->getJson('/api/v1/projects/context')
            ->assertOk()
            ->assertJsonPath('data.permissions.can_create', true);

        $this->getJson('/api/v1/projects')
            ->assertOk();

        $this->postJson('/api/v1/projects', [
            'nombre_proyecto' => 'nuevo proyecto',
            'fecha' => '2026-04-30',
            'latitud' => '111',
            'longitud' => '222',
            'ubicacion' => 'ubicacion',
            'responsable' => 1,
            'solicitante' => 1,
            'observaciones' => 'obs',
            'estado' => 'AC',
            'aprobado' => 'PD',
        ])->assertCreated();
    }

    private function createProjectUserWithPermissions(array $functionNames): User
    {
        $role = Role::query()->create([
            'id_rol' => 2,
            'nombre_rol' => 'Tecnico Proyecto',
            'estado' => 'AC',
        ]);

        $unit = Unit::query()->firstOrCreate([
            'id_unidad' => 2,
        ], [
            'descripcion' => 'Unidad Tecnica',
            'estado' => 'AC',
        ]);

        foreach (array_values($functionNames) as $index => $functionName) {
            $function = SystemFunction::query()->create([
                'id_funcion' => 200 + $index,
                'nombre_funcion' => $functionName,
                'descripcion' => $functionName,
                'clase' => 'PROYECTO',
                'estado' => 'AC',
            ]);

            Permission::query()->create([
                'id_permiso' => 200 + $index,
                'id_rol' => $role->id_rol,
                'nombre_rol' => $role->nombre_rol,
                'id_funcion' => $function->id_funcion,
                'descripcion' => $function->descripcion,
                'estado' => 'AC',
            ]);
        }

        return User::query()->create([
            'id_usuario' => 2,
            'funcionario' => 'Usuario Proyecto',
            'ci' => '87654321',
            'username' => 'proyecto',
            'clave' => Hash::make('secret123'),
            'estado' => 'AC',
            'id_unidad' => $unit->id_unidad,
            'rol' => $role->id_rol,
            'fecha' => now()->toDateString(),
            'subalcaldia' => null,
        ]);
    }
}

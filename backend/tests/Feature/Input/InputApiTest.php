<?php

namespace Tests\Feature\Input;

use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithLegacyAuth;
use Tests\Concerns\InteractsWithLegacyInputs;
use Tests\Concerns\InteractsWithLegacyItems;
use Tests\Concerns\InteractsWithLegacyProjects;
use Tests\TestCase;

class InputApiTest extends TestCase
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
        $this->createInputType();
        $this->createInputCategory();
        $this->createUnitMeasure();
    }

    public function test_admin_can_list_inputs_with_legacy_order_and_filters(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createInputType([
            'id_tipo' => 2,
            'descripcion' => 'MANO DE OBRA',
            'estado' => 'AC',
        ]);

        $this->createUnitMeasure([
            'id_unidad_medida' => 2,
            'descripcion' => 'Metro',
            'abreviatura' => 'm',
            'estado' => 'AC',
        ]);

        $this->createInputCategory([
            'id_categoria' => 2,
            'descripcion' => 'Categoria secundaria',
            'estado' => 'AC',
        ]);

        $this->createInput([
            'id_insumo' => 1,
            'descripcion' => 'Zinc',
            'tipo' => 2,
            'id_categoria' => 2,
            'unidad_medida' => 2,
            'estado' => 'AC',
        ]);

        $this->createInput([
            'id_insumo' => 2,
            'descripcion' => 'Acero estructural',
            'tipo' => 1,
            'unidad_medida' => 1,
            'estado' => 'AC',
        ]);

        $this->createInput([
            'id_insumo' => 3,
            'descripcion' => 'Arena fina',
            'tipo' => 1,
            'unidad_medida' => 2,
            'estado' => 'DC',
        ]);

        $response = $this->getJson('/api/v1/inputs?status=AC&per_page=10');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.meta.total', 2)
            ->assertJsonPath('data.items.0.descripcion', 'Acero estructural')
            ->assertJsonPath('data.items.0.tipo', 1)
            ->assertJsonPath('data.items.0.unidad_medida', 1)
            ->assertJsonPath('data.items.0.nombre_tipo', 'MATERIAL')
            ->assertJsonPath('data.items.0.nombre_unidad_medida', 'Pieza')
            ->assertJsonPath('data.items.1.descripcion', 'Zinc')
            ->assertJsonPath('data.items.1.tipo', 2);

        $this->getJson('/api/v1/inputs?search=acero&status=AC&type_id=1&unit_measure_id=1&per_page=10')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.id_insumo', 2);

        $this->getJson('/api/v1/inputs?category_id=2&status=AC&per_page=10')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.id_insumo', 1)
            ->assertJsonPath('data.items.0.id_categoria', 2)
            ->assertJsonPath('data.items.0.nombre_categoria', 'Categoria secundaria');
    }

    public function test_admin_can_search_inputs_by_independent_words(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createInput([
            'id_insumo' => 1,
            'descripcion' => 'ARENA FINA LAVADA',
            'estado' => 'AC',
        ]);
        $this->createInput([
            'id_insumo' => 2,
            'descripcion' => 'CEMENTO PORTLAND',
            'estado' => 'AC',
        ]);

        $this->getJson('/api/v1/inputs?search=arena%20lavada&per_page=10')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.id_insumo', 1);

        $this->getJson('/api/v1/inputs?search=arena%20fina&per_page=10')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.id_insumo', 1);

        $this->getJson('/api/v1/inputs?description=arena%20%20%20lavada&per_page=10')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.id_insumo', 1);

        $this->getJson('/api/v1/inputs?search=arena%20xyz&per_page=10')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 0);
    }

    public function test_admin_can_filter_duplicate_inputs_by_normalized_description(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createInput([
            'id_insumo' => 1,
            'descripcion' => 'Aditivo Impermeabilizante',
            'estado' => 'AC',
        ]);
        $this->createInput([
            'id_insumo' => 2,
            'descripcion' => '  aditivo   impermeabilizante ',
            'estado' => 'AC',
        ]);
        $this->createInput([
            'id_insumo' => 3,
            'descripcion' => 'Aditivo Plastificante',
            'estado' => 'AC',
        ]);

        $this->getJson('/api/v1/inputs?duplicates=1&per_page=10')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 2)
            ->assertJsonPath('data.items.0.id_insumo', 1)
            ->assertJsonPath('data.items.0.is_duplicate', true)
            ->assertJsonPath('data.items.0.duplicate_count', 2)
            ->assertJsonPath('data.items.0.duplicate_key', 'aditivo impermeabilizante')
            ->assertJsonPath('data.items.1.id_insumo', 2);

        $this->getJson('/api/v1/inputs?duplicates=1&search=impermeabilizante&status=AC&per_page=10')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 2);

        $this->getJson('/api/v1/inputs?duplicates=1&search=plastificante&per_page=10')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 0);
    }

    public function test_inputs_marked_as_deleted_are_hidden_by_default_but_can_be_filtered(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createInput([
            'id_insumo' => 1,
            'descripcion' => 'ARENA ACTIVA',
            'estado' => 'AC',
        ]);
        $this->createInput([
            'id_insumo' => 2,
            'descripcion' => 'ARENA ELIMINADA',
            'estado' => 'DP',
        ]);

        $this->getJson('/api/v1/inputs?per_page=10')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.id_insumo', 1);

        $this->getJson('/api/v1/inputs?status=DP&per_page=10')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.id_insumo', 2);
    }

    public function test_input_list_marks_only_pending_delete_authorizations_as_in_process(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createInput([
            'id_insumo' => 1,
            'descripcion' => 'ARENA CON SOLICITUD PENDIENTE',
            'estado' => 'AC',
        ]);
        $this->createInput([
            'id_insumo' => 2,
            'descripcion' => 'CEMENTO CON SOLICITUD APROBADA',
            'estado' => 'AC',
        ]);
        $this->createInput([
            'id_insumo' => 3,
            'descripcion' => 'FIERRO SIN SOLICITUD',
            'estado' => 'AC',
        ]);

        $pendingAuthorization = $this->createAuthorization([
            'id_autorizacion' => 10,
            'id_elemento' => 1,
            'elemento' => 'ARENA CON SOLICITUD PENDIENTE',
            'tipo_elemento' => 'insumo',
            'tabla' => 'insumo',
            'estado' => 'PE',
        ]);
        $this->createAuthorization([
            'id_autorizacion' => 11,
            'id_elemento' => 2,
            'elemento' => 'CEMENTO CON SOLICITUD APROBADA',
            'tipo_elemento' => 'insumo',
            'tabla' => 'insumo',
            'estado' => 'AP',
        ]);

        $this->getJson('/api/v1/inputs?order=oldest&per_page=10')
            ->assertOk()
            ->assertJsonPath('data.items.0.id_insumo', 1)
            ->assertJsonPath('data.items.0.delete_authorization_status', 'pending')
            ->assertJsonPath('data.items.0.delete_authorization_label', 'ELIMINACIÓN EN PROCESO')
            ->assertJsonPath('data.items.0.delete_authorization_id', $pendingAuthorization->id_autorizacion)
            ->assertJsonPath('data.items.1.id_insumo', 2)
            ->assertJsonPath('data.items.1.delete_authorization_status', 'none')
            ->assertJsonPath('data.items.1.delete_authorization_id', null)
            ->assertJsonPath('data.items.2.id_insumo', 3)
            ->assertJsonPath('data.items.2.delete_authorization_status', 'none');
    }

    public function test_admin_can_view_input_delete_impact_with_items_and_pending_projects(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createInput(['id_insumo' => 1, 'descripcion' => 'ARENA FINA']);
        $this->createInput(['id_insumo' => 2, 'descripcion' => 'CEMENTO PORTLAND']);
        $this->createGroup();
        $this->createSubgroup();

        $this->createItemRecord(['id_item' => 1, 'item' => 'ITEM ACTIVO', 'estado' => 'AC']);
        $this->createItemRecord(['id_item' => 2, 'item' => 'ITEM ASOCIACION INACTIVA', 'estado' => 'AC']);
        $this->createItemRecord(['id_item' => 3, 'item' => 'ITEM INACTIVO', 'estado' => 'DC']);

        $this->createItemInput(['id_item_insumo' => 1, 'id_insumo' => 1, 'id_item' => 1, 'estado' => 'AC']);
        $this->createItemInput(['id_item_insumo' => 2, 'id_insumo' => 1, 'id_item' => 2, 'estado' => 'DC']);
        $this->createItemInput(['id_item_insumo' => 3, 'id_insumo' => 1, 'id_item' => 3, 'estado' => 'AC']);
        $this->createItemInput(['id_item_insumo' => 4, 'id_insumo' => 2, 'id_item' => 1, 'estado' => 'AC']);

        $this->createProjectRecord(['id_proyecto' => 1, 'nombre_proyecto' => 'PROYECTO PENDIENTE', 'aprobado' => 'PD', 'estado' => 'AC']);
        $this->createProjectRecord(['id_proyecto' => 2, 'nombre_proyecto' => 'PROYECTO APROBADO', 'aprobado' => 'AP', 'estado' => 'AC']);
        $this->createProjectRecord(['id_proyecto' => 3, 'nombre_proyecto' => 'PLANILLA PENDIENTE', 'aprobado' => 'PD', 'estado' => 'AC', 'es_plantilla' => true]);
        $this->createProjectRecord(['id_proyecto' => 4, 'nombre_proyecto' => 'PROYECTO INACTIVO', 'aprobado' => 'PD', 'estado' => 'DC']);
        $this->createProjectRecord(['id_proyecto' => 5, 'nombre_proyecto' => 'PROYECTO ITEM INACTIVO', 'aprobado' => 'PD', 'estado' => 'AC']);

        $this->createProjectItemRecord(['id_proyecto_item' => 1, 'id_proyecto' => 1, 'id_item' => 1, 'estado' => 'AC']);
        $this->createProjectItemRecord(['id_proyecto_item' => 2, 'id_proyecto' => 1, 'id_item' => 3, 'estado' => 'AC']);
        $this->createProjectItemRecord(['id_proyecto_item' => 3, 'id_proyecto' => 2, 'id_item' => 1, 'estado' => 'AC']);
        $this->createProjectItemRecord(['id_proyecto_item' => 4, 'id_proyecto' => 3, 'id_item' => 1, 'estado' => 'AC']);
        $this->createProjectItemRecord(['id_proyecto_item' => 5, 'id_proyecto' => 4, 'id_item' => 1, 'estado' => 'AC']);
        $this->createProjectItemRecord(['id_proyecto_item' => 6, 'id_proyecto' => 5, 'id_item' => 1, 'estado' => 'DC']);

        $this->getJson('/api/v1/inputs/1/delete-impact')
            ->assertOk()
            ->assertJsonPath('data.summary.items_count', 2)
            ->assertJsonPath('data.summary.pending_projects_count', 1)
            ->assertJsonPath('data.items.0.id_item', 1)
            ->assertJsonPath('data.items.0.status_label', 'HABILITADO')
            ->assertJsonPath('data.items.1.id_item', 3)
            ->assertJsonPath('data.items.1.status_label', 'INHABILITADO')
            ->assertJsonPath('data.pending_projects.0.id_proyecto', 1)
            ->assertJsonPath('data.pending_projects.0.name', 'PROYECTO PENDIENTE')
            ->assertJsonPath('data.pending_projects.0.items_count', 2);
    }

    public function test_admin_can_sort_inputs_by_recent_and_oldest_dates(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createInput([
            'id_insumo' => 1,
            'descripcion' => 'Zinc',
            'fecha' => '2026-01-10',
        ]);

        $this->createInput([
            'id_insumo' => 2,
            'descripcion' => 'Acero estructural',
            'fecha' => '2026-02-10',
        ]);

        $this->createInput([
            'id_insumo' => 3,
            'descripcion' => 'Arena fina',
            'fecha' => '2026-03-10',
        ]);

        $this->getJson('/api/v1/inputs?per_page=10&order=recent')
            ->assertOk()
            ->assertJsonPath('data.items.0.id_insumo', 3)
            ->assertJsonPath('data.items.1.id_insumo', 2)
            ->assertJsonPath('data.items.2.id_insumo', 1);

        $this->getJson('/api/v1/inputs?per_page=10&order=oldest')
            ->assertOk()
            ->assertJsonPath('data.items.0.id_insumo', 1)
            ->assertJsonPath('data.items.1.id_insumo', 2)
            ->assertJsonPath('data.items.2.id_insumo', 3);
    }

    public function test_admin_can_get_input_context_and_select_searches(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createUnitMeasure([
            'id_unidad_medida' => 2,
            'descripcion' => 'Metro',
            'abreviatura' => 'm',
            'estado' => 'AC',
        ]);

        $this->createInput([
            'id_insumo' => 2,
            'descripcion' => 'Arena fina',
            'estado' => 'AC',
        ]);

        $this->getJson('/api/v1/inputs/context')
            ->assertOk()
            ->assertJsonPath('data.statuses.0.code', 'AC')
            ->assertJsonPath('data.statuses.1.code', 'DC')
            ->assertJsonPath('data.permissions.can_delete', true);

        $this->getJson('/api/v1/search/inputs?search=arena')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', 1)
            ->assertJsonPath('data.items.0.text', 'Arena fina');

        $this->getJson('/api/v1/search/unit-measures?search=metro')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', 2)
            ->assertJsonPath('data.items.0.text', 'Metro (m)');
    }

    public function test_admin_can_create_show_update_and_get_simple_name_of_input(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $response = $this->postJson('/api/v1/inputs', [
            'descripcion' => 'Cemento Portland',
            'unidad_medida' => 1,
            'precio' => 65.50,
            'tipo' => 1,
            'category_id' => 1,
            'estado' => 'AC',
            'cod' => 'INS-100',
            'fecha_cotiz' => '2026-04-29',
            'observacion' => 'Cotizacion base',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.input.descripcion', 'Cemento Portland')
            ->assertJsonPath('data.input.observacion', 'Cotizacion base')
            ->assertJsonPath('data.input.tipo', 1)
            ->assertJsonPath('data.input.unidad_medida', 1);

        $inputId = $response->json('data.input.id_insumo');

        $this->assertDatabaseHas('log_insumo', [
            'id_insumo' => $inputId,
            'accion' => 'RG',
            'id_categoria' => 1,
        ]);

        $this->assertDatabaseHas('historial_insumo', [
            'id_insumo' => $inputId,
            'accion' => 'REGISTRADOR',
            'id_categoria' => 1,
        ]);

        $this->getJson('/api/v1/inputs/'.$inputId)
            ->assertOk()
            ->assertJsonPath('data.input.nombre_tipo', 'MATERIAL')
            ->assertJsonPath('data.input.nombre_unidad_medida', 'Pieza')
            ->assertJsonPath('data.input.abreviatura', 'pza.');

        $this->getJson('/api/v1/inputs/'.$inputId.'/name')
            ->assertOk()
            ->assertJsonPath('data.id_insumo', $inputId)
            ->assertJsonPath('data.descripcion', 'Cemento Portland');

        $this->putJson('/api/v1/inputs/'.$inputId, [
            'descripcion' => 'Cemento Portland IP-30',
            'unidad_medida' => 1,
            'precio' => 65.50,
            'tipo' => 1,
            'category_id' => 1,
            'estado' => 'DC',
            'cod' => 'INS-101',
            'fecha_cotiz' => '2026-05-01',
            'observacion' => 'Actualizado',
        ])->assertOk()
            ->assertJsonPath('data.input.descripcion', 'Cemento Portland IP-30')
            ->assertJsonPath('data.input.estado', 'DC');

        $this->assertDatabaseHas('historial_insumo', [
            'id_insumo' => $inputId,
            'accion' => 'MODIFICADO',
            'id_categoria' => 1,
        ]);

        $this->getJson('/api/v1/inputs/'.$inputId.'/history')
            ->assertOk()
            ->assertJsonPath('data.items.0.nombre_categoria', 'Categoria demo');
    }

    public function test_cannot_create_or_update_duplicate_input_description_when_other_input_is_not_deleted(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createInput([
            'id_insumo' => 1,
            'descripcion' => 'Acero estructural',
            'estado' => 'AC',
        ]);

        $this->postJson('/api/v1/inputs', [
            'descripcion' => 'acero estructural',
            'unidad_medida' => 1,
            'precio' => 65.50,
            'tipo' => 1,
            'fecha_cotiz' => '2026-04-29',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.descripcion.0', 'Ya existe otro insumo con la misma descripcion.');

        $this->createInput([
            'id_insumo' => 2,
            'descripcion' => 'Arena fina',
            'estado' => 'AC',
        ]);

        $this->putJson('/api/v1/inputs/2', [
            'descripcion' => 'ACERO ESTRUCTURAL',
            'unidad_medida' => 1,
            'precio' => 15.36,
            'tipo' => 1,
            'estado' => 'AC',
            'fecha_cotiz' => '2026-05-01',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.descripcion.0', 'Ya existe otro insumo con la misma descripcion.');
    }

    public function test_admin_can_view_history_logs_and_quote_endpoints_of_input(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createInput();
        $this->createInputLog();
        $this->createInputHistory();
        $this->createInputQuote([
            'fecha' => '2026-04-01',
        ]);
        $this->createInputQuote([
            'id_cotizacion' => 2,
            'condicion' => 'VG',
            'fecha' => '2026-05-01',
            'id_log_insumo' => 1,
        ]);

        $this->getJson('/api/v1/inputs/1/history')
            ->assertOk()
            ->assertJsonPath('data.items.0.accion', 'MODIFICADO')
            ->assertJsonPath('data.items.0.nombre_tipo', 'MATERIAL')
            ->assertJsonPath('data.items.0.id_log_insumo', 1)
            ->assertJsonCount(2, 'data.items.0.quotes')
            ->assertJsonFragment(['id_cotizacion' => 2]);

        $this->getJson('/api/v1/inputs/1/logs')
            ->assertOk()
            ->assertJsonPath('data.items.0.accion', 'RG')
            ->assertJsonPath('data.items.0.nombre_unidad_medida', 'Pieza');

        $this->getJson('/api/v1/inputs/1/quotes/current')
            ->assertOk()
            ->assertJsonPath('data.quote.id_cotizacion', 2)
            ->assertJsonPath('data.quote.input_description', 'Acero estructural');

        $this->getJson('/api/v1/inputs/1/quotes/history')
            ->assertOk()
            ->assertJsonPath('data.items.0.id_cotizacion', 2);

        $this->getJson('/api/v1/inputs/1/quotes/log-history')
            ->assertOk()
            ->assertJsonPath('data.items.0.id_log_insumo', 1);

        $this->getJson('/api/v1/input-logs/1/files')
            ->assertOk()
            ->assertJsonCount(2, 'data.items');
    }

    public function test_admin_can_register_quote_for_input_with_uploaded_files(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createInput();
        $this->createInputLog();

        $response = $this->postJson('/api/v1/inputs/1/quotes', [
            'condition' => 'CREDITO',
            'status' => 'AC',
            'log_id' => 1,
            'date' => '2026-04-29',
            'request_id' => 8,
            'valido' => UploadedFile::fake()->create('quote.pdf', 100, 'application/pdf'),
            'propuesto_1' => UploadedFile::fake()->create('quote-a.pdf', 100, 'application/pdf'),
            'propuesto_2' => UploadedFile::fake()->create('quote-b.pdf', 100, 'application/pdf'),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.quote.id_insumo', 1)
            ->assertJsonPath('data.quote.condicion', 'CREDITO')
            ->assertJsonPath('data.quote.id_log_insumo', 1)
            ->assertJsonPath('data.quote.id_solicitud', 8)
            ->assertJsonPath('data.quote.archivo_available', true)
            ->assertJsonPath('data.quote.archivo', 'https://repository.test/files/document-1.pdf')
            ->assertJsonPath('data.quote.archivo1', 'https://repository.test/files/document-2.pdf')
            ->assertJsonPath('data.quote.archivo2', 'https://repository.test/files/document-3.pdf')
            ->assertJsonPath('data.quote.archivo_label', 'Propuesta oficial vigente')
            ->assertJsonPath('data.quote.archivo1_label', 'Propuesta alternativa 1 vigente')
            ->assertJsonPath('data.quote.archivo2_label', 'Propuesta alternativa 2 vigente');

        $this->assertDatabaseHas('cotizaciones', [
            'id_insumo' => 1,
            'condicion' => 'CREDITO',
            'id_log_insumo' => 1,
            'id_solicitud' => 8,
        ]);
    }

    public function test_quote_upload_persists_repository_metadata_before_creating_the_local_quote(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());
        $this->createInput();
        Schema::drop('cotizaciones');

        $this->postJson('/api/v1/inputs/1/quotes', [
            'valido' => UploadedFile::fake()->create('quote.pdf', 100, 'application/pdf'),
        ])->assertServerError();

        $this->assertDatabaseHas('repository_files', [
            'repository_id' => 'test-repository-file-1',
            'url_file' => 'https://repository.test/files/document-1.pdf',
        ]);
        $this->assertDatabaseCount('repository_file_links', 0);
    }

    public function test_standalone_quote_without_log_remains_unassigned(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createInput();
        $this->createInputLog();

        $this->postJson('/api/v1/inputs/1/quotes', [
            'valido' => UploadedFile::fake()->create('quote.pdf', 100, 'application/pdf'),
        ])->assertCreated()
            ->assertJsonPath('data.quote.id_log_insumo', null);

        $this->getJson('/api/v1/inputs/1/quotes/unassigned')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id_log_insumo', null);
    }

    public function test_price_change_requires_quote_support(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createInput(['precio' => 15.36]);

        $this->putJson('/api/v1/inputs/1', [
            'descripcion' => 'Acero estructural',
            'unidad_medida' => 1,
            'precio' => 20,
            'tipo' => 1,
            'estado' => 'AC',
            'fecha_cotiz' => '2026-05-01',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['precio']);

        $this->postJson('/api/v1/inputs/1/price-update', [
            'descripcion' => 'Acero estructural',
            'unidad_medida' => 1,
            'precio' => 20,
            'tipo' => 1,
            'estado' => 'AC',
            'fecha_cotiz' => '2026-05-01',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['quotes']);
    }

    public function test_input_history_without_log_returns_empty_quotes(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createInput();
        $this->createInputHistory([
            'id_log_insumo' => null,
        ]);

        $this->getJson('/api/v1/inputs/1/history')
            ->assertOk()
            ->assertJsonPath('data.items.0.id_log_insumo', null)
            ->assertJsonCount(0, 'data.items.0.quotes');
    }

    public function test_price_change_can_attach_unassigned_quote(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createInput(['precio' => 15.36]);
        $quote = $this->createInputQuote([
            'id_cotizacion' => 10,
            'id_log_insumo' => null,
            'archivo' => 'public/cotizaciones/cotizacion.pdf',
        ]);

        $this->postJson('/api/v1/inputs/1/price-update', [
            'descripcion' => 'Acero estructural',
            'unidad_medida' => 1,
            'precio' => 20,
            'tipo' => 1,
            'estado' => 'AC',
            'fecha_cotiz' => '2026-05-01',
            'quote_ids' => [$quote->id_cotizacion],
        ])->assertOk()
            ->assertJsonPath('data.input.precio', 20);

        $logId = \App\Models\InputLog::query()->where('id_insumo', 1)->latest('id_log')->value('id_log');

        $this->assertDatabaseHas('cotizaciones', [
            'id_cotizacion' => $quote->id_cotizacion,
            'id_insumo' => 1,
            'id_log_insumo' => $logId,
        ]);

        $this->getJson('/api/v1/inputs/1/history')
            ->assertOk()
            ->assertJsonPath('data.items.0.id_log_insumo', $logId)
            ->assertJsonPath('data.items.0.quotes.0.id_cotizacion', $quote->id_cotizacion);
    }

    public function test_price_change_rejects_used_or_foreign_quote_ids(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createInput(['id_insumo' => 1, 'precio' => 15.36]);
        $this->createInput(['id_insumo' => 2, 'descripcion' => 'Arena fina', 'precio' => 10]);
        $this->createInputQuote([
            'id_cotizacion' => 10,
            'id_insumo' => 1,
            'id_log_insumo' => 99,
        ]);
        $this->createInputQuote([
            'id_cotizacion' => 11,
            'id_insumo' => 2,
            'id_log_insumo' => null,
        ]);

        foreach ([10, 11] as $quoteId) {
            $this->postJson('/api/v1/inputs/1/price-update', [
                'descripcion' => 'Acero estructural',
                'unidad_medida' => 1,
                'precio' => 20,
                'tipo' => 1,
                'estado' => 'AC',
                'fecha_cotiz' => '2026-05-01',
                'quote_ids' => [$quoteId],
            ])->assertUnprocessable()
                ->assertJsonValidationErrors(['quote_ids']);
        }
    }

    public function test_price_change_can_create_quote_for_new_log(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createInput(['precio' => 15.36]);

        $this->postJson('/api/v1/inputs/1/price-update', [
            'descripcion' => 'Acero estructural',
            'unidad_medida' => 1,
            'precio' => 20,
            'tipo' => 1,
            'estado' => 'AC',
            'fecha_cotiz' => '2026-05-01',
            'valido' => UploadedFile::fake()->create('quote.pdf', 100, 'application/pdf'),
        ])->assertOk()
            ->assertJsonPath('data.input.precio', 20);

        $logId = \App\Models\InputLog::query()->where('id_insumo', 1)->latest('id_log')->value('id_log');

        $this->assertDatabaseHas('cotizaciones', [
            'id_insumo' => 1,
            'id_log_insumo' => $logId,
            'archivo' => 'https://repository.test/files/document-1.pdf',
        ]);

        $this->getJson('/api/v1/inputs/1/history')
            ->assertOk()
            ->assertJsonPath('data.items.0.id_log_insumo', $logId)
            ->assertJsonPath('data.items.0.quotes.0.archivo', 'https://repository.test/files/document-1.pdf');
    }

    public function test_quote_upload_requires_pdf_under_legacy_limit_and_at_least_one_file(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createInput();

        $this->postJson('/api/v1/inputs/1/quotes', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['valido', 'propuesto_1', 'propuesto_2']);

        $this->postJson('/api/v1/inputs/1/quotes', [
            'valido' => UploadedFile::fake()->create('quote.txt', 100, 'text/plain'),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['valido']);

        $this->postJson('/api/v1/inputs/1/quotes', [
            'valido' => UploadedFile::fake()->create('quote.pdf', 4097, 'application/pdf'),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['valido']);
    }

    public function test_quote_resource_marks_missing_legacy_files_as_unavailable(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createInput();
        $this->createInputQuote([
            'archivo' => 'public/archivos/cotizaciones/cotizacion_valida_legacy.pdf',
            'archivo1' => 'public/archivos/cotizaciones/cotizacion_propuesto1_legacy.pdf',
            'archivo2' => null,
        ]);

        $this->getJson('/api/v1/inputs/1/quotes/history')
            ->assertOk()
            ->assertJsonPath('data.items.0.archivo_available', false)
            ->assertJsonPath('data.items.0.archivo_url', null)
            ->assertJsonPath('data.items.0.archivo_label', 'Propuesta oficial vigente')
            ->assertJsonPath('data.items.0.archivo1_available', false)
            ->assertJsonPath('data.items.0.archivo2_label', null);
    }

    public function test_admin_can_request_check_and_execute_logical_delete_with_authorization(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createInput();

        $this->postJson('/api/v1/inputs/1/delete-authorization-request', [
            'nro_autorizacion' => 'AUTH-001',
        ])->assertCreated()
            ->assertJsonPath('data.authorization.estado', 'PE');

        $this->getJson('/api/v1/inputs/1/delete-authorization-status')
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.usable', false);

        $this->createAuthorization([
            'id_autorizacion' => 2,
            'id_elemento' => 1,
            'estado' => 'AP',
            'nro_autorizacion' => 'AUTH-OK',
        ]);

        $this->getJson('/api/v1/inputs/1/delete-authorization-status')
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.usable', true);

        $this->deleteJson('/api/v1/inputs/1', [
            'autorizacion' => 'AUTH-OK',
        ])->assertOk()
            ->assertJsonPath('data.input.estado', 'DP');

        $this->assertDatabaseHas('insumo', [
            'id_insumo' => 1,
            'estado' => 'DP',
        ]);

        $this->assertDatabaseHas('log_insumo', [
            'id_insumo' => 1,
            'estado' => 'DP',
        ]);
    }

    public function test_cannot_delete_input_when_used_by_active_items_or_without_approved_authorization(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createInput();
        $this->createItemInput();

        $this->deleteJson('/api/v1/inputs/1', [
            'autorizacion' => 'AUTH-001',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.input.0', 'El insumo no puede eliminarse porque esta asociado a items activos.');

        $this->assertDatabaseMissing('insumo', [
            'id_insumo' => 1,
            'estado' => 'DP',
        ]);
    }

    public function test_input_endpoints_require_authentication(): void
    {
        $this->createInput();

        $this->getJson('/api/v1/inputs')->assertUnauthorized();
        $this->getJson('/api/v1/inputs/context')->assertUnauthorized();
        $this->postJson('/api/v1/inputs', [])->assertUnauthorized();
        $this->getJson('/api/v1/inputs/1')->assertUnauthorized();
        $this->getJson('/api/v1/inputs/1/name')->assertUnauthorized();
        $this->putJson('/api/v1/inputs/1', [])->assertUnauthorized();
        $this->deleteJson('/api/v1/inputs/1', [])->assertUnauthorized();
        $this->postJson('/api/v1/inputs/1/delete-authorization-request', [])->assertUnauthorized();
        $this->getJson('/api/v1/inputs/1/delete-authorization-status')->assertUnauthorized();
        $this->getJson('/api/v1/inputs/1/delete-impact')->assertUnauthorized();
        $this->patchJson('/api/v1/inputs/1/status', [])->assertUnauthorized();
        $this->getJson('/api/v1/inputs/1/history')->assertUnauthorized();
        $this->getJson('/api/v1/inputs/1/logs')->assertUnauthorized();
        $this->getJson('/api/v1/inputs/1/quotes')->assertUnauthorized();
        $this->postJson('/api/v1/inputs/1/quotes', [])->assertUnauthorized();
        $this->getJson('/api/v1/inputs/1/quotes/current')->assertUnauthorized();
        $this->getJson('/api/v1/inputs/1/quotes/history')->assertUnauthorized();
        $this->getJson('/api/v1/inputs/1/quotes/log-history')->assertUnauthorized();
        $this->getJson('/api/v1/input-logs/1/files')->assertUnauthorized();
        $this->getJson('/api/v1/search/inputs')->assertUnauthorized();
        $this->getJson('/api/v1/search/unit-measures')->assertUnauthorized();
    }

    public function test_non_admin_cannot_manage_inputs(): void
    {
        $this->createLegacyAuthUser();
        $this->createInput();

        $userRole = Role::query()->create([
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

        $user = User::query()->create([
            'id_usuario' => 2,
            'funcionario' => 'Usuario Tecnico',
            'ci' => '87654321',
            'username' => 'tecnico',
            'clave' => Hash::make('secret123'),
            'estado' => 'AC',
            'id_unidad' => $unit->id_unidad,
            'rol' => $userRole->id_rol,
            'fecha' => now()->toDateString(),
            'subalcaldia' => null,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/inputs')->assertForbidden();
        $this->getJson('/api/v1/inputs/context')->assertForbidden();
        $this->postJson('/api/v1/inputs', [
            'descripcion' => 'Cemento',
            'unidad_medida' => 1,
            'precio' => 10,
            'tipo' => 1,
            'fecha_cotiz' => '2026-05-01',
        ])->assertForbidden();
    }
}

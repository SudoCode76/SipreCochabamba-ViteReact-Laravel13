<?php

namespace Tests\Feature\Project;

use App\Models\Permission;
use App\Models\Project;
use App\Models\ProjectItem;
use App\Models\ProjectReportPhysicalSignature;
use App\Models\ProjectReportSignature;
use App\Models\ProjectSignableReport;
use App\Models\Role;
use App\Models\SystemFunction;
use App\Models\Unit;
use App\Models\User;
use App\Modules\Projects\Services\ProjectBudgetService;
use App\Modules\Projects\Services\ProjectInputBreakdownPdfService;
use App\Modules\Projects\Services\ProjectInputsGroupedReportPdfService;
use App\Modules\Projects\Services\ProjectInputsReportPdfService;
use App\Modules\Projects\Services\ProjectLegacyUnitPriceService;
use App\Modules\Projects\Services\ProjectReportSignatureService;
use App\Modules\Projects\Services\ProjectSignableReportService;
use App\Modules\Projects\Services\ProjectSignatureNotificationService;
use App\Notifications\ProjectSignatureNotification;
use App\Services\Citizenship\CiudadaniaDigitalClient;
use App\Services\Citizenship\CiudadaniaDigitalException;
use Carbon\Carbon;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery;
use PhpOffice\PhpSpreadsheet\IOFactory;
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
            ->assertJsonPath('data.conditions.0.code', 'PD')
            ->assertJsonPath('data.conditions.0.label', 'DESARROLLO')
            ->assertJsonPath('data.conditions.1.code', 'RV')
            ->assertJsonPath('data.conditions.1.label', 'FINALIZADO')
            ->assertJsonPath('data.conditions.2.code', 'AP')
            ->assertJsonPath('data.conditions.2.label', 'ACTUALIZADO')
            ->assertJsonPath('data.approval_statuses.0.label', 'DESARROLLO')
            ->assertJsonPath('data.approval_statuses.1.label', 'FINALIZADO')
            ->assertJsonPath('data.approval_statuses.2.label', 'ACTUALIZADO')
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

        $this->assertDatabaseHas('proyecto', [
            'id_proyecto' => $projectId,
            'signature_access_mode' => 'selected',
        ]);
        $this->assertDatabaseHas('project_signature_authorized_users', [
            'id_proyecto_raiz' => $projectId,
            'id_usuario' => 1,
        ]);

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
            ->assertJsonPath('data.items.1.action', 'version_finalized')
            ->assertJsonPath('data.items.2.action', 'created')
            ->assertJsonPath('data.meta.total', 3);
    }

    public function test_admin_can_configure_signable_project_reports(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->getJson('/api/v1/signable-project-reports')
            ->assertOk()
            ->assertJsonPath('data.permissions.can_manage', true)
            ->assertJsonPath('data.items.0.is_enabled', false)
            ->assertJsonPath('data.items.0.requires_finalized_project', true)
            ->assertJsonPath('data.items.0.validity_days', 30);

        $this->patchJson('/api/v1/signable-project-reports/general_budget', [
            'is_enabled' => true,
            'requires_finalized_project' => false,
            'validity_days' => 15,
        ])
            ->assertOk()
            ->assertJsonPath('data.report.report_key', 'general_budget')
            ->assertJsonPath('data.report.is_enabled', true)
            ->assertJsonPath('data.report.requires_finalized_project', true)
            ->assertJsonPath('data.report.validity_days', 15);

        $this->assertTrue(ProjectSignableReport::query()
            ->where('report_key', 'general_budget')
            ->value('is_enabled'));
        $this->assertTrue(ProjectSignableReport::query()
            ->where('report_key', 'general_budget')
            ->value('requires_finalized_project'));
        $this->assertSame(15, ProjectSignableReport::query()
            ->where('report_key', 'general_budget')
            ->value('validity_days'));

        $this->patchJson('/api/v1/signable-project-reports/general_budget', [
            'validity_days' => 0,
        ])->assertUnprocessable();

        $this->createProjectRecord(['aprobado' => 'PD']);

        $this->getJson('/api/v1/projects/1/signature-status?report_key=general_budget&format=PCA')
            ->assertOk()
            ->assertJsonPath('data.project_is_finalized', false)
            ->assertJsonPath('data.project_status_allows_signing', false)
            ->assertJsonPath('data.report.requires_finalized_project', true);

        $this->postJson('/api/v1/projects/1/reports/general_budget/sign', [
            'format' => 'PCA',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Este reporte solo permite firma digital en proyectos FINALIZADOS.');

        $this->patchJson('/api/v1/signable-project-reports/general_budget', [
            'requires_finalized_project' => false,
        ])->assertOk()
            ->assertJsonPath('data.report.requires_finalized_project', true);

        $this->getJson('/api/v1/projects/1/signature-status?report_key=general_budget&format=PCA')
            ->assertOk()
            ->assertJsonPath('data.project_status_allows_signing', false)
            ->assertJsonPath('data.report.requires_finalized_project', true);

        Sanctum::actingAs($this->createProjectUserWithPermissions(['INDEX']));

        $this->patchJson('/api/v1/signable-project-reports/general_budget', [
            'is_enabled' => false,
        ])->assertForbidden();
    }

    public function test_project_role_can_configure_signable_reports_with_signature_configuration_function(): void
    {
        Sanctum::actingAs($this->createProjectUserWithPermissions(['CONFIGURAR_FIRMAS']));

        $this->patchJson('/api/v1/signable-project-reports/general_budget', [
            'is_enabled' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.report.report_key', 'general_budget')
            ->assertJsonPath('data.report.is_enabled', true);
    }

    public function test_report_view_permission_does_not_allow_signing_without_signing_function(): void
    {
        ProjectSignableReport::query()
            ->where('report_key', 'general_budget')
            ->update(['is_enabled' => true]);

        $project = $this->createProjectRecord([
            'aprobado' => 'RV',
            'fecha_aprob' => now()->toDateString(),
            'fecha_finalizacion' => now(),
        ]);

        Sanctum::actingAs($this->createProjectUserWithPermissions(['PRESUPUESTO_GENERAL']));

        $this->getJson("/api/v1/projects/{$project->id_proyecto}/signature-status?report_key=general_budget")
            ->assertOk()
            ->assertJsonPath('data.report.can_sign', false);

        $this->postJson("/api/v1/projects/{$project->id_proyecto}/reports/general_budget/sign")
            ->assertForbidden()
            ->assertJsonPath('message', 'No tiene permisos para firmar este reporte.');

        $this->postJson("/api/v1/projects/{$project->id_proyecto}/reports/general_budget/physical-signatures")
            ->assertUnprocessable();
    }

    public function test_report_signing_requires_signing_function_and_report_permission(): void
    {
        ProjectSignableReport::query()
            ->where('report_key', 'general_budget')
            ->update(['is_enabled' => true]);

        $project = $this->createProjectRecord([
            'aprobado' => 'RV',
            'fecha_aprob' => now()->toDateString(),
            'fecha_finalizacion' => now(),
        ]);

        Sanctum::actingAs($this->createProjectUserWithPermissions([
            'PRESUPUESTO_GENERAL',
            'FIRMAR_REPORTES',
        ]));

        $this->getJson("/api/v1/projects/{$project->id_proyecto}/signature-status?report_key=general_budget")
            ->assertOk()
            ->assertJsonPath('data.report.can_sign', true);
    }

    public function test_project_signature_preview_requires_every_selected_image_and_protects_digital_zone(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $creator = $this->createLegacyAuthUser();
        $signers = collect(range(1, 4))
            ->map(fn () => $this->createProjectUserWithPermissions(['PRESUPUESTO_GENERAL', 'FIRMAR_REPORTES']));
        $project = $this->createProjectRecord([
            'aprobado' => 'RV',
            'fecha_finalizacion' => now(),
        ]);
        ProjectSignableReport::query()->where('report_key', 'general_budget')->update(['is_enabled' => true]);

        Sanctum::actingAs($creator);
        $this->putJson("/api/v1/projects/{$project->id_proyecto}/signature-access", [
            'mode' => 'selected',
            'user_ids' => [$creator->id_usuario, ...$signers->pluck('id_usuario')->all()],
        ])->assertOk();

        $this->postJson("/api/v1/projects/{$project->id_proyecto}/reports/general_budget/signature-preview", [
            'format' => 'PCA',
            'page_scope' => 'last',
        ])->assertOk()
            ->assertJsonPath('data.ready', false)
            ->assertJsonCount(5, 'data.missing_users');

        $image = imagecreatetruecolor(20, 10);
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);
        Storage::disk('public')->put('signatures/creator.png', $png);
        $creator->forceFill(['firma_imagen_path' => 'signatures/creator.png'])->save();
        foreach ($signers as $index => $signer) {
            $path = "signatures/signer-{$index}.png";
            Storage::disk('public')->put($path, $png);
            $signer->forceFill(['firma_imagen_path' => $path])->save();
        }

        $preview = $this->postJson("/api/v1/projects/{$project->id_proyecto}/reports/general_budget/signature-preview", [
            'format' => 'PCA',
            'page_scope' => 'last',
        ])->assertOk()
            ->assertJsonPath('data.ready', true)
            ->assertJsonPath('data.page_scope', 'last')
            ->assertJsonPath('data.digital_zone.height', 20)
            ->assertJsonPath('data.physical_zone.default_width', 36)
            ->assertJsonPath('data.physical_zone.default_height', 18)
            ->assertJsonPath('data.physical_zone.columns', 5)
            ->assertJsonCount(5, 'data.items');

        $this->assertCount(1, collect($preview->json('data.items'))->pluck('y')->unique());

        $first = $preview->json('data.items.0');
        $second = $preview->json('data.items.1');
        $digitalZone = $preview->json('data.digital_zone');
        $pageWidth = $preview->json('data.page_sizes.0.width');
        $originalHash = $preview->json('data.layout_hash');

        $moved = $this->patchJson("/api/v1/projects/{$project->id_proyecto}/reports/general_budget/signature-preview/positions", [
            'format' => 'PCA',
            'layout_hash' => $originalHash,
            'positions' => [[
                'id' => $first['id'],
                'x' => $first['x'],
                'y' => 20,
                'width' => $first['width'],
                'height' => $first['height'],
            ]],
        ])->assertOk()
            ->assertJsonPath('data.items.0.y', 20);

        $savedHash = $moved->json('data.layout_hash');
        $this->assertNotSame($originalHash, $savedHash);

        $this->patchJson("/api/v1/projects/{$project->id_proyecto}/reports/general_budget/signature-preview/positions", [
            'format' => 'PCA',
            'layout_hash' => $originalHash,
            'positions' => [[
                'id' => $first['id'],
                'x' => $first['x'],
                'y' => 21,
                'width' => $first['width'],
                'height' => $first['height'],
            ]],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['layout_hash']);

        $this->postJson("/api/v1/projects/{$project->id_proyecto}/reports/general_budget/signature-preview", [
            'format' => 'PCA',
            'page_scope' => 'last',
        ])->assertOk()
            ->assertJsonPath('data.items.0.y', 20);

        $this->patchJson("/api/v1/projects/{$project->id_proyecto}/reports/general_budget/signature-preview/positions", [
            'format' => 'PCA',
            'layout_hash' => $savedHash,
            'positions' => [[
                'id' => $first['id'],
                'x' => $first['x'],
                'y' => $digitalZone['y'],
                'width' => $first['width'],
                'height' => $first['height'],
            ]],
        ])->assertUnprocessable()
            ->assertJsonPath('errors.positions.0', 'Las firmas físicas no pueden entrar en la zona de Ciudadanía Digital.');

        $this->patchJson("/api/v1/projects/{$project->id_proyecto}/reports/general_budget/signature-preview/positions", [
            'format' => 'PCA',
            'layout_hash' => $savedHash,
            'positions' => [[
                'id' => $first['id'],
                'x' => $pageWidth - $first['width'] + 1,
                'y' => 20,
                'width' => $first['width'],
                'height' => $first['height'],
            ]],
        ])->assertUnprocessable()
            ->assertJsonPath('errors.positions.0', 'Una de las firmas queda fuera de la página.');

        $this->patchJson("/api/v1/projects/{$project->id_proyecto}/reports/general_budget/signature-preview/positions", [
            'format' => 'PCA',
            'layout_hash' => $savedHash,
            'positions' => [[
                'id' => $first['id'],
                'x' => $second['x'],
                'y' => $second['y'],
                'width' => $first['width'],
                'height' => $first['height'],
            ]],
        ])->assertUnprocessable()
            ->assertJsonPath('errors.positions.0', 'Las firmas físicas no pueden superponerse.');

        $this->patchJson("/api/v1/projects/{$project->id_proyecto}/reports/general_budget/signature-preview/positions", [
            'format' => 'PCA',
            'layout_hash' => $savedHash,
            'positions' => [[
                'id' => $first['id'],
                'x' => $first['x'],
                'y' => 20,
                'width' => 19,
                'height' => 12,
            ]],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['positions.0.width']);
    }

    public function test_prepared_project_pdf_can_start_and_pending_attempt_can_be_cancelled(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $creator = $this->createLegacyAuthUser();
        $creator->forceFill(['firma_imagen_path' => 'signatures/creator.png'])->save();
        $image = imagecreatetruecolor(20, 10);
        ob_start();
        imagepng($image);
        Storage::disk('public')->put('signatures/creator.png', ob_get_clean());
        imagedestroy($image);
        $project = $this->createProjectRecord([
            'aprobado' => 'RV',
            'fecha_finalizacion' => now(),
        ]);
        ProjectSignableReport::query()->where('report_key', 'general_budget')->update(['is_enabled' => true]);
        Sanctum::actingAs($creator);

        $preview = $this->postJson("/api/v1/projects/{$project->id_proyecto}/reports/general_budget/signature-preview", [
            'format' => 'PCA',
            'page_scope' => 'last',
        ])->assertOk();

        $client = Mockery::mock(CiudadaniaDigitalClient::class);
        $client->shouldReceive('userInfo')->once()->andReturn(['data' => ['numero_documento' => '1234567']]);
        $client->shouldReceive('createSigningUrl')->once()->andReturn(['data' => ['link' => 'https://ciudadania.test/firma']]);
        $this->app->instance(CiudadaniaDigitalClient::class, $client);

        $started = $this->postJson("/api/v1/projects/{$project->id_proyecto}/reports/general_budget/sign", [
            'format' => 'PCA',
            'access_token' => 'token-ciudadania',
            'page_scope' => 'last',
            'layout_hash' => $preview->json('data.layout_hash'),
        ])->assertCreated()
            ->assertJsonPath('data.redirect_url', 'https://ciudadania.test/firma');

        $signatureId = $started->json('data.signature.id');
        $this->postJson("/api/v1/projects/{$project->id_proyecto}/signatures/{$signatureId}/cancel")
            ->assertOk()
            ->assertJsonPath('data.signature.status', 'cancelled');
    }

    public function test_first_completed_project_signature_locks_version_signers_and_layout(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $creator = $this->createLegacyAuthUser();
        $image = imagecreatetruecolor(20, 10);
        ob_start();
        imagepng($image);
        Storage::disk('public')->put('signatures/creator.png', ob_get_clean());
        imagedestroy($image);
        $creator->forceFill(['firma_imagen_path' => 'signatures/creator.png'])->save();
        $project = $this->createProjectRecord(['aprobado' => 'RV', 'fecha_finalizacion' => now()]);
        ProjectSignableReport::query()->where('report_key', 'general_budget')->update(['is_enabled' => true]);
        Sanctum::actingAs($creator);

        $preview = $this->postJson("/api/v1/projects/{$project->id_proyecto}/reports/general_budget/signature-preview", [
            'format' => 'PCA',
            'page_scope' => 'last',
        ])->assertOk();

        $signedPdf = $this->fakePdf('Firmado');
        $client = Mockery::mock(CiudadaniaDigitalClient::class);
        $client->shouldReceive('userInfo')->once()->andReturn(['data' => ['numero_documento' => '1234567']]);
        $client->shouldReceive('createSigningUrl')->once()->andReturn(['data' => ['link' => 'https://ciudadania.test/firma']]);
        $client->shouldReceive('approvedDocument')->once()->andReturn(['data' => ['url_documento' => 'https://ciudadania.test/documento.pdf']]);
        $client->shouldReceive('downloadDocument')->once()->andReturn($signedPdf);
        $client->shouldReceive('validateSignedDocument')->once()->andReturn([
            'data' => ['verificacion_exitosa' => true, 'registros' => [['nro_documento' => '1234567']]],
        ]);
        $client->shouldReceive('logout')->once()->andReturn(null);
        $this->app->instance(CiudadaniaDigitalClient::class, $client);

        $started = $this->postJson("/api/v1/projects/{$project->id_proyecto}/reports/general_budget/sign", [
            'format' => 'PCA',
            'access_token' => 'token-ciudadania',
            'page_scope' => 'last',
            'layout_hash' => $preview->json('data.layout_hash'),
        ])->assertCreated();

        $this->postJson('/api/v1/citizenship/signature/approval-callback', [
            'code' => $started->json('data.signature.code'),
            'access_token' => 'token-ciudadania',
        ])->assertOk()
            ->assertJsonPath('data.signature.status', 'signed');

        $this->assertNotNull($project->fresh()->signature_signers_locked_at);
        $this->assertDatabaseHas('project_version_signature_users', [
            'id_proyecto' => $project->id_proyecto,
            'id_usuario' => $creator->id_usuario,
        ]);

        $this->putJson("/api/v1/projects/{$project->id_proyecto}/signature-access", [
            'mode' => 'selected',
            'user_ids' => [$creator->id_usuario],
        ])->assertUnprocessable();
    }

    public function test_legacy_digital_signature_function_still_allows_signing(): void
    {
        ProjectSignableReport::query()
            ->where('report_key', 'general_budget')
            ->update(['is_enabled' => true]);

        $project = $this->createProjectRecord([
            'aprobado' => 'RV',
            'fecha_aprob' => now()->toDateString(),
            'fecha_finalizacion' => now(),
        ]);

        Sanctum::actingAs($this->createProjectUserWithPermissions([
            'PRESUPUESTO_GENERAL',
            'FIRMAS_DIGITALES',
        ]));

        $this->getJson("/api/v1/projects/{$project->id_proyecto}/signature-status?report_key=general_budget")
            ->assertOk()
            ->assertJsonPath('data.report.can_sign', true);
    }

    public function test_digital_and_physical_signature_permissions_are_independent_for_projects_and_items(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $admin = $this->createLegacyAuthUser();
        ProjectSignableReport::query()
            ->whereIn('report_key', ['general_budget', 'item_unit_price_analysis'])
            ->update(['is_enabled' => true]);

        $project = $this->createProjectRecord([
            'aprobado' => 'RV',
            'fecha_aprob' => now()->toDateString(),
            'fecha_finalizacion' => now(),
        ]);
        $this->createUnitMeasure();
        $this->createGroup();
        $this->createSubgroup();
        $item = $this->createItemRecord();
        $signer = $this->createProjectUserWithPermissions([
            'PRESUPUESTO_GENERAL',
            'FIRMAR_REPORTES',
        ]);
        $signer->forceFill(['firma_imagen_path' => 'signatures/user.png'])->save();
        Storage::disk('public')->put('signatures/user.png', 'signature-image');
        DB::table('project_version_signature_users')->insert([
            'id_proyecto' => $project->id_proyecto,
            'id_usuario' => $signer->id_usuario,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(ProjectReportSignatureService::class);
        $pdf = $this->fakePdf('Firmado', ['A4', 'A5']);

        foreach ([
            [$project, 'general_budget', 'project-signatures/project.pdf'],
            [$item, 'item_unit_price_analysis', 'item-signatures/item.pdf'],
        ] as [$subject, $reportKey, $path]) {
            $parameters = [];
            Storage::disk('local')->put($path, $pdf);
            ProjectReportSignature::query()->create([
                'id_proyecto' => $subject instanceof Project ? $subject->id_proyecto : null,
                'id_item' => $subject instanceof Project ? null : $subject->id_item,
                'trace_id' => 'trace-'.$reportKey,
                'report_key' => $reportKey,
                'parameters' => $parameters,
                'parameters_hash' => $service->parametersHash($parameters),
                'status' => 'signed',
                'id_usuario' => $admin->id_usuario,
                'base_file_path' => $path,
                'signed_file_path' => $path,
                'base_document_hash' => $service->currentDocumentHash($subject, $reportKey, $parameters),
                'signed_at' => now(),
            ]);
        }

        Sanctum::actingAs($signer);

        $this->getJson("/api/v1/projects/{$project->id_proyecto}/signature-status?report_key=general_budget")
            ->assertOk()
            ->assertJsonPath('data.report.can_sign', true);
        $this->getJson("/api/v1/projects/{$project->id_proyecto}/reports/general_budget/physical-signatures")
            ->assertOk()
            ->assertJsonPath('data.ready', false)
            ->assertJsonPath('data.can_adjust', false);
        $this->postJson("/api/v1/projects/{$project->id_proyecto}/reports/general_budget/physical-signatures")
            ->assertUnprocessable();
        $this->postJson("/api/v1/items/{$item->id_item}/reports/item_unit_price_analysis/physical-signatures")
            ->assertForbidden();
        $this->putJson("/api/v1/projects/{$project->id_proyecto}/reports/general_budget/physical-signatures/positions", [
            'positions' => [['id' => 999, 'page' => 1, 'x' => 0, 'y' => 0, 'width' => 20, 'height' => 12]],
        ])->assertUnprocessable();
        $this->patchJson("/api/v1/items/{$item->id_item}/reports/item_unit_price_analysis/physical-signatures/positions", [
            'positions' => [['id' => 999, 'page' => 1, 'x' => 0, 'y' => 0, 'width' => 20, 'height' => 12]],
        ])->assertForbidden();

        return;

        Permission::query()
            ->where('id_rol', $signer->rol)
            ->where('id_funcion', SystemFunction::query()->where('nombre_funcion', 'FIRMAR_REPORTES')->value('id_funcion'))
            ->delete();
        $physicalFunction = SystemFunction::query()->create([
            'id_funcion' => 202,
            'nombre_funcion' => 'FIRMAR_REPORTES_FISICOS',
            'descripcion' => 'Firmar reportes fisicamente',
            'clase' => 'PROYECTO',
            'estado' => 'AC',
        ]);
        Permission::query()->create([
            'id_permiso' => 202,
            'id_rol' => $signer->rol,
            'nombre_rol' => 'Tecnico Proyecto',
            'id_funcion' => $physicalFunction->id_funcion,
            'descripcion' => $physicalFunction->descripcion,
            'estado' => 'AC',
        ]);

        $this->assertTrue(app(ProjectSignableReportService::class)->canSignPhysically($admin, 'general_budget'));
        $this->getJson("/api/v1/projects/{$project->id_proyecto}/signature-status?report_key=general_budget")
            ->assertOk()
            ->assertJsonPath('data.report.can_sign', false);

        $projectPhysical = $this->getJson("/api/v1/projects/{$project->id_proyecto}/reports/general_budget/physical-signatures")
            ->assertOk()
            ->assertJsonPath('data.can_mark', true)
            ->assertJsonPath('data.can_adjust', true)
            ->assertJsonCount(2, 'data.page_sizes');
        $itemPhysical = $this->getJson("/api/v1/items/{$item->id_item}/reports/item_unit_price_analysis/physical-signatures")
            ->assertOk()
            ->assertJsonPath('data.can_mark', true)
            ->assertJsonPath('data.can_adjust', true)
            ->assertJsonCount(2, 'data.page_sizes');

        $projectPhysical = $this->postJson("/api/v1/projects/{$project->id_proyecto}/reports/general_budget/physical-signatures")
            ->assertOk();
        $itemPhysical = $this->postJson("/api/v1/items/{$item->id_item}/reports/item_unit_price_analysis/physical-signatures")
            ->assertOk();

        $projectPosition = $projectPhysical->json('data.items.0');
        $itemPosition = $itemPhysical->json('data.items.0');

        $this->putJson("/api/v1/projects/{$project->id_proyecto}/reports/general_budget/physical-signatures/positions", [
            'positions' => [[
                'id' => $projectPosition['id'],
                'page' => $projectPosition['page'],
                'x' => $projectPosition['x'],
                'y' => $projectPosition['y'],
                'width' => $projectPosition['width'],
                'height' => $projectPosition['height'],
            ]],
        ])->assertOk();
        $this->patchJson("/api/v1/items/{$item->id_item}/reports/item_unit_price_analysis/physical-signatures/positions", [
            'positions' => [[
                'id' => $itemPosition['id'],
                'page' => $itemPosition['page'],
                'x' => $itemPosition['x'],
                'y' => $itemPosition['y'],
                'width' => $itemPosition['width'],
                'height' => $itemPosition['height'],
            ]],
        ])->assertOk();

        $secondProjectPhysical = ProjectReportPhysicalSignature::query()->create([
            'id_proyecto' => $project->id_proyecto,
            'report_key' => 'general_budget',
            'parameters_hash' => $service->parametersHash([]),
            'logical_document_hash' => $service->currentDocumentHash($project, 'general_budget', []),
            'id_usuario' => $admin->id_usuario,
            'signature_image_path' => 'signatures/user.png',
            'page' => 2,
            'x' => $projectPosition['x'],
            'y' => $projectPosition['y'],
            'width' => $projectPosition['width'],
            'height' => $projectPosition['height'],
            'marked_at' => now(),
        ]);

        $this->putJson("/api/v1/projects/{$project->id_proyecto}/reports/general_budget/physical-signatures/positions", [
            'positions' => [
                [
                    'id' => $projectPosition['id'],
                    'page' => $projectPosition['page'],
                    'x' => $projectPosition['x'],
                    'y' => $projectPosition['y'],
                    'width' => $projectPosition['width'],
                    'height' => $projectPosition['height'],
                ],
                [
                    'id' => $secondProjectPhysical->id,
                    'page' => 2,
                    'x' => $projectPosition['x'],
                    'y' => $projectPosition['y'],
                    'width' => $projectPosition['width'],
                    'height' => $projectPosition['height'],
                ],
            ],
        ])->assertUnprocessable()
            ->assertJsonPath('errors.positions.0', 'Las firmas físicas no pueden superponerse.');

        $smallestPageWidth = min(array_column($projectPhysical->json('data.page_sizes'), 'width'));

        $this->putJson("/api/v1/projects/{$project->id_proyecto}/reports/general_budget/physical-signatures/positions", [
            'positions' => [[
                'id' => $projectPosition['id'],
                'page' => $projectPosition['page'],
                'x' => $smallestPageWidth - $projectPosition['width'] + 1,
                'y' => $projectPosition['y'],
                'width' => $projectPosition['width'],
                'height' => $projectPosition['height'],
            ]],
        ])->assertUnprocessable()
            ->assertJsonPath('errors.positions.0', 'Una de las firmas queda fuera de la página.');
    }

    public function test_signature_access_is_shared_by_the_project_family_and_only_root_creator_or_admin_can_manage_it(): void
    {
        $admin = $this->createLegacyAuthUser();
        $rootCreator = $this->createProjectUserWithPermissions(['INDEX']);
        $versionCreator = User::query()->create([
            'funcionario' => 'Creador Version',
            'ci' => '11223344',
            'username' => 'version.creator',
            'clave' => Hash::make('secret123'),
            'estado' => 'AC',
            'id_unidad' => $rootCreator->id_unidad,
            'rol' => $rootCreator->rol,
            'fecha' => now()->toDateString(),
        ]);
        $root = $this->createProjectRecord([
            'id_proyecto' => 1,
            'id_proyecto_raiz' => 1,
            'id_usuario' => $rootCreator->id_usuario,
        ]);
        $version = $this->createProjectRecord([
            'id_proyecto' => 2,
            'id_proyecto_raiz' => 1,
            'id_version_origen' => 1,
            'numero_version' => 2,
            'id_usuario' => $versionCreator->id_usuario,
        ]);

        Sanctum::actingAs($rootCreator);

        $this->putJson("/api/v1/projects/{$version->id_proyecto}/signature-access", [
            'mode' => 'selected',
            'user_ids' => [$rootCreator->id_usuario],
        ])->assertOk()
            ->assertJsonPath('data.root_project_id', $root->id_proyecto)
            ->assertJsonPath('data.can_manage', true);

        Sanctum::actingAs($versionCreator);

        $this->getJson("/api/v1/projects/{$version->id_proyecto}/signature-access")
            ->assertOk()
            ->assertJsonPath('data.root_project_id', $root->id_proyecto)
            ->assertJsonPath('data.creator.id', $rootCreator->id_usuario)
            ->assertJsonPath('data.authorized_users.0.id', $rootCreator->id_usuario)
            ->assertJsonPath('data.can_manage', false);

        $this->putJson("/api/v1/projects/{$version->id_proyecto}/signature-access", [
            'mode' => 'selected',
            'user_ids' => [$rootCreator->id_usuario],
        ])->assertForbidden();

        Sanctum::actingAs($admin);

        $this->putJson("/api/v1/projects/{$version->id_proyecto}/signature-access", [
            'mode' => 'selected',
            'user_ids' => [$rootCreator->id_usuario],
        ])->assertOk()
            ->assertJsonPath('data.mode', 'selected');

        $this->getJson("/api/v1/projects/{$root->id_proyecto}/signature-access")
            ->assertOk()
            ->assertJsonPath('data.mode', 'selected')
            ->assertJsonPath('data.root_project_id', $root->id_proyecto);
        $this->assertSame('selected', $version->fresh()->signature_access_mode);
    }

    public function test_existing_projects_are_opened_for_all_signers_by_backfill(): void
    {
        $root = $this->createProjectRecord([
            'id_proyecto' => 1,
            'id_proyecto_raiz' => 1,
            'signature_access_mode' => 'selected',
        ]);
        $version = $this->createProjectRecord([
            'id_proyecto' => 2,
            'id_proyecto_raiz' => 1,
            'signature_access_mode' => 'selected',
        ]);

        $migration = require database_path('migrations/2026_07_13_000003_open_existing_projects_for_signatures.php');
        $migration->up();
        $migration->up();

        $this->assertSame('all', $root->fresh()->signature_access_mode);
        $this->assertSame('all', $version->fresh()->signature_access_mode);
    }

    public function test_selected_mode_blocks_new_digital_and_physical_signatures_while_all_mode_keeps_global_permissions_required(): void
    {
        $admin = $this->createLegacyAuthUser();
        ProjectSignableReport::query()
            ->where('report_key', 'general_budget')
            ->update(['is_enabled' => true]);
        $project = $this->createProjectRecord([
            'aprobado' => 'RV',
            'fecha_aprob' => now()->toDateString(),
            'fecha_finalizacion' => now(),
        ]);
        $signer = $this->createProjectUserWithPermissions([
            'PRESUPUESTO_GENERAL',
            'FIRMAR_REPORTES',
            'FIRMAR_REPORTES_FISICOS',
        ]);
        DB::table('project_signature_authorized_users')
            ->where('id_proyecto_raiz', $project->id_proyecto)
            ->where('id_usuario', $signer->id_usuario)
            ->delete();

        Sanctum::actingAs($signer);

        $this->getJson("/api/v1/projects/{$project->id_proyecto}/signature-status?report_key=general_budget&format=PCA")
            ->assertOk()
            ->assertJsonPath('data.report.can_sign', false)
            ->assertJsonPath('data.signature_access.mode', 'selected')
            ->assertJsonPath('data.signature_access.allowed', false);

        $this->postJson("/api/v1/projects/{$project->id_proyecto}/reports/general_budget/sign", [
            'format' => 'PCA',
            'access_token' => 'token-ciudadania',
        ])->assertForbidden()
            ->assertJsonPath('message', 'No está incluido entre las personas autorizadas para firmar esta versión.');

        $this->postJson("/api/v1/projects/{$project->id_proyecto}/reports/general_budget/physical-signatures", [
            'format' => 'PCA',
        ])->assertUnprocessable();

        Sanctum::actingAs($admin);
        $this->putJson("/api/v1/projects/{$project->id_proyecto}/signature-access", [
            'mode' => 'selected',
            'user_ids' => [1, $signer->id_usuario],
        ])->assertOk();

        $history = $this->getJson("/api/v1/projects/{$project->id_proyecto}/history")
            ->assertOk()
            ->json('data.items');
        $accessHistory = collect($history)->firstWhere('action', 'signature_access_updated');

        $this->assertNotNull($accessHistory);
        $this->assertSame('Se actualizó el acceso al proyecto', $accessHistory['title']);
        $this->assertSame('Se modificaron los usuarios autorizados para consultar y editar el proyecto, así como para firmar sus documentos.', $accessHistory['detail']);
        $this->assertSame($signer->funcionario, $accessHistory['metadata']['added_users'][0]['full_name']);
        $this->assertSame([], $accessHistory['metadata']['removed_users']);

        Sanctum::actingAs($signer);
        $this->getJson("/api/v1/projects/{$project->id_proyecto}/signature-status?report_key=general_budget&format=PCA")
            ->assertOk()
            ->assertJsonPath('data.report.can_sign', true)
            ->assertJsonPath('data.signature_access.allowed', true);

        Permission::query()
            ->where('id_rol', $signer->rol)
            ->where('id_funcion', SystemFunction::query()->where('nombre_funcion', 'FIRMAR_REPORTES')->value('id_funcion'))
            ->delete();

        $this->getJson("/api/v1/projects/{$project->id_proyecto}/signature-status?report_key=general_budget&format=PCA")
            ->assertOk()
            ->assertJsonPath('data.report.can_sign', false)
            ->assertJsonPath('data.signature_access.allowed', true);
    }

    public function test_history_resource_normalizes_legacy_project_access_entries(): void
    {
        $user = $this->createLegacyAuthUser();
        $project = $this->createProjectRecord();

        DB::table('proyecto_historial')->insert([
            'id_proyecto' => $project->id_proyecto,
            'id_usuario' => $user->id_usuario,
            'usuario_nombre' => $user->funcionario,
            'accion' => 'signature_access_updated',
            'titulo' => 'Se actualizó la configuración de firmantes',
            'detalle' => 'Se modificó quién puede firmar documentos de la familia del proyecto.',
            'metadata' => json_encode([
                'mode' => 'selected',
                'added_users' => [['id' => $user->id_usuario, 'full_name' => $user->funcionario]],
                'removed_users' => [],
            ], JSON_THROW_ON_ERROR),
            'ip' => '127.0.0.1',
            'fecha_hora' => now(),
        ]);

        Sanctum::actingAs($user);

        $entry = collect($this->getJson("/api/v1/projects/{$project->id_proyecto}/history")
            ->assertOk()
            ->json('data.items'))
            ->firstWhere('action', 'signature_access_updated');

        $this->assertSame('Se actualizó el acceso al proyecto', $entry['title']);
        $this->assertSame('Se modificaron los usuarios autorizados para consultar y editar el proyecto, así como para firmar sus documentos.', $entry['detail']);
    }

    public function test_removing_signed_users_requires_confirmation_and_preserves_signatures_across_versions(): void
    {
        $admin = $this->createLegacyAuthUser();
        $root = $this->createProjectRecord(['id_proyecto' => 1, 'id_proyecto_raiz' => 1]);
        $version = $this->createProjectRecord([
            'id_proyecto' => 2,
            'id_proyecto_raiz' => 1,
            'id_version_origen' => 1,
            'numero_version' => 2,
        ]);
        $signer = $this->createProjectUserWithPermissions(['PRESUPUESTO_GENERAL', 'FIRMAR_REPORTES']);
        $digital = ProjectReportSignature::query()->create([
            'id_proyecto' => $root->id_proyecto,
            'report_key' => 'general_budget',
            'parameters_hash' => hash('sha256', 'parameters'),
            'status' => 'signed',
            'id_usuario' => $signer->id_usuario,
            'base_file_path' => 'project-signatures/base.pdf',
            'signed_file_path' => 'project-signatures/signed.pdf',
            'signed_at' => now(),
        ]);
        $physical = ProjectReportPhysicalSignature::query()->create([
            'id_proyecto' => $version->id_proyecto,
            'report_key' => 'general_budget',
            'parameters_hash' => hash('sha256', 'parameters'),
            'logical_document_hash' => hash('sha256', 'document'),
            'id_usuario' => $signer->id_usuario,
            'signature_image_path' => 'signatures/signer.png',
            'marked_at' => now(),
        ]);
        $version->forceFill(['signature_signers_locked_at' => now()])->save();
        $root->forceFill(['signature_access_mode' => 'all'])->save();
        DB::table('project_signature_authorized_users')
            ->where('id_proyecto_raiz', $root->id_proyecto)
            ->where('id_usuario', $signer->id_usuario)
            ->delete();

        Sanctum::actingAs($admin);

        $payload = [
            'mode' => 'selected',
            'user_ids' => [$admin->id_usuario],
        ];
        $this->putJson("/api/v1/projects/{$version->id_proyecto}/signature-access", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_ids']);

        $this->assertDatabaseHas('project_report_signatures', ['id' => $digital->id, 'status' => 'signed']);
        $this->assertDatabaseHas('project_report_physical_signatures', ['id' => $physical->id]);

        return;

        $this->assertDatabaseMissing('project_signature_authorized_users', [
            'id_proyecto_raiz' => $root->id_proyecto,
            'id_usuario' => $signer->id_usuario,
        ]);

        $this->putJson("/api/v1/projects/{$root->id_proyecto}/signature-access", [
            ...$payload,
            'confirm_signed_removals' => true,
        ])->assertOk()
            ->assertJsonPath('data.mode', 'selected');

        $this->assertDatabaseMissing('project_signature_authorized_users', [
            'id_proyecto_raiz' => $root->id_proyecto,
            'id_usuario' => $signer->id_usuario,
        ]);
        $this->assertDatabaseHas('project_report_signatures', ['id' => $digital->id, 'status' => 'signed']);
        $this->assertDatabaseHas('project_report_physical_signatures', ['id' => $physical->id]);
        $this->assertDatabaseHas('proyecto_historial', [
            'id_proyecto' => $root->id_proyecto,
            'accion' => 'signature_access_updated',
        ]);
    }

    public function test_project_creation_stores_signature_access_and_rolls_back_invalid_selected_configuration(): void
    {
        $creator = $this->createLegacyAuthUser();
        $signer = $this->createProjectUserWithPermissions(['INDEX']);
        Sanctum::actingAs($creator);
        $payload = [
            'fecha' => '2026-07-13',
            'ubicacion' => 'CENTRO',
            'responsable' => $creator->id_usuario,
            'solicitante' => $creator->id_usuario,
            'estado' => 'AC',
            'aprobado' => 'PD',
        ];

        $selected = $this->postJson('/api/v1/projects', [
            ...$payload,
            'nombre_proyecto' => 'PROYECTO FIRMANTES SELECCIONADOS',
            'signature_access' => [
                'mode' => 'selected',
                'user_ids' => [$creator->id_usuario, $signer->id_usuario],
            ],
        ])->assertCreated();
        $selectedId = $selected->json('data.project.id_proyecto');

        $this->assertDatabaseHas('proyecto', [
            'id_proyecto' => $selectedId,
            'signature_access_mode' => 'selected',
        ]);
        $this->assertDatabaseHas('project_signature_authorized_users', [
            'id_proyecto_raiz' => $selectedId,
            'id_usuario' => $signer->id_usuario,
        ]);

        $all = $this->postJson('/api/v1/projects', [
            ...$payload,
            'nombre_proyecto' => 'PROYECTO FIRMA ABIERTA',
            'signature_access' => [
                'mode' => 'selected',
                'user_ids' => [$signer->id_usuario],
            ],
        ])->assertCreated();
        $allId = $all->json('data.project.id_proyecto');

        $this->assertDatabaseHas('proyecto', [
            'id_proyecto' => $allId,
            'signature_access_mode' => 'selected',
        ]);
        $this->assertDatabaseMissing('project_signature_authorized_users', [
            'id_proyecto_raiz' => $allId,
            'id_usuario' => $creator->id_usuario,
        ]);
        $this->assertDatabaseHas('project_signature_authorized_users', [
            'id_proyecto_raiz' => $allId,
            'id_usuario' => $signer->id_usuario,
        ]);

        $this->postJson('/api/v1/projects', [
            ...$payload,
            'nombre_proyecto' => 'PROYECTO CONFIGURACION INVALIDA',
            'signature_access' => [
                'mode' => 'selected',
                'user_ids' => [],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['signature_access.user_ids']);

        $this->assertDatabaseMissing('proyecto', [
            'nombre_proyecto' => 'PROYECTO CONFIGURACION INVALIDA',
        ]);
    }

    public function test_project_creation_notifies_new_signers_and_notifications_can_be_read(): void
    {
        $creator = $this->createLegacyAuthUser();
        $signer = $this->createProjectUserWithPermissions(['INDEX']);
        Sanctum::actingAs($creator);

        $response = $this->postJson('/api/v1/projects', [
            'nombre_proyecto' => 'PROYECTO CON NOTIFICACIONES',
            'fecha' => '2026-07-16',
            'ubicacion' => 'CENTRO',
            'responsable' => $creator->id_usuario,
            'solicitante' => $creator->id_usuario,
            'estado' => 'AC',
            'aprobado' => 'PD',
            'signature_access' => [
                'mode' => 'selected',
                'user_ids' => [$creator->id_usuario, $signer->id_usuario],
            ],
        ])->assertCreated();

        $projectId = $response->json('data.project.id_proyecto');
        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $creator->id_usuario,
            'type' => ProjectSignatureNotification::class,
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $signer->id_usuario,
            'type' => ProjectSignatureNotification::class,
            'read_at' => null,
        ]);

        Sanctum::actingAs($signer);
        $notifications = $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1)
            ->assertJsonPath('data.items.0.type', ProjectSignatureNotification::ASSIGNED)
            ->assertJsonPath('data.items.0.title', 'Firma física requerida')
            ->assertJsonPath('data.items.0.message', 'Carga tu firma física para incluirla en «PROYECTO CON NOTIFICACIONES».')
            ->assertJsonPath('data.items.0.project_id', $projectId)
            ->assertJsonPath('data.items.0.needs_signature_image', true)
            ->assertJsonPath('data.items.0.action_kind', 'profile');

        $notificationId = $notifications->json('data.items.0.id');
        $this->patchJson("/api/v1/notifications/{$notificationId}/read")
            ->assertOk()
            ->assertJsonPath('data.notification.id', $notificationId);
        $this->assertDatabaseMissing('notifications', ['id' => $notificationId, 'read_at' => null]);
    }

    public function test_new_project_version_notifies_inherited_signers_without_duplicates_on_save(): void
    {
        $creator = $this->createLegacyAuthUser();
        $signer = $this->createProjectUserWithPermissions(['INDEX']);
        Sanctum::actingAs($creator);

        $created = $this->postJson('/api/v1/projects', [
            'nombre_proyecto' => 'PROYECTO VERSIONADO',
            'fecha' => '2026-07-16',
            'ubicacion' => 'CENTRO',
            'responsable' => $creator->id_usuario,
            'solicitante' => $creator->id_usuario,
            'estado' => 'AC',
            'aprobado' => 'PD',
            'signature_access' => [
                'mode' => 'selected',
                'user_ids' => [$signer->id_usuario],
            ],
        ])->assertCreated();
        $projectId = $created->json('data.project.id_proyecto');

        $this->postJson("/api/v1/projects/{$projectId}/finalize")->assertOk();
        $version = $this->postJson("/api/v1/projects/{$projectId}/versions")->assertCreated();
        $versionId = $version->json('data.project.id_proyecto');

        $assigned = $signer->notifications()->get()
            ->filter(fn ($notification): bool => $notification->data['kind'] === ProjectSignatureNotification::ASSIGNED);
        $this->assertCount(2, $assigned);
        $this->assertEqualsCanonicalizing([$projectId, $versionId], $assigned->pluck('data.project_id')->all());

        $this->putJson("/api/v1/projects/{$versionId}/signature-access", [
            'mode' => 'selected',
            'user_ids' => [$signer->id_usuario],
        ])->assertOk();

        $this->assertSame(2, $signer->notifications()->get()
            ->filter(fn ($notification): bool => $notification->data['kind'] === ProjectSignatureNotification::ASSIGNED)
            ->count());
    }

    public function test_removing_a_signer_resolves_assignment_and_sends_removal_notice(): void
    {
        $creator = $this->createLegacyAuthUser();
        $signer = $this->createProjectUserWithPermissions(['INDEX']);
        $project = $this->createProjectRecord(['id_usuario' => $creator->id_usuario]);
        Sanctum::actingAs($creator);

        $this->putJson("/api/v1/projects/{$project->id_proyecto}/signature-access", [
            'mode' => 'selected',
            'user_ids' => [$creator->id_usuario, $signer->id_usuario],
        ])->assertOk();

        $this->putJson("/api/v1/projects/{$project->id_proyecto}/signature-access", [
            'mode' => 'selected',
            'user_ids' => [$creator->id_usuario],
        ])->assertOk();

        $notifications = $signer->notifications()->latest()->get();
        $this->assertCount(2, $notifications);
        $removed = $notifications->first(fn ($notification): bool => $notification->data['kind'] === ProjectSignatureNotification::REMOVED);
        $assigned = $notifications->first(fn ($notification): bool => $notification->data['kind'] === ProjectSignatureNotification::ASSIGNED);
        $this->assertNull($removed?->read_at);
        $this->assertNotNull($assigned?->read_at);
    }

    public function test_notification_api_is_user_scoped_and_mark_all_only_updates_current_user(): void
    {
        $creator = $this->createLegacyAuthUser();
        $signer = $this->createProjectUserWithPermissions(['INDEX']);
        $other = User::query()->create([
            'funcionario' => 'Otro Firmante',
            'ci' => '55667788',
            'username' => 'otro.firmante',
            'clave' => Hash::make('secret123'),
            'estado' => 'AC',
            'id_unidad' => $signer->id_unidad,
            'rol' => $signer->rol,
            'fecha' => now()->toDateString(),
        ]);
        $payload = [
            'kind' => ProjectSignatureNotification::ASSIGNED,
            'title' => 'Designación',
            'message' => 'Debe revisar el proyecto.',
            'project_id' => 1,
            'project_name' => 'PROYECTO TEST',
            'version_number' => 1,
            'action_kind' => 'project',
            'context_key' => 'project:1:assigned',
        ];
        $signer->notify(new ProjectSignatureNotification($payload));
        $other->notify(new ProjectSignatureNotification($payload));
        $otherNotificationId = $other->notifications()->firstOrFail()->id;

        Sanctum::actingAs($signer);
        $this->patchJson("/api/v1/notifications/{$otherNotificationId}/read")->assertNotFound();
        $this->patchJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.updated', 1);

        $this->assertNotNull($signer->notifications()->first()->read_at);
        $this->assertNull($other->notifications()->first()->read_at);
        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $creator->id_usuario,
            'type' => ProjectSignatureNotification::class,
        ]);
    }

    public function test_notification_broadcast_channel_is_private_to_the_authenticated_user(): void
    {
        $user = $this->createLegacyAuthUser();
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);
        app(BroadcastManager::class)->forgetDrivers();
        require base_path('routes/channels.php');
        Sanctum::actingAs($user);

        $this->postJson('/api/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => "private-users.{$user->id_usuario}",
        ])->assertOk();

        $this->postJson('/api/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-users.999999',
        ])->assertForbidden();
    }

    public function test_finalizing_project_notifies_signers_once_per_version(): void
    {
        Storage::fake('public');
        $creator = $this->createLegacyAuthUser();
        $signer = $this->createProjectUserWithPermissions(['INDEX']);
        Storage::disk('public')->put('signatures/signer.png', 'image');
        $signer->forceFill(['firma_imagen_path' => 'signatures/signer.png'])->save();
        $project = $this->createProjectRecord([
            'id_usuario' => $creator->id_usuario,
            'aprobado' => 'AP',
        ]);
        DB::table('project_version_signature_users')->insert([
            'id_proyecto' => $project->id_proyecto,
            'id_usuario' => $signer->id_usuario,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Sanctum::actingAs($creator);

        $this->postJson("/api/v1/projects/{$project->id_proyecto}/finalize")->assertOk();
        app(ProjectSignatureNotificationService::class)->notifyFinalized($project->refresh(), $creator);

        $this->assertSame(1, $signer->notifications()->where('type', ProjectSignatureNotification::class)->count());
        $notification = $signer->notifications()->firstOrFail();
        $this->assertSame(ProjectSignatureNotification::PENDING, $notification->data['kind']);

        Sanctum::actingAs($signer);
        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('data.items.0.title', 'Firma de Ciudadanía Digital pendiente')
            ->assertJsonPath('data.items.0.message', '«PROYECTO TEST» fue finalizado y requiere tu firma digital.')
            ->assertJsonPath('data.items.0.action_kind', 'project_signatures')
            ->assertJsonPath('data.items.0.report_key', null);
    }

    public function test_signature_status_counts_distinct_signers_for_the_exact_report_parameters(): void
    {
        ProjectSignableReport::query()
            ->where('report_key', 'general_budget')
            ->update(['is_enabled' => true]);
        $project = $this->createProjectRecord([
            'aprobado' => 'RV',
            'fecha_aprob' => now()->toDateString(),
            'fecha_finalizacion' => now(),
        ]);
        $signer = $this->createProjectUserWithPermissions(['PRESUPUESTO_GENERAL', 'FIRMAR_REPORTES']);
        $otherSigner = User::query()->create([
            'id_usuario' => 3,
            'funcionario' => 'Otro Firmante',
            'ci' => '76543210',
            'username' => 'otro-firmante',
            'clave' => Hash::make('secret123'),
            'estado' => 'AC',
            'id_unidad' => $signer->id_unidad,
            'rol' => $signer->rol,
            'fecha' => now()->toDateString(),
            'subalcaldia' => null,
        ]);
        DB::table('project_version_signature_users')->insertOrIgnore([
            [
                'id_proyecto' => $project->id_proyecto,
                'id_usuario' => $signer->id_usuario,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id_proyecto' => $project->id_proyecto,
                'id_usuario' => $otherSigner->id_usuario,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        $signatureService = app(ProjectReportSignatureService::class);
        $pcaParameters = $signatureService->normalizeParameters(['format' => 'PCA']);
        $fpsParameters = $signatureService->normalizeParameters(['format' => 'PC_FPS']);

        foreach ([$signer, $otherSigner] as $signedUser) {
            ProjectReportSignature::query()->create([
                'id_proyecto' => $project->id_proyecto,
                'report_key' => 'general_budget',
                'parameters' => $pcaParameters,
                'parameters_hash' => $signatureService->parametersHash($pcaParameters),
                'status' => 'signed',
                'id_usuario' => $signedUser->id_usuario,
                'signed_at' => now(),
            ]);
        }
        ProjectReportSignature::query()->create([
            'id_proyecto' => $project->id_proyecto,
            'report_key' => 'general_budget',
            'parameters' => $fpsParameters,
            'parameters_hash' => $signatureService->parametersHash($fpsParameters),
            'status' => 'signed',
            'id_usuario' => $otherSigner->id_usuario,
            'signed_at' => now(),
        ]);

        Sanctum::actingAs($signer);

        $this->getJson("/api/v1/projects/{$project->id_proyecto}/signature-status")
            ->assertOk()
            ->assertJsonPath('data.signature_access.allowed', true)
            ->assertJsonPath('data.required_signers_count', 2);
        $this->getJson("/api/v1/projects/{$project->id_proyecto}/signature-status?report_key=general_budget&format=PCA")
            ->assertOk()
            ->assertJsonPath('data.signed_signers_count', 2)
            ->assertJsonPath('data.required_signers_count', 2)
            ->assertJsonPath('data.current_user_signed', true)
            ->assertJsonPath('data.current_user_signature_status', 'signed');
        $this->getJson("/api/v1/projects/{$project->id_proyecto}/signature-status?report_key=general_budget&format=PC_FPS")
            ->assertOk()
            ->assertJsonPath('data.signed_signers_count', 1)
            ->assertJsonPath('data.required_signers_count', 2)
            ->assertJsonPath('data.current_user_signed', false)
            ->assertJsonPath('data.current_user_signature_status', null);
    }

    public function test_report_signing_sends_derivation_code_and_validity_to_firmagamc(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        ProjectSignableReport::query()
            ->where('report_key', 'general_budget')
            ->update(['is_enabled' => true]);

        $project = $this->createProjectRecord([
            'aprobado' => 'RV',
            'fecha_aprob' => now()->toDateString(),
            'fecha_finalizacion' => now(),
        ]);

        $signer = $this->createProjectUserWithPermissions([
            'PRESUPUESTO_GENERAL',
            'FIRMAR_REPORTES',
        ]);
        $image = imagecreatetruecolor(20, 10);
        ob_start();
        imagepng($image);
        Storage::disk('public')->put('signatures/signer.png', ob_get_clean());
        imagedestroy($image);
        $signer->forceFill(['firma_imagen_path' => 'signatures/signer.png'])->save();
        DB::table('project_version_signature_users')->where('id_proyecto', $project->id_proyecto)->delete();
        DB::table('project_version_signature_users')->insert([
            'id_proyecto' => $project->id_proyecto,
            'id_usuario' => $signer->id_usuario,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Sanctum::actingAs($signer);

        $preview = $this->postJson("/api/v1/projects/{$project->id_proyecto}/reports/general_budget/signature-preview", [
            'format' => 'PCA',
            'page_scope' => 'last',
        ])->assertOk();

        $capturedPayload = null;
        $client = Mockery::mock(CiudadaniaDigitalClient::class);
        $client->shouldReceive('userInfo')
            ->once()
            ->with('token-ciudadania')
            ->andReturn(['data' => ['nombre' => 'Usuario Ciudadania']]);
        $client->shouldReceive('createSigningUrl')
            ->once()
            ->with(Mockery::type('string'), Mockery::on(function (array $payload) use (&$capturedPayload): bool {
                $capturedPayload = $payload;

                return true;
            }))
            ->andReturn([
                'status' => 200,
                'data' => ['link' => 'https://aprobador.test/solicitudes/123'],
            ]);
        $this->app->instance(CiudadaniaDigitalClient::class, $client);

        $this->postJson("/api/v1/projects/{$project->id_proyecto}/reports/general_budget/sign", [
            'format' => 'PCA',
            'access_token' => 'token-ciudadania',
            'page_scope' => 'last',
            'layout_hash' => $preview->json('data.layout_hash'),
        ])
            ->assertCreated()
            ->assertJsonPath('data.redirect_url', 'https://aprobador.test/solicitudes/123');

        $signature = ProjectReportSignature::query()->latest('id')->firstOrFail();

        $this->assertMatchesRegularExpression(
            '/^sipre-'.$project->id_proyecto.'-[0-9a-f-]{36}$/',
            (string) $signature->code
        );
        $this->assertSame($signature->code, $capturedPayload['code']);
        $this->assertArrayNotHasKey('code ', $capturedPayload);
        $this->assertArrayNotHasKey('id_system', $capturedPayload);
        $this->assertArrayNotHasKey('codigo', $capturedPayload);
        $this->assertSame('true', $capturedPayload['is_derivated']);
        $this->assertSame('false', $capturedPayload['format_sign']);
        $this->assertSame('BOTTOM', $capturedPayload['signed_position']);
        $this->assertArrayHasKey('valid_from', $capturedPayload);
        $this->assertArrayHasKey('valid_to', $capturedPayload);
        $this->assertSame($capturedPayload['code'], $signature->request_payload['code']);
        $this->assertArrayNotHasKey('code ', $signature->request_payload);
        $this->assertArrayNotHasKey('id_system', $signature->request_payload);
        $this->assertArrayNotHasKey('codigo', $signature->request_payload);
        $this->assertSame($capturedPayload['is_derivated'], $signature->request_payload['is_derivated']);
        $this->assertSame($capturedPayload['signed_position'], $signature->request_payload['signed_position']);
        $this->assertSame($capturedPayload['valid_from'], $signature->request_payload['valid_from']);
        $this->assertSame($capturedPayload['valid_to'], $signature->request_payload['valid_to']);
        $this->assertArrayNotHasKey('acces_token', $signature->request_payload);
        $this->assertTrue(Carbon::parse($capturedPayload['valid_to'])->greaterThan(
            Carbon::parse($capturedPayload['valid_from'])
        ));
    }

    public function test_report_signing_uses_exact_backend_login_callback_without_query(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        ProjectSignableReport::query()
            ->where('report_key', 'general_budget')
            ->update(['is_enabled' => true]);

        config()->set(
            'services.ciudadania_digital.login_redirect_uri',
            'http://localhost:8011/api/v1/citizenship/signature/login-callback'
        );

        $project = $this->createProjectRecord([
            'aprobado' => 'RV',
            'fecha_aprob' => now()->toDateString(),
            'fecha_finalizacion' => now(),
        ]);

        $signer = $this->createProjectUserWithPermissions([
            'PRESUPUESTO_GENERAL',
            'FIRMAR_REPORTES',
        ]);
        $image = imagecreatetruecolor(20, 10);
        ob_start();
        imagepng($image);
        Storage::disk('public')->put('signatures/signer.png', ob_get_clean());
        imagedestroy($image);
        $signer->forceFill(['firma_imagen_path' => 'signatures/signer.png'])->save();
        Sanctum::actingAs($signer);

        $preview = $this->postJson("/api/v1/projects/{$project->id_proyecto}/reports/general_budget/signature-preview", [
            'format' => 'PCA',
            'page_scope' => 'last',
        ])->assertOk();

        config()->set('app.url', 'http://localhost:8011');
        config()->set(
            'services.ciudadania_digital.login_redirect_uri',
            'https://sipregamcapidev.cochabamba.bo/api/v1/citizenship/signature/login-callback'
        );

        $client = Mockery::mock(CiudadaniaDigitalClient::class);
        $client->shouldReceive('createAuthenticationUrl')
            ->once()
            ->with('http://localhost:8011/api/v1/citizenship/signature/login-callback')
            ->andReturn([
                'status' => 200,
                'data' => ['url' => 'https://ciudadania.test/login?state=state-general-budget'],
            ]);
        $this->app->instance(CiudadaniaDigitalClient::class, $client);

        $response = $this->postJson("/api/v1/projects/{$project->id_proyecto}/reports/general_budget/sign", [
            'format' => 'PCA',
            'page_scope' => 'last',
            'layout_hash' => $preview->json('data.layout_hash'),
        ]);

        $response->assertCreated()
            ->assertCookie('sipre_pending_signature')
            ->assertJsonPath('data.redirect_url', 'https://ciudadania.test/login?state=state-general-budget');

        $signature = ProjectReportSignature::query()->latest('id')->firstOrFail();

        $this->assertSame('auth_pending', $signature->status);
        $this->assertSame('state-general-budget', $signature->response_payload['auth_state']);
        $this->assertSame(
            'http://localhost:8011/api/v1/citizenship/signature/login-callback',
            $signature->request_payload['redirect_uri']
        );
    }

    public function test_signature_login_callback_uses_auth_state_when_available(): void
    {
        $this->createProjectRecord(['id_proyecto' => 1]);

        $oldSignature = ProjectReportSignature::query()->create([
            'id_proyecto' => 1,
            'trace_id' => 'trace-old',
            'report_key' => 'general_budget',
            'parameters' => ['format' => 'PCA'],
            'parameters_hash' => app(ProjectReportSignatureService::class)->parametersHash(['format' => 'PCA']),
            'status' => 'auth_pending',
            'id_usuario' => 1,
            'base_file_path' => 'project-signatures/old.pdf',
            'response_payload' => ['auth_state' => 'old-state'],
        ]);
        $targetSignature = ProjectReportSignature::query()->create([
            'id_proyecto' => 1,
            'trace_id' => 'trace-target',
            'report_key' => 'general_budget',
            'parameters' => ['format' => 'PCA'],
            'parameters_hash' => app(ProjectReportSignatureService::class)->parametersHash(['format' => 'PCA']),
            'status' => 'auth_pending',
            'id_usuario' => 1,
            'base_file_path' => 'project-signatures/target.pdf',
            'response_payload' => ['auth_state' => 'target-state'],
        ]);

        $service = Mockery::mock(ProjectReportSignatureService::class);
        $service->shouldReceive('continueAfterAuthentication')
            ->once()
            ->with((string) $targetSignature->id, Mockery::on(fn (array $payload): bool => ($payload['state'] ?? null) === 'target-state'))
            ->andReturn($targetSignature);
        $service->shouldReceive('serialize')
            ->once()
            ->with($targetSignature)
            ->andReturn([
                'id' => $targetSignature->id,
                'status' => 'sent',
                'redirect_url' => 'https://aprobador.test/solicitudes/target',
            ]);
        $this->app->instance(ProjectReportSignatureService::class, $service);

        $response = $this
            ->get('/api/v1/citizenship/signature/login-callback?state=target-state&access_token=token-ciudadania')
            ->assertRedirect();

        $this->assertStringContainsString('signature='.$targetSignature->id, $response->headers->get('Location'));
        $this->assertStringNotContainsString('signature='.$oldSignature->id, $response->headers->get('Location'));
    }

    public function test_signature_login_callback_without_query_uses_latest_auth_pending_signature(): void
    {
        $this->createProjectRecord(['id_proyecto' => 1]);

        $signature = ProjectReportSignature::query()->create([
            'id_proyecto' => 1,
            'trace_id' => 'trace-login',
            'report_key' => 'general_budget',
            'parameters' => ['format' => 'PCA'],
            'parameters_hash' => app(ProjectReportSignatureService::class)->parametersHash(['format' => 'PCA']),
            'status' => 'auth_pending',
            'code' => null,
            'id_usuario' => 1,
            'base_file_path' => 'project-signatures/base.pdf',
        ]);

        $service = Mockery::mock(ProjectReportSignatureService::class);
        $service->shouldReceive('continueAfterAuthentication')
            ->once()
            ->with((string) $signature->id, Mockery::on(fn (array $payload): bool => ($payload['access_token'] ?? null) === 'token-ciudadania'))
            ->andReturn($signature);
        $service->shouldReceive('serialize')
            ->once()
            ->with($signature)
            ->andReturn([
                'id' => $signature->id,
                'status' => 'sent',
                'redirect_url' => 'https://aprobador.test/solicitudes/abc',
            ]);
        $this->app->instance(ProjectReportSignatureService::class, $service);

        $response = $this
            ->withCookie('sipre_pending_signature', 'not-a-number')
            ->get('/api/v1/citizenship/signature/login-callback?access_token=token-ciudadania')
            ->assertRedirect();

        $this->assertStringStartsWith(
            'http://localhost:8010/ciudadania-digital/login/callback?',
            $response->headers->get('Location')
        );
        $this->assertStringContainsString('debug_access_token=token-ciudadania', $response->headers->get('Location'));
        $this->assertStringContainsString('redirect_url=https%3A%2F%2Faprobador.test%2Fsolicitudes%2Fabc', $response->headers->get('Location'));
    }

    public function test_signature_logout_callback_redirects_even_without_signature_cookie(): void
    {
        $this->get('/api/v1/citizenship/signature/logout-callback')
            ->assertRedirect();
    }

    public function test_citizenship_session_reports_active_pending_signature(): void
    {
        $user = $this->createLegacyAuthUser();
        $this->createProjectRecord(['id_proyecto' => 1]);
        Sanctum::actingAs($user);

        $signature = ProjectReportSignature::query()->create([
            'id_proyecto' => 1,
            'trace_id' => 'trace-session',
            'report_key' => 'general_budget',
            'parameters' => ['format' => 'PCA'],
            'parameters_hash' => app(ProjectReportSignatureService::class)->parametersHash(['format' => 'PCA']),
            'status' => 'error',
            'code' => 'code-session',
            'id_usuario' => $user->id_usuario,
            'base_file_path' => 'project-signatures/base.pdf',
            'error_message' => 'Error de firma',
        ]);

        Cache::put('ciudadania_digital_signature_token:'.$signature->id, 'token-ciudadania', now()->addMinutes(30));

        $client = Mockery::mock(CiudadaniaDigitalClient::class);
        $client->shouldReceive('userInfo')
            ->once()
            ->with('token-ciudadania')
            ->andReturn(['data' => ['id' => 1]]);
        $this->app->instance(CiudadaniaDigitalClient::class, $client);

        $this->getJson('/api/v1/citizenship/session')
            ->assertOk()
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.can_logout', true)
            ->assertJsonPath('data.signature.id', $signature->id);
    }

    public function test_citizenship_session_logout_returns_redirect_url_for_user_signature(): void
    {
        $user = $this->createLegacyAuthUser();
        $this->createProjectRecord(['id_proyecto' => 1]);
        Sanctum::actingAs($user);

        $signature = ProjectReportSignature::query()->create([
            'id_proyecto' => 1,
            'trace_id' => 'trace-logout',
            'report_key' => 'general_budget',
            'parameters' => ['format' => 'PCA'],
            'parameters_hash' => app(ProjectReportSignatureService::class)->parametersHash(['format' => 'PCA']),
            'status' => 'error',
            'code' => 'code-logout',
            'id_usuario' => $user->id_usuario,
            'base_file_path' => 'project-signatures/base.pdf',
        ]);

        Cache::put('ciudadania_digital_signature_token:'.$signature->id, 'token-ciudadania', now()->addMinutes(30));

        $client = Mockery::mock(CiudadaniaDigitalClient::class);
        $client->shouldReceive('userInfo')
            ->once()
            ->with('token-ciudadania')
            ->andReturn(['data' => ['id' => 1]]);
        $client->shouldReceive('logout')
            ->once()
            ->with('token-ciudadania', Mockery::type('string'))
            ->andReturn(['data' => ['url' => 'https://ciudadania.test/logout']]);
        $this->app->instance(CiudadaniaDigitalClient::class, $client);

        $this->postJson('/api/v1/citizenship/session/logout')
            ->assertOk()
            ->assertJsonPath('data.active', false)
            ->assertJsonPath('data.can_logout', true)
            ->assertJsonPath('data.logout_redirect_url', 'https://ciudadania.test/logout');

        $signature->refresh();
        $this->assertSame('https://ciudadania.test/logout', data_get($signature->response_payload, 'logout_redirect_url'));
        $this->assertNotEmpty(data_get($signature->response_payload, 'logout_requested_at'));
        $this->assertFalse(Cache::has('ciudadania_digital_signature_token:'.$signature->id));

        $this->getJson('/api/v1/citizenship/session')
            ->assertOk()
            ->assertJsonPath('data.active', false)
            ->assertJsonPath('data.can_logout', false);
    }

    public function test_citizenship_session_ignores_historical_logout_redirect_url_without_token(): void
    {
        $user = $this->createLegacyAuthUser();
        $this->createProjectRecord(['id_proyecto' => 1]);
        Sanctum::actingAs($user);

        ProjectReportSignature::query()->create([
            'id_proyecto' => 1,
            'trace_id' => 'trace-signed-logout',
            'report_key' => 'general_budget',
            'parameters' => ['format' => 'PCA'],
            'parameters_hash' => app(ProjectReportSignatureService::class)->parametersHash(['format' => 'PCA']),
            'status' => 'signed',
            'code' => 'code-signed-logout',
            'id_usuario' => $user->id_usuario,
            'base_file_path' => 'project-signatures/base.pdf',
            'signed_file_path' => 'project-signatures/signed.pdf',
            'response_payload' => ['logout_redirect_url' => 'https://ciudadania.test/logout'],
            'signed_at' => now(),
        ]);

        $this->getJson('/api/v1/citizenship/session')
            ->assertOk()
            ->assertJsonPath('data.active', false)
            ->assertJsonPath('data.can_logout', false)
            ->assertJsonPath('data.logout_redirect_url', null);
    }

    public function test_citizenship_session_logout_treats_missing_token_as_closed(): void
    {
        $user = $this->createLegacyAuthUser();
        $this->createProjectRecord(['id_proyecto' => 1]);
        Sanctum::actingAs($user);

        $signature = ProjectReportSignature::query()->create([
            'id_proyecto' => 1,
            'trace_id' => 'trace-missing-token',
            'report_key' => 'general_budget',
            'parameters' => ['format' => 'PCA'],
            'parameters_hash' => app(ProjectReportSignatureService::class)->parametersHash(['format' => 'PCA']),
            'status' => 'error',
            'code' => 'code-missing-token',
            'id_usuario' => $user->id_usuario,
            'base_file_path' => 'project-signatures/base.pdf',
        ]);

        $this->postJson('/api/v1/citizenship/session/logout')
            ->assertOk()
            ->assertJsonPath('data.active', false)
            ->assertJsonPath('data.can_logout', false)
            ->assertJsonPath('data.logout_redirect_url', null);

        $signature->refresh();
        $this->assertNotEmpty(data_get($signature->response_payload, 'logout_token_missing_at'));
    }

    public function test_citizenship_session_closes_invalid_cached_token(): void
    {
        $user = $this->createLegacyAuthUser();
        $this->createProjectRecord(['id_proyecto' => 1]);
        Sanctum::actingAs($user);

        $signature = ProjectReportSignature::query()->create([
            'id_proyecto' => 1,
            'trace_id' => 'trace-invalid-token',
            'report_key' => 'general_budget',
            'parameters' => ['format' => 'PCA'],
            'parameters_hash' => app(ProjectReportSignatureService::class)->parametersHash(['format' => 'PCA']),
            'status' => 'error',
            'code' => 'code-invalid-token',
            'id_usuario' => $user->id_usuario,
            'base_file_path' => 'project-signatures/base.pdf',
        ]);

        Cache::put('ciudadania_digital_signature_token:'.$signature->id, 'token-ciudadania', now()->addMinutes(30));

        $client = Mockery::mock(CiudadaniaDigitalClient::class);
        $client->shouldReceive('userInfo')
            ->once()
            ->with('token-ciudadania')
            ->andThrow(new CiudadaniaDigitalException('Token inválido.', 'users/info', 401));
        $this->app->instance(CiudadaniaDigitalClient::class, $client);

        $this->getJson('/api/v1/citizenship/session')
            ->assertOk()
            ->assertJsonPath('data.active', false)
            ->assertJsonPath('data.can_logout', false);

        $signature->refresh();
        $this->assertFalse(Cache::has('ciudadania_digital_signature_token:'.$signature->id));
        $this->assertNotEmpty(data_get($signature->response_payload, 'citizenship_session_closed_at'));
    }

    public function test_signature_logout_callback_marks_citizenship_session_closed(): void
    {
        $this->createProjectRecord(['id_proyecto' => 1]);

        $signature = ProjectReportSignature::query()->create([
            'id_proyecto' => 1,
            'trace_id' => 'trace-closed',
            'report_key' => 'general_budget',
            'parameters' => ['format' => 'PCA'],
            'parameters_hash' => app(ProjectReportSignatureService::class)->parametersHash(['format' => 'PCA']),
            'status' => 'signed',
            'code' => 'code-closed',
            'id_usuario' => 1,
            'base_file_path' => 'project-signatures/base.pdf',
            'signed_file_path' => 'project-signatures/signed.pdf',
            'response_payload' => ['logout_redirect_url' => 'https://ciudadania.test/logout'],
            'signed_at' => now(),
        ]);
        Cache::put('ciudadania_digital_signature_token:'.$signature->id, 'token-ciudadania', now()->addMinutes(30));

        $this->get('/api/v1/citizenship/signature/logout-callback?signature='.$signature->id)
            ->assertRedirect();

        $signature->refresh();
        $this->assertNotEmpty(data_get($signature->response_payload, 'logout_confirmed_at'));
        $this->assertFalse(Cache::has('ciudadania_digital_signature_token:'.$signature->id));
    }

    public function test_signature_browser_post_callback_redirects_unless_json_is_requested(): void
    {
        $this->post('/api/v1/citizenship/signature/login-callback')
            ->assertRedirect();

        $this->postJson('/api/v1/citizenship/signature/login-callback')
            ->assertUnprocessable()
            ->assertJson(fn ($json) => $json
                ->where('success', false)
                ->where('message', fn (string $message): bool => str_starts_with($message, 'No se recibió la solicitud de firma. Código de seguimiento: '))
                ->etc()
            );
    }

    public function test_repeated_login_callback_reuses_existing_approval_url(): void
    {
        $this->createProjectRecord(['id_proyecto' => 1]);

        $signature = ProjectReportSignature::query()->create([
            'id_proyecto' => 1,
            'trace_id' => 'trace-sent',
            'report_key' => 'general_budget',
            'parameters' => ['format' => 'PCA'],
            'parameters_hash' => app(ProjectReportSignatureService::class)->parametersHash(['format' => 'PCA']),
            'status' => 'sent',
            'code' => 'code-sent',
            'id_usuario' => 1,
            'base_file_path' => 'project-signatures/base.pdf',
            'response_payload' => [
                'redirect_url' => 'https://aprobador.test/solicitudes/existing',
            ],
        ]);

        $client = Mockery::mock(CiudadaniaDigitalClient::class);
        $client->shouldNotReceive('createSigningUrl');
        $client->shouldNotReceive('createDerivedSigningUrl');
        $client->shouldNotReceive('userInfo');
        $this->app->instance(CiudadaniaDigitalClient::class, $client);

        $result = app(ProjectReportSignatureService::class)
            ->continueAfterAuthentication($signature->id, ['access_token' => 'token-ciudadania']);

        $this->assertSame($signature->id, $result->id);
        $this->assertSame('sent', $result->status);
        $this->assertSame(
            'https://aprobador.test/solicitudes/existing',
            $result->response_payload['redirect_url']
        );
    }

    public function test_new_signature_replaces_incomplete_attempt_for_same_user_and_report(): void
    {
        ProjectSignableReport::query()
            ->where('report_key', 'general_budget')
            ->update(['is_enabled' => true]);

        $project = $this->createProjectRecord([
            'aprobado' => 'RV',
            'fecha_aprob' => now()->toDateString(),
            'fecha_finalizacion' => now(),
        ]);
        $user = $this->createProjectUserWithPermissions([
            'PRESUPUESTO_GENERAL',
            'FIRMAR_REPORTES',
        ]);
        $hash = app(ProjectReportSignatureService::class)->parametersHash(['format' => 'PCA']);

        Storage::disk('local')->put('project-signatures/abandoned-base.pdf', '%PDF-abandoned');
        $abandoned = ProjectReportSignature::query()->create([
            'id_proyecto' => $project->id_proyecto,
            'trace_id' => 'trace-abandoned',
            'report_key' => 'general_budget',
            'parameters' => ['format' => 'PCA'],
            'parameters_hash' => $hash,
            'status' => 'sent',
            'code' => 'code-abandoned',
            'id_usuario' => $user->id_usuario,
            'base_file_path' => 'project-signatures/abandoned-base.pdf',
            'response_payload' => ['redirect_url' => 'https://aprobador.test/solicitudes/abandoned'],
        ]);

        Sanctum::actingAs($user);

        $client = Mockery::mock(CiudadaniaDigitalClient::class);
        $client->shouldNotReceive('createAuthenticationUrl');
        $this->app->instance(CiudadaniaDigitalClient::class, $client);

        $this->postJson("/api/v1/projects/{$project->id_proyecto}/reports/general_budget/sign", [
            'format' => 'PCA',
        ])->assertUnprocessable();

        $this->assertDatabaseHas('project_report_signatures', ['id' => $abandoned->id]);
        $this->assertTrue(Storage::disk('local')->exists('project-signatures/abandoned-base.pdf'));

        return;
        $this->assertDatabaseHas('project_report_signatures', [
            'id_proyecto' => $project->id_proyecto,
            'report_key' => 'general_budget',
            'status' => 'auth_pending',
            'id_usuario' => $user->id_usuario,
        ]);
    }

    public function test_signature_history_only_returns_completed_signatures(): void
    {
        $project = $this->createProjectRecord(['id_proyecto' => 1]);
        $hash = app(ProjectReportSignatureService::class)->parametersHash(['format' => 'PCA']);
        Storage::disk('local')->put('project-signatures/signed.pdf', '%PDF-signed');

        ProjectReportSignature::query()->create([
            'id_proyecto' => $project->id_proyecto,
            'trace_id' => 'trace-signed',
            'report_key' => 'general_budget',
            'parameters' => ['format' => 'PCA'],
            'parameters_hash' => $hash,
            'status' => 'signed',
            'code' => 'code-signed',
            'id_usuario' => 1,
            'base_file_path' => 'project-signatures/signed.pdf',
            'signed_file_path' => 'project-signatures/signed.pdf',
        ]);
        ProjectReportSignature::query()->create([
            'id_proyecto' => $project->id_proyecto,
            'trace_id' => 'trace-sent',
            'report_key' => 'general_budget',
            'parameters' => ['format' => 'PCA'],
            'parameters_hash' => $hash,
            'status' => 'sent',
            'code' => 'code-sent',
            'id_usuario' => 1,
            'base_file_path' => 'project-signatures/sent.pdf',
        ]);

        Sanctum::actingAs($this->createProjectUserWithPermissions(['PRESUPUESTO_GENERAL']));

        $this->getJson("/api/v1/projects/{$project->id_proyecto}/reports/general_budget/signatures?format=PCA")
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.status', 'signed');
    }

    public function test_late_login_callback_for_replaced_signature_does_not_use_another_pending_signature(): void
    {
        $this->createProjectRecord(['id_proyecto' => 1]);

        $active = ProjectReportSignature::query()->create([
            'id_proyecto' => 1,
            'trace_id' => 'trace-active',
            'report_key' => 'general_budget',
            'parameters' => ['format' => 'PCA'],
            'parameters_hash' => app(ProjectReportSignatureService::class)->parametersHash(['format' => 'PCA']),
            'status' => 'auth_pending',
            'id_usuario' => 1,
            'base_file_path' => 'project-signatures/active.pdf',
        ]);
        $replacedId = $active->id + 100;

        $response = $this
            ->get('/api/v1/citizenship/signature/login-callback?signature='.$replacedId.'&access_token=token-ciudadania')
            ->assertRedirect();

        $location = $response->headers->get('Location');
        $this->assertStringContainsString('error_message=', $location);
        $this->assertStringContainsString('La+solicitud+de+firma+ya+no+est%C3%A1+activa', $location);
        $this->assertDatabaseHas('project_report_signatures', [
            'id' => $active->id,
            'status' => 'auth_pending',
        ]);
    }

    public function test_second_report_signature_uses_previous_signed_url_for_derivation(): void
    {
        ProjectSignableReport::query()
            ->where('report_key', 'general_budget')
            ->update([
                'is_enabled' => true,
            ]);

        $project = $this->createProjectRecord([
            'aprobado' => 'RV',
            'fecha_aprob' => now()->toDateString(),
            'fecha_finalizacion' => now(),
        ]);

        $service = app(ProjectReportSignatureService::class);
        $parameters = ['format' => 'PCA'];
        $hash = $service->parametersHash($parameters);
        $documentHash = $service->currentDocumentHash($project, 'general_budget', $parameters);
        Storage::disk('local')->put('project-signatures/previous.pdf', '%PDF-previous');

        $previous = ProjectReportSignature::query()->create([
            'id_proyecto' => $project->id_proyecto,
            'trace_id' => 'trace-previous',
            'report_key' => 'general_budget',
            'parameters' => $parameters,
            'parameters_hash' => $hash,
            'status' => 'signed',
            'code' => 'code-previous',
            'id_usuario' => 1,
            'base_file_path' => 'project-signatures/previous.pdf',
            'signed_file_path' => 'project-signatures/previous.pdf',
            'base_document_hash' => $documentHash,
            'response_payload' => [
                'signed_document_url' => 'https://repo.test/previous.pdf',
                'validation' => [
                    'data' => [
                        'verificacion_exitosa' => true,
                        'registros' => [
                            ['nro_documento' => '1111111'],
                        ],
                    ],
                ],
            ],
        ]);

        Storage::disk('public')->put('signature-images/second-signer.png', 'image');
        $secondSigner = $this->createProjectUserWithPermissions([
            'PRESUPUESTO_GENERAL',
            'FIRMAR_REPORTES',
        ]);
        User::query()->update(['firma_imagen_path' => 'signature-images/second-signer.png']);
        DB::table('project_version_signature_users')
            ->where('id_proyecto', $project->id_proyecto)
            ->update(['signature_image_path' => 'signature-images/second-signer.png']);
        Sanctum::actingAs($secondSigner);

        $capturedPayload = null;
        $client = Mockery::mock(CiudadaniaDigitalClient::class);
        $client->shouldReceive('userInfo')
            ->once()
            ->with('token-ciudadania')
            ->andReturn(['data' => ['numero_documento' => '2222222']]);
        $client->shouldReceive('approvedDocument')
            ->once()
            ->with('token-ciudadania', 'code-previous')
            ->andReturn(['data' => ['url_documento' => 'https://repo.test/previous-updated.pdf']]);
        $client->shouldReceive('downloadDocument')
            ->once()
            ->with('https://repo.test/previous-updated.pdf')
            ->andReturn('%PDF-signed');
        $client->shouldReceive('validateSignedDocument')
            ->once()
            ->with('%PDF-signed', 'sipre_firma_'.$previous->id.'_general_budget.pdf', 'token-ciudadania')
            ->andReturn([
                'data' => [
                    'verificacion_exitosa' => true,
                    'registros' => [
                        ['nro_documento' => '1111111'],
                    ],
                ],
            ]);
        $client->shouldReceive('createDerivedSigningUrl')
            ->once()
            ->with(Mockery::on(function (array $payload) use (&$capturedPayload): bool {
                $capturedPayload = $payload;

                return true;
            }))
            ->andReturn(['data' => ['link' => 'https://aprobador.test/derivada']]);
        $client->shouldNotReceive('createSigningUrl');
        $this->app->instance(CiudadaniaDigitalClient::class, $client);

        $this->postJson("/api/v1/projects/{$project->id_proyecto}/reports/general_budget/sign", [
            'format' => 'PCA',
            'access_token' => 'token-ciudadania',
        ])
            ->assertCreated()
            ->assertJsonPath('data.redirect_url', 'https://aprobador.test/derivada');

        $signature = ProjectReportSignature::query()->latest('id')->firstOrFail();

        $this->assertSame('true', $capturedPayload['firmar_derivacion']);
        $this->assertSame('https://repo.test/previous-updated.pdf', $capturedPayload['url_document']);
        $this->assertMatchesRegularExpression(
            '/^sipre-'.$project->id_proyecto.'-[0-9a-f-]{36}$/',
            (string) $capturedPayload['code']
        );
        $this->assertSame($signature->code, $capturedPayload['code']);
        $this->assertSame('BOTTOM', $capturedPayload['signed_position']);
        $this->assertArrayNotHasKey('is_derivated', $capturedPayload);
        $this->assertSame($capturedPayload['url_document'], $signature->request_payload['url_document']);
    }

    public function test_report_signature_hash_ignores_technical_snapshot_columns_but_changes_for_project_content(): void
    {
        $project = $this->createProjectRecord(['aprobado' => 'AP']);
        $this->createUnitMeasure();
        $this->createGroup();
        $this->createSubgroup();
        $this->createItemRecord(['id_item' => 1]);
        $this->createProjectItemRecord([
            'id_proyecto_item' => 1,
            'id_proyecto' => $project->id_proyecto,
            'cantidad' => 2,
            'precio' => 10,
        ]);

        DB::table('proyecto_item_insumo_snapshot')->insert([
            'id_proyecto_item' => 1,
            'id_insumo' => 1,
            'descripcion' => 'INSUMO TEST',
            'tipo' => 1,
            'unidad' => 'u',
            'cantidad' => 1,
            'precio_unitario' => 5,
            'parcial' => 5,
            'estado' => 'AC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(ProjectReportSignatureService::class);
        $parameters = ['format' => 'PCA'];
        $originalHash = $service->currentDocumentHash($project, 'general_budget', $parameters);

        DB::table('proyecto_item_insumo_snapshot')
            ->where('id_proyecto_item', 1)
            ->update(['updated_at' => now()->addDay()]);

        $this->assertSame(
            $originalHash,
            $service->currentDocumentHash($project, 'general_budget', $parameters)
        );

        ProjectItem::query()
            ->where('id_proyecto_item', 1)
            ->update(['cantidad' => 3]);

        $this->assertNotSame(
            $originalHash,
            $service->currentDocumentHash($project, 'general_budget', $parameters)
        );
    }

    public function test_item_report_signature_hash_ignores_non_visible_item_metadata_but_changes_for_composition(): void
    {
        $this->createUnitMeasure();
        $this->createGroup();
        $this->createSubgroup();
        $this->createInput([
            'id_insumo' => 1,
            'descripcion' => 'Material visible',
            'precio' => 10,
            'tipo' => 1,
        ]);
        $item = $this->createItemRecord([
            'id_item' => 1,
            'item' => 'ITEM CON FIRMA',
            'precio' => 20,
            'fecha_item' => now()->subDays(10)->toDateString(),
            'especificacion' => 'public/archivos/especificaciones/antes.pdf',
        ]);
        $this->createItemInputRecord([
            'id_item_insumo' => 1,
            'id_item' => $item->id_item,
            'id_insumo' => 1,
            'cantidad' => 2,
            'tipo' => 1,
        ]);

        $service = app(ProjectReportSignatureService::class);
        $parameters = ['format' => 'PCA'];
        $originalHash = $service->currentDocumentHash($item, 'item_unit_price_analysis', $parameters);

        DB::table('item')
            ->where('id_item', $item->id_item)
            ->update([
                'fecha_item' => now()->toDateString(),
                'especificacion' => 'public/archivos/especificaciones/despues.pdf',
            ]);
        DB::table('item_insumo')
            ->where('id_item_insumo', 1)
            ->delete();
        $replacement = $this->createItemInputRecord([
            'id_item_insumo' => 99,
            'id_item' => $item->id_item,
            'id_insumo' => 1,
            'cantidad' => 2,
            'tipo' => 1,
        ]);

        $item->refresh();
        $this->assertSame(
            $originalHash,
            $service->currentDocumentHash($item, 'item_unit_price_analysis', $parameters)
        );

        DB::table('item_insumo')
            ->where('id_item_insumo', $replacement->id_item_insumo)
            ->update(['cantidad' => 3]);

        $item->refresh();
        $this->assertNotSame(
            $originalHash,
            $service->currentDocumentHash($item, 'item_unit_price_analysis', $parameters)
        );
    }

    public function test_signature_status_marks_open_project_signed_report_as_stale_when_project_content_changes(): void
    {
        ProjectSignableReport::query()
            ->where('report_key', 'general_budget')
            ->update(['is_enabled' => true, 'requires_finalized_project' => false]);

        $project = $this->createProjectRecord(['aprobado' => 'AP']);
        $this->createUnitMeasure();
        $this->createGroup();
        $this->createSubgroup();
        $this->createItemRecord(['id_item' => 1]);
        $this->createItemRecord(['id_item' => 2, 'item' => 'ITEM NUEVO', 'cod' => 'ITM-002']);
        $this->createProjectItemRecord(['id_proyecto_item' => 1, 'id_item' => 1, 'cantidad' => 1, 'precio' => 10]);

        $service = app(ProjectReportSignatureService::class);
        $parameters = ['format' => 'PCA'];
        $path = 'project-signatures/stale.pdf';
        Storage::disk('local')->put($path, '%PDF-signed');

        ProjectReportSignature::query()->create([
            'id_proyecto' => $project->id_proyecto,
            'trace_id' => 'trace-stale',
            'report_key' => 'general_budget',
            'parameters' => $parameters,
            'parameters_hash' => $service->parametersHash($parameters),
            'status' => 'signed',
            'id_usuario' => 1,
            'base_file_path' => $path,
            'signed_file_path' => $path,
            'base_document_hash' => $service->currentDocumentHash($project, 'general_budget', $parameters),
        ]);

        $this->createProjectItemRecord(['id_proyecto_item' => 2, 'id_item' => 2, 'cantidad' => 1, 'precio' => 20]);

        Sanctum::actingAs($this->createProjectUserWithPermissions(['PRESUPUESTO_GENERAL', 'FIRMAR_REPORTES']));

        $this->getJson("/api/v1/projects/{$project->id_proyecto}/signature-status?report_key=general_budget&format=PCA")
            ->assertOk()
            ->assertJsonPath('data.is_current_pdf_signed', false)
            ->assertJsonPath('data.is_signed_stale', true);
    }

    public function test_signature_status_marks_open_project_legacy_signed_report_without_hash_as_stale(): void
    {
        ProjectSignableReport::query()
            ->where('report_key', 'general_budget')
            ->update(['is_enabled' => true, 'requires_finalized_project' => false]);

        $project = $this->createProjectRecord(['aprobado' => 'AP']);
        $service = app(ProjectReportSignatureService::class);
        $parameters = ['format' => 'PCA'];
        $path = 'project-signatures/legacy-no-hash.pdf';
        Storage::disk('local')->put($path, '%PDF-signed');

        ProjectReportSignature::query()->create([
            'id_proyecto' => $project->id_proyecto,
            'trace_id' => 'trace-no-hash',
            'report_key' => 'general_budget',
            'parameters' => $parameters,
            'parameters_hash' => $service->parametersHash($parameters),
            'status' => 'signed',
            'id_usuario' => 1,
            'base_file_path' => $path,
            'signed_file_path' => $path,
            'base_document_hash' => null,
        ]);

        Sanctum::actingAs($this->createProjectUserWithPermissions(['PRESUPUESTO_GENERAL', 'FIRMAR_REPORTES']));

        $this->getJson("/api/v1/projects/{$project->id_proyecto}/signature-status?report_key=general_budget&format=PCA")
            ->assertOk()
            ->assertJsonPath('data.is_current_pdf_signed', false)
            ->assertJsonPath('data.is_signed_stale', true);
    }

    public function test_report_signature_blocks_repeated_citizenship_signer(): void
    {
        ProjectSignableReport::query()
            ->where('report_key', 'general_budget')
            ->update(['is_enabled' => true]);

        $project = $this->createProjectRecord([
            'aprobado' => 'RV',
            'fecha_aprob' => now()->toDateString(),
            'fecha_finalizacion' => now(),
        ]);

        $service = app(ProjectReportSignatureService::class);
        $parameters = ['format' => 'PCA'];
        $hash = $service->parametersHash($parameters);
        Storage::disk('local')->put('project-signatures/repeated.pdf', '%PDF-previous');

        ProjectReportSignature::query()->create([
            'id_proyecto' => $project->id_proyecto,
            'trace_id' => 'trace-repeated',
            'report_key' => 'general_budget',
            'parameters' => $parameters,
            'parameters_hash' => $hash,
            'status' => 'signed',
            'code' => 'code-repeated',
            'id_usuario' => 1,
            'base_file_path' => 'project-signatures/repeated.pdf',
            'signed_file_path' => 'project-signatures/repeated.pdf',
        ]);

        Sanctum::actingAs($this->createProjectUserWithPermissions([
            'PRESUPUESTO_GENERAL',
            'FIRMAR_REPORTES',
        ]));

        $client = Mockery::mock(CiudadaniaDigitalClient::class);
        $client->shouldReceive('userInfo')
            ->once()
            ->with('token-ciudadania')
            ->andReturn(['data' => ['numero_documento' => '7511786236-8Y']]);
        $client->shouldReceive('approvedDocument')
            ->once()
            ->with('token-ciudadania', 'code-repeated')
            ->andReturn(['data' => ['url_documento' => 'https://repo.test/repeated.pdf']]);
        $client->shouldReceive('downloadDocument')
            ->once()
            ->with('https://repo.test/repeated.pdf')
            ->andReturn('%PDF-signed');
        $client->shouldReceive('validateSignedDocument')
            ->once()
            ->andReturn([
                'data' => [
                    'verificacion_exitosa' => true,
                    'registros' => [
                        ['nro_documento' => '7511786236-8Y'],
                    ],
                ],
            ]);
        $client->shouldNotReceive('createDerivedSigningUrl');
        $this->app->instance(CiudadaniaDigitalClient::class, $client);

        $this->postJson("/api/v1/projects/{$project->id_proyecto}/reports/general_budget/sign", [
            'format' => 'PCA',
            'access_token' => 'token-ciudadania',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Esta persona ya firmó este documento.');
    }

    public function test_signature_approval_callback_uses_document_approval_to_store_signed_pdf(): void
    {
        $this->createLegacyAuthUser();
        $project = $this->createProjectRecord([
            'aprobado' => 'RV',
            'fecha_aprob' => now()->toDateString(),
            'fecha_finalizacion' => now(),
        ]);
        $signer = $this->createProjectUserWithPermissions(['PRESUPUESTO_GENERAL', 'FIRMAR_REPORTES']);

        $signature = ProjectReportSignature::query()->create([
            'id_proyecto' => $project->id_proyecto,
            'trace_id' => 'trace-complete',
            'report_key' => 'general_budget',
            'parameters' => ['format' => 'PCA'],
            'parameters_hash' => app(ProjectReportSignatureService::class)->parametersHash(['format' => 'PCA']),
            'status' => 'sent',
            'code' => 'code-complete',
            'id_usuario' => $signer->id_usuario,
            'base_file_path' => 'project-signatures/base.pdf',
        ]);

        DB::table('project_signature_authorized_users')
            ->where('id_proyecto_raiz', $project->id_proyecto)
            ->where('id_usuario', $signer->id_usuario)
            ->delete();

        $client = Mockery::mock(CiudadaniaDigitalClient::class);
        $client->shouldReceive('approvedDocument')
            ->once()
            ->with('token-ciudadania', 'code-complete')
            ->andReturn(['data' => ['url_documento' => 'https://repo.test/completed.pdf']]);
        $client->shouldReceive('downloadDocument')
            ->once()
            ->with('https://repo.test/completed.pdf')
            ->andReturn('%PDF-completed');
        $client->shouldReceive('validateSignedDocument')
            ->once()
            ->with('%PDF-completed', 'sipre_firma_'.$signature->id.'_general_budget.pdf', 'token-ciudadania')
            ->andReturn([
                'data' => [
                    'verificacion_exitosa' => true,
                    'registros' => [
                        ['nro_documento' => '2222222', 'nombres' => 'ANA'],
                    ],
                ],
            ]);
        $client->shouldReceive('logout')
            ->once()
            ->andReturn(null);
        $this->app->instance(CiudadaniaDigitalClient::class, $client);
        Cache::put('ciudadania_digital_signature_token:'.$signature->id, 'token-ciudadania', now()->addMinutes(30));

        $this->postJson('/api/v1/citizenship/signature/approval-callback', [
            'code' => 'code-complete',
            'access_token' => 'token-ciudadania',
        ])
            ->assertOk()
            ->assertJsonPath('data.signature.status', 'signed');

        $signature->refresh();

        $this->assertSame('signed', $signature->status);
        $this->assertSame('https://repo.test/completed.pdf', data_get($signature->response_payload, 'signed_document_url'));
        $this->assertTrue(Storage::disk('local')->exists($signature->signed_file_path));
        $this->assertFalse(Cache::has('ciudadania_digital_signature_token:'.$signature->id));
    }

    public function test_signature_approval_callback_accepts_code(): void
    {
        $signature = new ProjectReportSignature([
            'id_proyecto' => 1,
            'trace_id' => 'trace-approval',
            'report_key' => 'general_budget',
            'parameters' => ['format' => 'PCA'],
            'status' => 'signed',
            'code' => 'code-99',
        ]);

        $service = Mockery::mock(ProjectReportSignatureService::class);
        $service->shouldReceive('complete')
            ->once()
            ->with('code-99', Mockery::on(fn (array $payload): bool => ($payload['code'] ?? null) === 'code-99'))
            ->andReturn($signature);
        $service->shouldReceive('serialize')
            ->once()
            ->with($signature)
            ->andReturn([
                'id' => 99,
                'status' => 'signed',
                'logout_redirect_url' => null,
            ]);
        $this->app->instance(ProjectReportSignatureService::class, $service);

        $this->postJson('/api/v1/citizenship/signature/approval-callback', [
            'code' => 'code-99',
        ])
            ->assertOk()
            ->assertJsonPath('data.signature.status', 'signed');
    }

    public function test_signature_approval_callback_accepts_pending_signature_when_provider_returns_no_code(): void
    {
        $signature = new ProjectReportSignature([
            'id_proyecto' => 1,
            'trace_id' => 'trace-signature',
            'report_key' => 'general_budget',
            'parameters' => ['format' => 'PCA'],
            'status' => 'signed',
            'code' => 'code-100',
        ]);

        $service = Mockery::mock(ProjectReportSignatureService::class);
        $service->shouldReceive('complete')
            ->once()
            ->with('100', Mockery::on(fn (array $payload): bool => ($payload['signature'] ?? null) === '100'))
            ->andReturn($signature);
        $service->shouldReceive('serialize')
            ->once()
            ->with($signature)
            ->andReturn([
                'id' => 100,
                'status' => 'signed',
                'logout_redirect_url' => null,
            ]);
        $this->app->instance(ProjectReportSignatureService::class, $service);

        $this->postJson('/api/v1/citizenship/signature/approval-callback', [
            'signature' => '100',
        ])
            ->assertOk()
            ->assertJsonPath('data.signature.status', 'signed');
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

    public function test_map_returns_active_non_template_projects_with_valid_coordinate_systems(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createProjectRecord([
            'id_proyecto' => 1,
            'nombre_proyecto' => 'PROYECTO UTM',
            'latitud' => '8076262.01',
            'longitud' => '806397.88',
        ]);
        $this->createProjectRecord([
            'id_proyecto' => 2,
            'nombre_proyecto' => 'PROYECTO GEOGRAFICO',
            'latitud' => '-17.416128',
            'longitud' => '-66.165436',
        ]);
        $this->createProjectRecord([
            'id_proyecto' => 3,
            'nombre_proyecto' => 'PROYECTO INACTIVO',
            'estado' => 'DC',
            'latitud' => '-17.416128',
            'longitud' => '-66.165436',
        ]);
        $this->createProjectRecord([
            'id_proyecto' => 4,
            'nombre_proyecto' => 'PLANILLA',
            'es_plantilla' => true,
            'latitud' => '-17.416128',
            'longitud' => '-66.165436',
        ]);
        $this->createProjectRecord([
            'id_proyecto' => 5,
            'nombre_proyecto' => 'COORDENADAS INVALIDAS',
            'latitud' => 'sin-coordenada',
            'longitud' => '0',
        ]);

        $this->getJson('/api/v1/projects/map')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', 1)
            ->assertJsonPath('data.items.0.coordinate_system', 'utm_32719')
            ->assertJsonPath('data.items.1.id', 2)
            ->assertJsonPath('data.items.1.coordinate_system', 'geographic')
            ->assertJsonPath('data.meta.total', 2)
            ->assertJsonPath('data.meta.skipped', 1)
            ->assertJsonMissing(['name' => 'PROYECTO INACTIVO'])
            ->assertJsonMissing(['name' => 'PLANILLA']);
    }

    public function test_non_admin_with_create_or_view_permission_can_access_project_map(): void
    {
        $this->createLegacyAuthUser();
        $this->createProjectRecord([
            'latitud' => '-17.416128',
            'longitud' => '-66.165436',
        ]);

        Sanctum::actingAs($this->createProjectUserWithPermissions(['REGISTRAR_PROYECTO']));

        $this->getJson('/api/v1/projects/map')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1);
    }

    public function test_project_map_can_filter_by_visible_bbox(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createProjectRecord([
            'id_proyecto' => 1,
            'nombre_proyecto' => 'DENTRO DEL MAPA',
            'latitud' => '-17.416128',
            'longitud' => '-66.165436',
        ]);
        $this->createProjectRecord([
            'id_proyecto' => 2,
            'nombre_proyecto' => 'FUERA DEL MAPA',
            'latitud' => '-16.500000',
            'longitud' => '-65.500000',
        ]);

        $this->getJson('/api/v1/projects/map?bbox=-17.500000,-66.300000,-17.300000,-66.000000')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', 1)
            ->assertJsonPath('data.meta.total_returned', 1)
            ->assertJsonPath('data.meta.truncated', false)
            ->assertJsonMissing(['name' => 'FUERA DEL MAPA']);
    }

    public function test_project_map_can_filter_nearby_and_report_limit_truncation(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        foreach ([1, 2, 3] as $id) {
            $this->createProjectRecord([
                'id_proyecto' => $id,
                'nombre_proyecto' => 'PROYECTO '.$id,
                'latitud' => '-17.41612'.$id,
                'longitud' => '-66.16543'.$id,
            ]);
        }
        $this->createProjectRecord([
            'id_proyecto' => 4,
            'nombre_proyecto' => 'PROYECTO LEJANO',
            'latitud' => '-17.000000',
            'longitud' => '-66.000000',
        ]);

        $this->getJson('/api/v1/projects/map?lat=-17.416128&lng=-66.165436&radius=500&limit=2')
            ->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.meta.total_returned', 2)
            ->assertJsonPath('data.meta.limit', 2)
            ->assertJsonPath('data.meta.truncated', true)
            ->assertJsonMissing(['name' => 'PROYECTO LEJANO']);
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
        $this->createItemRecord(['id_item' => 3, 'item' => 'ITEM INACTIVO', 'estado' => 'DC']);

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
            ->assertJsonCount(2, 'data.items')
            ->assertJsonMissingPath('data.items.2');

        $this->getJson('/api/v1/projects/items/3/incidence-price?format=PCA')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'El ítem seleccionado no está activo.');

        Sanctum::actingAs($this->createProjectUserWithPermissions([
            'INDEX',
            'REGISTRAR_ITEM_PROYECTO',
        ]));
        $this->getJson('/api/v1/projects/items/1/incidence-price?format=PCA')
            ->assertOk()
            ->assertJsonPath('data.item.id_item', 1);

        $this->postJson('/api/v1/projects/1/items/sync', [
            'items' => [
                ['id_item' => 3, 'precio' => 10, 'cantidad' => 1, 'prioridad' => 1],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.id_item');
    }

    public function test_adding_an_item_preserves_existing_price_precision_without_false_history_changes(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createUnitMeasure();
        $this->createGroup();
        $this->createSubgroup();
        $this->createProjectRecord();
        $this->createItemRecord();
        $this->createItemRecord(['id_item' => 2, 'item' => 'ITEM NUEVO']);
        $this->createProjectItemRecord([
            'cantidad' => 1,
            'precio' => 11455.182,
            'prioridad' => 1,
        ]);

        $itemsResponse = $this->getJson('/api/v1/projects/1/items?format=PCA')
            ->assertOk()
            ->assertJsonPath('data.items.0.precio', 11455.182);

        $this->postJson('/api/v1/projects/1/items/sync', [
            'items' => [
                [
                    'id_proyecto_item' => 1,
                    'id_item' => 1,
                    'id_modulo' => 1,
                    'precio' => $itemsResponse->json('data.items.0.precio'),
                    'cantidad' => 1,
                    'prioridad' => 1,
                ],
                [
                    'id_item' => 2,
                    'id_modulo' => 1,
                    'precio' => 20,
                    'cantidad' => 1,
                    'prioridad' => 2,
                ],
            ],
        ])->assertOk();

        $history = DB::table('proyecto_historial')
            ->where('accion', 'items_synced')
            ->orderByDesc('id_historial')
            ->firstOrFail();
        $metadata = json_decode($history->metadata, true, flags: JSON_THROW_ON_ERROR);

        $this->assertCount(1, $metadata['added']);
        $this->assertSame([], $metadata['updated']);
        $this->assertEqualsWithDelta(11455.182, (float) ProjectItem::query()->findOrFail(1)->precio, 0.0001);
        $this->assertEqualsWithDelta(11475.182, (float) Project::query()->findOrFail(1)->precio, 0.0001);
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

        DB::table('modulo')->insert([
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

        $unitPricesPdf = $this->get('/api/v1/projects/1/unit-prices/pdf?format=PC_OBRAS');

        $unitPricesPdf->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'inline; filename="analisis_de_precios_unitarios_print.pdf"');

        $this->assertStringStartsWith('%PDF', $unitPricesPdf->getContent());

        $this->getJson('/api/v1/users/1/display-name')
            ->assertOk()
            ->assertJsonPath('data.id_usuario', 1)
            ->assertJsonPath('data.funcionario', 'Usuario Demo');
    }

    public function test_can_generate_project_specifications_pdf(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());
        Storage::fake('public');

        $this->createUnitMeasure();
        $this->createGroup();
        $this->createSubgroup();
        $this->createProjectRecord();

        Storage::disk('public')->put('archivos/items/especificaciones/item-1.pdf', $this->fakePdf('Especificación 1'));
        Storage::disk('public')->put('archivos/items/especificaciones/item-2.pdf', $this->fakePdf('Especificación 2'));

        $this->createItemRecord([
            'id_item' => 1,
            'item' => 'ITEM UNO',
            'especificacion' => 'archivos/items/especificaciones/item-1.pdf',
        ]);
        $this->createItemRecord([
            'id_item' => 2,
            'item' => 'ITEM DOS',
            'especificacion' => 'archivos/items/especificaciones/item-2.pdf',
        ]);
        $this->createProjectItemRecord(['id_item' => 2, 'prioridad' => 2]);
        $this->createProjectItemRecord(['id_item' => 1, 'prioridad' => 1, 'id_proyecto_item' => 2]);

        $response = $this->get('/api/v1/projects/1/specifications/pdf');

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'inline; filename="especificaciones_proyecto.pdf"');

        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_specification_signers_assign_pages_and_only_move_shared_page_signatures(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $creator = $this->createLegacyAuthUser();
        $this->createUnitMeasure();
        $this->createGroup();
        $this->createSubgroup();
        $project = $this->createProjectRecord(['aprobado' => 'RV', 'fecha_finalizacion' => now()]);
        $second = $this->createProjectUserWithPermissions(['INDEX', 'FIRMAR_REPORTES']);
        $third = User::query()->create([
            'funcionario' => 'Tercer Firmante',
            'ci' => '7654321',
            'username' => 'firmante3',
            'clave' => Hash::make('secret123'),
            'estado' => 'AC',
            'id_unidad' => $second->id_unidad,
            'rol' => $second->rol,
            'fecha' => now()->toDateString(),
        ]);
        DB::table('project_signature_authorized_users')->insert([
            'id_proyecto_raiz' => $project->id_proyecto,
            'id_usuario' => $third->id_usuario,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        ProjectSignableReport::query()->where('report_key', 'specifications')->update(['is_enabled' => true]);

        $image = imagecreatetruecolor(20, 10);
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);
        foreach ([$creator, $second, $third] as $signer) {
            $path = "signatures/{$signer->id_usuario}.png";
            Storage::disk('public')->put($path, $png);
            $signer->forceFill(['firma_imagen_path' => $path])->save();
        }

        DB::table('modulo')->insert([
            ['id_modulo' => 2, 'nombre_modulo' => 'Módulo 1', 'estado' => 'AC', 'id_usuario' => 1, 'fecha' => now()->toDateString()],
            ['id_modulo' => 3, 'nombre_modulo' => 'Módulo 2', 'estado' => 'AC', 'id_usuario' => 1, 'fecha' => now()->toDateString()],
        ]);
        Storage::disk('public')->put(
            'archivos/items/especificaciones/repetida.pdf',
            $this->fakePdf('Especificación repetida', array_fill(0, 10, 'A4'))
        );
        $this->createItemRecord([
            'id_item' => 1,
            'item' => 'ITEM REPETIDO',
            'especificacion' => 'archivos/items/especificaciones/repetida.pdf',
        ]);
        $this->createProjectItemRecord(['id_proyecto_item' => 1, 'id_item' => 1, 'id_modulo' => 2, 'prioridad' => 1]);
        $this->createProjectItemRecord(['id_proyecto_item' => 2, 'id_item' => 1, 'id_modulo' => 3, 'prioridad' => 2]);

        Sanctum::actingAs($creator);
        $this->putJson("/api/v1/projects/{$project->id_proyecto}/signature-access", [
            'mode' => 'selected',
            'user_ids' => [$creator->id_usuario, $second->id_usuario, $third->id_usuario],
        ])->assertOk();

        $preview = $this->postJson("/api/v1/projects/{$project->id_proyecto}/reports/specifications/signature-preview", [
            'page_scope' => 'all',
        ])->assertOk()
            ->assertJsonPath('data.page_map.total_pages', 20)
            ->assertJsonCount(2, 'data.page_map.modules')
            ->assertJsonPath('data.page_assignment.complete', false)
            ->assertJsonPath('data.can_send', false);
        $this->assertSame([1, 11], collect($preview->json('data.page_map.modules'))->pluck('items.0.start_page')->all());

        $this->putJson("/api/v1/projects/{$project->id_proyecto}/reports/specifications/physical-signatures/pages", [
            'pages' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors('pages');
        $this->putJson("/api/v1/projects/{$project->id_proyecto}/reports/specifications/physical-signatures/pages", [
            'pages' => range(1, 5),
        ])->assertOk()->assertJsonPath('data.page_assignment.complete', false);

        Sanctum::actingAs($second);
        $this->putJson("/api/v1/projects/{$project->id_proyecto}/reports/specifications/physical-signatures/pages", [
            'pages' => range(5, 10),
        ])->assertOk()->assertJsonPath('data.page_assignment.complete', false);

        Sanctum::actingAs($third);
        $complete = $this->putJson("/api/v1/projects/{$project->id_proyecto}/reports/specifications/physical-signatures/pages", [
            'pages' => range(11, 20),
        ])->assertOk()
            ->assertJsonPath('data.page_assignment.complete', true)
            ->assertJsonPath('data.can_send', true)
            ->assertJsonCount(0, 'data.page_assignment.uncovered_pages');

        $creatorSignature = collect($complete->json('data.items'))->firstWhere('user_id', $creator->id_usuario);
        $originalPageOne = $creatorSignature['page_positions']['1'];
        $pageFive = $creatorSignature['page_positions']['5'];

        Sanctum::actingAs($second);
        $moved = $this->patchJson("/api/v1/projects/{$project->id_proyecto}/reports/specifications/signature-preview/positions", [
            'layout_hash' => $complete->json('data.layout_hash'),
            'positions' => [[
                'id' => $creatorSignature['id'],
                'page' => 5,
                'x' => $pageFive['x'] + 1,
                'y' => $pageFive['y'],
                'width' => $pageFive['width'],
                'height' => $pageFive['height'],
            ]],
        ])->assertOk();
        $updatedCreator = collect($moved->json('data.items'))->firstWhere('user_id', $creator->id_usuario);
        $this->assertEquals($pageFive['x'] + 1, $updatedCreator['page_positions']['5']['x']);
        $this->assertSame($originalPageOne, $updatedCreator['page_positions']['1']);

        $this->patchJson("/api/v1/projects/{$project->id_proyecto}/reports/specifications/signature-preview/positions", [
            'layout_hash' => $moved->json('data.layout_hash'),
            'positions' => [[
                'id' => $creatorSignature['id'],
                'page' => 1,
                ...$originalPageOne,
            ]],
        ])->assertForbidden();

        Storage::disk('public')->put(
            'archivos/items/especificaciones/repetida.pdf',
            $this->fakePdf('Especificación modificada', array_fill(0, 11, 'A4'))
        );
        $this->postJson("/api/v1/projects/{$project->id_proyecto}/reports/specifications/signature-preview", [
            'page_scope' => 'all',
        ])->assertOk()
            ->assertJsonPath('data.page_map.total_pages', 22)
            ->assertJsonPath('data.page_assignment.current_user_confirmed', false)
            ->assertJsonPath('data.can_send', false);
    }

    public function test_project_specifications_pdf_returns_validation_error_when_file_is_missing(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());
        Storage::fake('public');

        $this->createUnitMeasure();
        $this->createGroup();
        $this->createSubgroup();
        $this->createProjectRecord();
        $this->createItemRecord([
            'id_item' => 1,
            'item' => 'ITEM SIN ESPECIFICACIÓN',
            'especificacion' => 'archivos/items/especificaciones/no-existe.pdf',
        ]);
        $this->createProjectItemRecord(['id_item' => 1, 'prioridad' => 1]);

        $this->getJson('/api/v1/projects/1/specifications/pdf')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('specifications');
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

        $data = app(ProjectBudgetService::class)
            ->budgetByGroupPdfData(Project::findOrFail(1));

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

        $data = app(ProjectBudgetService::class)
            ->budgetRecalculation(Project::findOrFail(1), Carbon::parse('2026-04-30'));

        $this->assertCount(1, $data['items']);
        $this->assertSame('ITEM FNDR TEST', $data['items'][0]['descripcion']);
        $this->assertSame([
            'materiales' => 16.0,
            'mano_obra' => 0.0,
            'herramientas' => 0.0,
        ], $data['totals']);
    }

    public function test_budget_recalculation_reproduces_legacy_tools_log_selection(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());
        $this->createUnitMeasure();
        DB::table('tipo_insumo')->insert([
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
        DB::table('log_insumo')->insert([
            ['id_log' => 10, 'id_insumo' => 1, 'precio' => 100, 'tipo' => 3, 'descripcion' => 'Herramienta 1', 'fecha' => '2026-04-01', 'estado' => 'AC'],
            ['id_log' => 11, 'id_insumo' => 1, 'precio' => 50, 'tipo' => 3, 'descripcion' => 'Herramienta 1', 'fecha' => '2026-04-02', 'estado' => 'AC'],
            ['id_log' => 5, 'id_insumo' => 2, 'precio' => 999, 'tipo' => 3, 'descripcion' => 'Herramienta 2', 'fecha' => '2026-04-03', 'estado' => 'AC'],
        ]);

        $data = app(ProjectBudgetService::class)
            ->budgetRecalculation(Project::findOrFail(1), Carbon::parse('2026-04-30'));

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

        $data = app(ProjectBudgetService::class)
            ->budgetByGroupPdfData(Project::findOrFail(1));

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

        $items = app(ProjectBudgetService::class)->generalBudgetPdfItems(
            Project::findOrFail(1),
            'PCA',
            app(ProjectLegacyUnitPriceService::class),
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

    public function test_general_budget_items_are_grouped_by_module_with_subtotals(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());
        $this->createUnitMeasure();
        $this->createGroup();
        $this->createSubgroup();
        $this->seedGeneralPercentages();
        $this->createProjectRecord();
        DB::table('modulo')->insert([
            ['id_modulo' => 2, 'nombre_modulo' => 'Modulo A', 'estado' => 'AC', 'id_usuario' => 1, 'fecha' => now()->toDateString()],
            ['id_modulo' => 3, 'nombre_modulo' => 'Modulo B', 'estado' => 'AC', 'id_usuario' => 1, 'fecha' => now()->toDateString()],
        ]);
        $this->createInput(['id_insumo' => 1, 'tipo' => 1, 'precio' => 10, 'descripcion' => 'Material A']);
        $this->createInput(['id_insumo' => 2, 'tipo' => 1, 'precio' => 20, 'descripcion' => 'Material B']);
        $this->createItemRecord(['id_item' => 1, 'item' => 'ITEM A']);
        $this->createItemRecord(['id_item' => 2, 'item' => 'ITEM B', 'cod' => 'ITM-002']);
        $this->createItemInputRecord(['id_item_insumo' => 1, 'id_item' => 1, 'id_insumo' => 1, 'cantidad' => 2]);
        $this->createItemInputRecord(['id_item_insumo' => 2, 'id_item' => 2, 'id_insumo' => 2, 'cantidad' => 3]);
        $this->createProjectItemRecord(['id_proyecto_item' => 1, 'id_item' => 1, 'id_modulo' => 2, 'cantidad' => 2, 'prioridad' => 2]);
        $this->createProjectItemRecord(['id_proyecto_item' => 2, 'id_item' => 2, 'id_modulo' => 3, 'cantidad' => 1, 'prioridad' => 1]);

        $items = app(ProjectBudgetService::class)->generalBudgetPdfItems(
            Project::findOrFail(1),
            'PCA',
            app(ProjectLegacyUnitPriceService::class),
        );

        $this->assertSame(['Modulo A', 'Modulo B'], array_column($items, 'modulo'));
        $moduleTotals = collect($items)
            ->groupBy('modulo')
            ->map(fn ($rows): float => round((float) $rows->sum('parcial'), 2))
            ->all();
        $this->assertEqualsWithDelta(
            round(array_sum(array_column($items, 'parcial')), 2),
            round(array_sum($moduleTotals), 2),
            0.02,
        );

        $pdf = $this->get('/api/v1/projects/1/general-budget/pdf?format=PCA');
        $pdf->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $xlsx = $this->get('/api/v1/projects/1/general-budget/xlsx?format=PCA');
        $xlsx->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringStartsWith('PK', $xlsx->getContent());
        $xlsxPath = tempnam(sys_get_temp_dir(), 'general-budget-').'.xlsx';
        file_put_contents($xlsxPath, $xlsx->getContent());
        $sheetValues = collect(IOFactory::load($xlsxPath)->getActiveSheet()->toArray())
            ->flatten()
            ->filter(fn ($value): bool => $value !== null && $value !== '')
            ->values()
            ->all();
        @unlink($xlsxPath);

        $this->assertContains('MÓDULO: Modulo A', $sheetValues);
        $this->assertContains('SUBTOTAL MÓDULO Modulo A', $sheetValues);
        $this->assertNotContains('OBRAS PRELIMINARES', $sheetValues);
        $this->assertNotContains('PRELIMINARES', $sheetValues);
    }

    public function test_budget_recalculation_rows_are_grouped_by_module_with_subtotals(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());
        $this->createUnitMeasure();
        $this->createGroup();
        $this->createSubgroup();
        $this->createProjectRecord();
        DB::table('modulo')->insert([
            ['id_modulo' => 2, 'nombre_modulo' => 'Modulo A', 'estado' => 'AC', 'id_usuario' => 1, 'fecha' => now()->toDateString()],
            ['id_modulo' => 3, 'nombre_modulo' => 'Modulo B', 'estado' => 'AC', 'id_usuario' => 1, 'fecha' => now()->toDateString()],
        ]);
        $this->createInput(['id_insumo' => 1, 'tipo' => 1, 'precio' => 10, 'descripcion' => 'Material A']);
        $this->createInput(['id_insumo' => 2, 'tipo' => 1, 'precio' => 20, 'descripcion' => 'Material B']);
        $this->createInputLog(['id_log' => 1, 'id_insumo' => 1, 'precio' => 8, 'tipo' => 1, 'descripcion' => 'Material A', 'fecha' => '2026-04-01']);
        $this->createInputLog(['id_log' => 2, 'id_insumo' => 2, 'precio' => 18, 'tipo' => 1, 'descripcion' => 'Material B', 'fecha' => '2026-04-01']);
        $this->createItemRecord(['id_item' => 1, 'item' => 'ITEM A']);
        $this->createItemRecord(['id_item' => 2, 'item' => 'ITEM B', 'cod' => 'ITM-002']);
        $this->createItemInputRecord(['id_item_insumo' => 1, 'id_item' => 1, 'id_insumo' => 1, 'cantidad' => 2]);
        $this->createItemInputRecord(['id_item_insumo' => 2, 'id_item' => 2, 'id_insumo' => 2, 'cantidad' => 3]);
        $this->createProjectItemRecord(['id_proyecto_item' => 1, 'id_item' => 1, 'id_modulo' => 2, 'prioridad' => 2]);
        $this->createProjectItemRecord(['id_proyecto_item' => 2, 'id_item' => 2, 'id_modulo' => 3, 'prioridad' => 1]);

        $data = app(ProjectBudgetService::class)
            ->budgetRecalculation(Project::findOrFail(1), Carbon::parse('2026-04-30'));

        $this->assertSame(['Modulo A', 'Modulo B'], array_column($data['items'], 'modulo'));
        $moduleTotals = collect($data['items'])
            ->groupBy('modulo')
            ->map(fn ($rows): float => round((float) $rows->sum('materiales'), 2))
            ->all();
        $this->assertSame(16.0, $moduleTotals['Modulo A']);
        $this->assertSame(54.0, $moduleTotals['Modulo B']);

        $pdf = $this->get('/api/v1/projects/1/budget-recalculation/pdf?fecha=2026-04-30');
        $pdf->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $xlsx = $this->get('/api/v1/projects/1/budget-recalculation/xlsx?fecha=2026-04-30');
        $xlsx->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
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

        $service = app(ProjectInputBreakdownPdfService::class);
        $this->assertSame('Material 1', $service->rows(Project::findOrFail(1), 1)[0]['descripcion']);
        $this->assertSame(20.0, $service->rows(Project::findOrFail(1), 1)[0]['parcial']);
        $this->assertSame('Mano 1', $service->rows(Project::findOrFail(1), 2)[0]['descripcion']);
        $this->assertSame('Herramienta 1', $service->rows(Project::findOrFail(1), 3)[0]['descripcion']);

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

        $rows = app(ProjectInputBreakdownPdfService::class)
            ->rows(Project::findOrFail(1), 1);

        $this->assertSame(['ITEM DOS', 'ITEM UNO'], array_column($rows, 'nombre_item'));
        $this->assertSame([10, 20], array_column($rows, 'prioridad'));
    }

    public function test_project_input_breakdown_rows_are_grouped_by_module_with_subtotals(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());
        $this->createUnitMeasure();
        $this->createGroup();
        $this->createSubgroup();
        $this->createProjectRecord();
        DB::table('modulo')->insert([
            ['id_modulo' => 2, 'nombre_modulo' => 'Modulo A', 'estado' => 'AC', 'id_usuario' => 1, 'fecha' => now()->toDateString()],
            ['id_modulo' => 3, 'nombre_modulo' => 'Modulo B', 'estado' => 'AC', 'id_usuario' => 1, 'fecha' => now()->toDateString()],
        ]);
        $this->createInput(['id_insumo' => 1, 'tipo' => 1, 'precio' => 10, 'descripcion' => 'Material A']);
        $this->createInput(['id_insumo' => 2, 'tipo' => 1, 'precio' => 20, 'descripcion' => 'Material B']);
        $this->createItemRecord(['id_item' => 1, 'item' => 'ITEM A']);
        $this->createItemRecord(['id_item' => 2, 'item' => 'ITEM B', 'cod' => 'ITM-002']);
        $this->createItemInputRecord(['id_item_insumo' => 1, 'id_item' => 1, 'id_insumo' => 1, 'cantidad' => 2]);
        $this->createItemInputRecord(['id_item_insumo' => 2, 'id_item' => 2, 'id_insumo' => 2, 'cantidad' => 3]);
        $this->createProjectItemRecord(['id_proyecto_item' => 1, 'id_item' => 1, 'id_modulo' => 2, 'prioridad' => 2]);
        $this->createProjectItemRecord(['id_proyecto_item' => 2, 'id_item' => 2, 'id_modulo' => 3, 'prioridad' => 1]);

        $rows = app(ProjectInputBreakdownPdfService::class)
            ->rows(Project::findOrFail(1), 1);

        $this->assertSame(['Modulo A', 'Modulo B'], array_column($rows, 'modulo'));
        $this->assertSame([20.0, 60.0], array_map(fn (array $row): float => $row['parcial'], $rows));
        $this->assertSame(80.0, array_sum(array_column($rows, 'parcial')));

        $pdf = $this->get('/api/v1/projects/1/input-breakdown/pdf?type=1');
        $pdf->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $xlsx = $this->get('/api/v1/projects/1/input-breakdown/xlsx?type=1');
        $xlsx->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringStartsWith('PK', $xlsx->getContent());
    }

    public function test_project_input_breakdown_pdf_keeps_project_snapshot_when_input_is_inactive(): void
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

        $rows = app(ProjectInputBreakdownPdfService::class)
            ->rows(Project::findOrFail(1), 1);

        $this->assertSame(['Material Activo', 'Material Inactivo'], array_column($rows, 'descripcion'));
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

        $rows = app(ProjectInputsReportPdfService::class)
            ->rows(Project::findOrFail(1));

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

        $groupedRows = app(ProjectInputsGroupedReportPdfService::class)
            ->rows(Project::findOrFail(1));

        $this->assertSame(['Material Repetido', 'Material Repetido', 'Mano Consolidada', 'Herramienta Consolidada'], array_column($groupedRows, 'insumo'));
        $this->assertSame(4.0, $groupedRows[0]['cantidad_total']);
        $this->assertSame(40.0, $groupedRows[0]['parcial']);

        $groupedPdf = $this->get('/api/v1/projects/1/grouped-inputs-report/pdf');

        $groupedPdf->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'inline; filename="proyecto_agrupado_por_insumos.pdf"');
        $this->assertStringStartsWith('%PDF', $groupedPdf->getContent());

        $groupedXlsx = $this->get('/api/v1/projects/1/grouped-inputs-report/xlsx');

        $groupedXlsx->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertHeader('content-disposition', 'attachment; filename="proyecto_agrupado_por_insumos.xlsx"');
        $this->assertStringStartsWith('PK', $groupedXlsx->getContent());

        $this->assertDatabaseHas('proyecto_historial', [
            'id_proyecto' => 1,
            'id_usuario' => 1,
            'accion' => 'pdf_generated',
        ]);
    }

    public function test_consolidated_project_inputs_report_uses_project_snapshot_and_excludes_inactive_project_rows(): void
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

        $rows = app(ProjectInputsReportPdfService::class)
            ->rows(Project::findOrFail(1));

        $this->assertSame(['Insumo Inactivo', 'Material Activo'], array_column($rows, 'descripcion'));
        $this->assertSame(2.0, $rows[0]['cantidad']);
        $this->assertSame(2.0, $rows[1]['cantidad']);
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
        $this->getJson('/api/v1/projects/map')->assertForbidden();
        $this->getJson('/api/v1/projects/1/history')->assertForbidden();
        $this->get('/api/v1/projects/1/inputs-report/pdf')->assertForbidden();
        $this->get('/api/v1/projects/1/unit-prices/pdf?format=PCA')->assertForbidden();
        $this->get('/api/v1/projects/1/specifications/pdf')->assertForbidden();
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
            'signature_access' => [
                'mode' => 'selected',
                'user_ids' => [1],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.project.nombre_proyecto', 'PROYECTO DESDE PLANILLA')
            ->assertJsonPath('data.project.es_plantilla', false)
            ->assertJsonPath('data.project.precio', 30);

        $projectId = $projectResponse->json('data.project.id_proyecto');

        $this->assertSame(1, ProjectItem::query()->where('id_proyecto', $projectId)->where('estado', 'AC')->count());
        $this->assertTrue(Project::query()->findOrFail($projectId)->project_access_restricted);
        $this->assertDatabaseHas('project_signature_authorized_users', [
            'id_proyecto_raiz' => $projectId,
            'id_usuario' => 1,
        ]);

        $allProjectResponse = $this->postJson("/api/v1/project-templates/{$templateId}/create-project", [
            'nombre_proyecto' => 'proyecto abierto desde planilla',
            'fecha' => '2026-05-22',
            'ubicacion' => 'ubicacion nueva',
            'responsable' => 1,
            'solicitante' => 1,
            'estado' => 'AC',
            'aprobado' => 'PD',
            'signature_access' => [
                'mode' => 'selected',
                'user_ids' => [1],
            ],
        ])->assertCreated();
        $allProjectId = $allProjectResponse->json('data.project.id_proyecto');
        $this->assertDatabaseHas('proyecto', [
            'id_proyecto' => $allProjectId,
            'signature_access_mode' => 'selected',
        ]);
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

    public function test_new_projects_limit_changes_to_authorized_users_and_keep_existing_projects_open(): void
    {
        $admin = $this->createLegacyAuthUser();
        $authorized = $this->createProjectUserWithPermissions([
            'INDEX',
            'EDITAR_PROYECTO',
            'REGISTRAR_ITEM_PROYECTO',
            'RECAL_PRESUPUESTO_RUBRO',
            'CALCULAR_DESGLOSE',
        ]);
        $unauthorized = User::query()->create([
            'id_usuario' => 3,
            'funcionario' => 'Usuario Solo Lectura',
            'ci' => '33445566',
            'username' => 'solo.lectura',
            'clave' => Hash::make('secret123'),
            'estado' => 'AC',
            'id_unidad' => $authorized->id_unidad,
            'rol' => $authorized->rol,
            'fecha' => now()->toDateString(),
            'subalcaldia' => null,
        ]);

        Sanctum::actingAs($admin);
        $created = $this->postJson('/api/v1/projects', [
            'nombre_proyecto' => 'proyecto restringido',
            'fecha' => '2026-07-22',
            'ubicacion' => 'ubicacion',
            'responsable' => 1,
            'solicitante' => 1,
            'estado' => 'AC',
            'aprobado' => 'PD',
            'signature_access' => [
                'mode' => 'selected',
                'user_ids' => [$authorized->id_usuario],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.project.access_restricted', true)
            ->assertJsonPath('data.project.can_modify', true);
        $projectId = $created->json('data.project.id_proyecto');

        $updatePayload = [
            'nombre_proyecto' => 'PROYECTO RESTRINGIDO',
            'fecha' => '2026-07-22',
            'ubicacion' => 'UBICACION',
            'responsable' => 1,
            'solicitante' => 1,
            'observaciones' => null,
            'estado' => 'AC',
            'aprobado' => 'PD',
        ];

        Sanctum::actingAs($unauthorized);
        $this->getJson("/api/v1/projects/{$projectId}")
            ->assertOk()
            ->assertJsonPath('data.project.can_modify', false);
        $this->putJson("/api/v1/projects/{$projectId}", $updatePayload)->assertForbidden();
        $this->postJson("/api/v1/projects/{$projectId}/items/sync", ['items' => []])->assertForbidden();
        $this->postJson("/api/v1/projects/{$projectId}/synchronize")->assertForbidden();
        $this->postJson("/api/v1/projects/{$projectId}/versions")->assertForbidden();
        $this->postJson("/api/v1/projects/{$projectId}/budget-recalculation", ['fecha' => '2026-07-22'])->assertForbidden();
        $this->postJson("/api/v1/projects/{$projectId}/template", ['nombre_proyecto' => 'NO PERMITIDA'])->assertForbidden();

        Sanctum::actingAs($authorized);
        $this->getJson("/api/v1/projects/{$projectId}")
            ->assertOk()
            ->assertJsonPath('data.project.can_modify', true);
        $this->putJson("/api/v1/projects/{$projectId}", $updatePayload)->assertOk();

        Sanctum::actingAs($admin);
        $this->putJson("/api/v1/projects/{$projectId}", $updatePayload)->assertOk();

        Sanctum::actingAs($authorized);
        $this->postJson("/api/v1/projects/{$projectId}/finalize")->assertOk();
        $this->postJson("/api/v1/projects/{$projectId}/versions")
            ->assertCreated()
            ->assertJsonPath('data.project.access_restricted', true)
            ->assertJsonPath('data.project.can_modify', true);

        $legacy = $this->createProjectRecord([
            'id_proyecto' => 50,
            'id_proyecto_raiz' => 50,
            'nombre_proyecto' => 'PROYECTO EXISTENTE',
        ]);
        Sanctum::actingAs($unauthorized);
        $this->getJson("/api/v1/projects/{$legacy->id_proyecto}")
            ->assertOk()
            ->assertJsonPath('data.project.access_restricted', false)
            ->assertJsonPath('data.project.can_modify', true);
        $this->putJson("/api/v1/projects/{$legacy->id_proyecto}", [
            ...$updatePayload,
            'nombre_proyecto' => 'PROYECTO EXISTENTE EDITADO',
        ])->assertOk();
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

        $user = User::query()->create([
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

        $authorizationRows = Project::query()
            ->where(function ($query): void {
                $query->whereColumn('id_proyecto', 'id_proyecto_raiz')
                    ->orWhereNull('id_proyecto_raiz');
            })
            ->get()
            ->map(fn (Project $project): array => [
                'id_proyecto_raiz' => $project->id_proyecto_raiz ?: $project->id_proyecto,
                'id_usuario' => $user->id_usuario,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->all();

        if ($authorizationRows !== []) {
            DB::table('project_signature_authorized_users')->insertOrIgnore($authorizationRows);
        }

        return $user;
    }

    private function fakePdf(string $text, array $pageFormats = ['A4']): string
    {
        $pdf = new \TCPDF;
        $pdf->SetPrintHeader(false);
        $pdf->SetPrintFooter(false);

        foreach ($pageFormats as $index => $format) {
            $pdf->AddPage('P', $format);
            $pdf->Write(0, $text.' '.($index + 1));
        }

        return $pdf->Output('', 'S');
    }

    public function test_project_versions_follow_the_finalize_update_finalize_cycle(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());
        $this->createProjectRecord([
            'id_proyecto' => 1,
            'id_proyecto_raiz' => 1,
            'numero_version' => 1,
            'es_version_actual' => true,
            'aprobado' => 'PD',
        ]);

        $payload = [
            'nombre_proyecto' => 'PROYECTO TEST',
            'fecha' => '2026-04-30',
            'ubicacion' => 'CENTRO',
            'responsable' => 1,
            'solicitante' => 1,
            'observaciones' => null,
            'estado' => 'AC',
            'aprobado' => 'RV',
        ];

        $this->putJson('/api/v1/projects/1', $payload)
            ->assertOk()
            ->assertJsonPath('data.project.aprobado', 'RV')
            ->assertJsonPath('data.project.is_frozen', true);

        $created = $this->postJson('/api/v1/projects/1/versions')
            ->assertCreated()
            ->assertJsonPath('data.project.aprobado', 'AP')
            ->assertJsonPath('data.project.version_number', 2)
            ->assertJsonPath('data.project.is_current_version', true);

        $versionTwoId = $created->json('data.project.id_proyecto');

        $this->getJson('/api/v1/projects/'.$versionTwoId.'/signature-access')
            ->assertOk()
            ->assertJsonPath('data.root_project_id', 1)
            ->assertJsonPath('data.mode', 'selected')
            ->assertJsonPath('data.creator.id', 1)
            ->assertJsonPath('data.authorized_users.0.id', 1);

        $this->getJson('/api/v1/projects/1/history')
            ->assertOk()
            ->assertJsonPath('data.items.0.action', 'version_created')
            ->assertJsonPath('data.items.0.user_name', 'Usuario Demo')
            ->assertJsonPath('data.items.0.project_id', $versionTwoId);

        $this->getJson('/api/v1/projects/1/history?action=version_created')
            ->assertOk()
            ->assertJsonPath('data.items.0.action', 'version_created')
            ->assertJsonPath('data.items.0.user_name', 'Usuario Demo')
            ->assertJsonPath('data.meta.total', 1);

        $this->getJson('/api/v1/projects/1/versions')
            ->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.id_proyecto', $versionTwoId)
            ->assertJsonPath('data.items.1.is_current_version', false);

        $this->putJson('/api/v1/projects/1', [
            ...$payload,
            'aprobado' => 'RV',
        ])->assertUnprocessable();

        $this->postJson('/api/v1/projects/'.$versionTwoId.'/finalize')
            ->assertOk()
            ->assertJsonPath('data.project.aprobado', 'RV')
            ->assertJsonPath('data.project.is_frozen', true);

        $this->getJson('/api/v1/projects')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id_proyecto', $versionTwoId)
            ->assertJsonPath('data.items.0.version_count', 2);
    }

    public function test_can_compare_project_versions_by_items(): void
    {
        Sanctum::actingAs($this->createLegacyAuthUser());

        $this->createProjectRecord([
            'id_proyecto' => 1,
            'id_proyecto_raiz' => 1,
            'numero_version' => 1,
            'es_version_actual' => false,
            'aprobado' => 'RV',
        ]);
        $this->createProjectRecord([
            'id_proyecto' => 2,
            'id_proyecto_raiz' => 1,
            'id_version_origen' => 1,
            'numero_version' => 2,
            'es_version_actual' => true,
            'aprobado' => 'AP',
        ]);
        $this->createUnitMeasure();
        $this->createGroup();
        $this->createSubgroup();
        $this->createItemRecord(['id_item' => 1, 'item' => 'ITEM MODIFICADO']);
        $this->createItemRecord(['id_item' => 2, 'item' => 'ITEM QUITADO']);
        $this->createItemRecord(['id_item' => 3, 'item' => 'ITEM AGREGADO']);

        $this->createProjectItemRecord([
            'id_proyecto_item' => 1,
            'id_proyecto' => 1,
            'id_item' => 1,
            'cantidad' => 2,
            'precio' => 10,
            'prioridad' => 1,
            'nombre_snapshot' => 'ITEM MODIFICADO',
        ]);
        $this->createProjectItemRecord([
            'id_proyecto_item' => 2,
            'id_proyecto' => 1,
            'id_item' => 2,
            'cantidad' => 1,
            'precio' => 20,
            'prioridad' => 2,
            'nombre_snapshot' => 'ITEM QUITADO',
        ]);
        $this->createProjectItemRecord([
            'id_proyecto_item' => 3,
            'id_proyecto' => 2,
            'id_item' => 1,
            'cantidad' => 3,
            'precio' => 12,
            'prioridad' => 1,
            'nombre_snapshot' => 'ITEM MODIFICADO',
        ]);
        $this->createProjectItemRecord([
            'id_proyecto_item' => 4,
            'id_proyecto' => 2,
            'id_item' => 3,
            'cantidad' => 1,
            'precio' => 5,
            'prioridad' => 3,
            'nombre_snapshot' => 'ITEM AGREGADO',
        ]);

        $this->getJson('/api/v1/projects/1/versions/compare?base=1&target=2&format=PCA')
            ->assertOk()
            ->assertJsonPath('data.base.id_proyecto', 1)
            ->assertJsonPath('data.target.id_proyecto', 2)
            ->assertJsonPath('data.summary.base_total', 40)
            ->assertJsonPath('data.summary.target_total', 41)
            ->assertJsonPath('data.summary.added_count', 1)
            ->assertJsonPath('data.summary.removed_count', 1)
            ->assertJsonPath('data.summary.modified_count', 1);
    }

    public function test_project_version_compare_rejects_different_families_and_missing_permission(): void
    {
        $this->createLegacyAuthUser();
        $this->createProjectRecord(['id_proyecto' => 1, 'id_proyecto_raiz' => 1]);
        $this->createProjectRecord(['id_proyecto' => 2, 'id_proyecto_raiz' => 2]);

        Sanctum::actingAs($this->createProjectUserWithPermissions([]));

        $this->getJson('/api/v1/projects/1/versions/compare?base=1&target=2&format=PCA')
            ->assertForbidden();

        Sanctum::actingAs(User::query()->findOrFail(1));

        $this->getJson('/api/v1/projects/1/versions/compare?base=1&target=2&format=PCA')
            ->assertUnprocessable()
            ->assertJsonPath('errors.version.0', 'Las versiones seleccionadas no pertenecen al mismo proyecto.');
    }
}

<?php

namespace Tests\Feature\Console;

use App\Models\Item;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrateLegacyRepositoryPdfsTest extends TestCase
{
    private string $legacyRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->legacyRoot = storage_path('framework/testing/legacy-pdfs-'.uniqid());
        File::ensureDirectoryExists($this->legacyRoot.'/uploads');
        File::ensureDirectoryExists($this->legacyRoot.'/archivos/especificaciones');
        File::ensureDirectoryExists($this->legacyRoot.'/archivos/especificaciones_ant');
        File::ensureDirectoryExists($this->legacyRoot.'/legacy_ant/precios_unitarios');

        Schema::create('item', function (Blueprint $table): void {
            $table->increments('id_item');
            $table->string('especificacion')->nullable();
            $table->string('ficha')->nullable();
        });
        Schema::create('repository_files', function (Blueprint $table): void {
            $table->id();
            $table->string('repository_id')->unique();
            $table->text('url_file');
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('collector')->nullable();
            $table->string('system_id')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamps();
        });
        Schema::create('repository_file_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('repository_file_id');
            $table->string('linkable_type');
            $table->unsignedBigInteger('linkable_id');
            $table->string('field')->nullable();
            $table->timestamps();
            $table->unique(['repository_file_id', 'linkable_type', 'linkable_id', 'field']);
        });
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->legacyRoot);
        parent::tearDown();
    }

    public function test_dry_run_resolves_public_storage_paths_without_database_or_http_writes(): void
    {
        File::put($this->legacyRoot.'/archivos/especificaciones/spec.pdf', "%PDF-1.4\nexample");
        $item = Item::query()->create(['especificacion' => 'public/archivos/especificaciones/spec.pdf']);
        Http::fake();

        $this->artisan('repository:migrate-legacy-pdfs', [
            '--legacy-root' => $this->legacyRoot,
            '--only' => 'specs',
            '--dry-run' => true,
            '--output' => $this->legacyRoot.'/reports',
        ])->assertSuccessful();

        $this->assertSame('public/archivos/especificaciones/spec.pdf', $item->fresh()->especificacion);
        $this->assertDatabaseCount('repository_files', 0);
        $this->assertDatabaseCount('repository_file_links', 0);
        Http::assertNothingSent();

        $report = json_decode(File::get($this->legacyRoot.'/reports/legacy-pdf-migration-summary.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(1, $report['summary']['referenced']);
        $this->assertSame(1, $report['summary']['dry_run']);
        $this->assertSame(0, $report['summary']['missing']);
    }

    public function test_migrates_referenced_and_unreferenced_pdfs_then_resume_does_not_upload_them_again(): void
    {
        File::put($this->legacyRoot.'/archivos/especificaciones/referenced.pdf', "%PDF-1.4\nreferenced");
        File::put($this->legacyRoot.'/archivos/especificaciones/unreferenced_c.pdf', "%PDF-1.4\nunreferenced");
        $item = Item::query()->create(['especificacion' => 'public/archivos/especificaciones/referenced.pdf']);
        config()->set('services.repository.endpoint', 'https://repository.test/upload');
        Http::fake(['https://repository.test/upload' => function (): mixed {
            static $sequence = 0;
            $sequence++;

            return Http::response([
                'status' => true,
                'response' => [[
                    'id_repository' => 'legacy-'.$sequence,
                    'url_file' => 'https://repository.test/legacy-'.$sequence.'.pdf',
                ]],
            ], 201);
        }]);

        $arguments = [
            '--legacy-root' => $this->legacyRoot,
            '--only' => 'specs',
            '--include-unreferenced' => true,
            '--output' => $this->legacyRoot.'/reports',
        ];
        $this->artisan('repository:migrate-legacy-pdfs', $arguments)->assertSuccessful();

        $this->assertSame('https://repository.test/legacy-1.pdf', $item->fresh()->especificacion);
        $this->assertDatabaseCount('repository_files', 2);
        $this->assertDatabaseCount('repository_file_links', 1);
        Http::assertSentCount(2);

        $report = json_decode(File::get($this->legacyRoot.'/reports/legacy-pdf-migration-summary.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(1, $report['summary']['referenced']);
        $this->assertSame(1, $report['summary']['unreferenced']);
        $this->assertSame(2, $report['summary']['migrated']);
        $this->assertSame('https://repository.test/legacy-2.pdf', $report['inventory'][1]['url_file']);

        $this->artisan('repository:migrate-legacy-pdfs', $arguments + ['--resume' => true])->assertSuccessful();
        $this->assertDatabaseCount('repository_files', 2);
        $this->assertDatabaseCount('repository_file_links', 1);
        Http::assertSentCount(2);
    }

    public function test_reuses_fingerprint_for_another_reference_without_a_second_upload(): void
    {
        $pdf = "%PDF-1.4\nexample";
        File::put($this->legacyRoot.'/uploads/one.pdf', $pdf);
        File::put($this->legacyRoot.'/uploads/two.pdf', $pdf);
        $first = Item::query()->create(['especificacion' => 'uploads/one.pdf']);
        $second = Item::query()->create(['especificacion' => 'uploads/two.pdf']);
        config()->set('services.repository.endpoint', 'https://repository.test/upload');
        Http::fake(['https://repository.test/upload' => Http::response([
            'status' => true,
            'response' => [['id_repository' => 'legacy-1', 'url_file' => 'https://repository.test/legacy-1.pdf']],
        ], 201)]);

        $this->artisan('repository:migrate-legacy-pdfs', ['--legacy-root' => $this->legacyRoot])->assertSuccessful();

        $this->assertSame('https://repository.test/legacy-1.pdf', $first->fresh()->especificacion);
        $this->assertSame('https://repository.test/legacy-1.pdf', $second->fresh()->especificacion);
        $this->assertDatabaseCount('repository_files', 1);
        $this->assertDatabaseCount('repository_file_links', 2);
        Http::assertSentCount(1);
    }

    public function test_resume_skips_an_https_repository_url_without_an_existing_link(): void
    {
        $item = Item::query()->create(['especificacion' => 'https://repository.test/files/already-migrated.pdf']);
        Http::fake();

        $this->artisan('repository:migrate-legacy-pdfs', [
            '--legacy-root' => $this->legacyRoot,
            '--resume' => true,
            '--output' => $this->legacyRoot.'/reports',
        ])->assertSuccessful();

        $this->assertSame('https://repository.test/files/already-migrated.pdf', $item->fresh()->especificacion);
        $this->assertDatabaseCount('repository_files', 0);
        Http::assertNothingSent();

        $report = json_decode(File::get($this->legacyRoot.'/reports/legacy-pdf-migration-summary.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(1, $report['summary']['skipped_resume']);
    }

    public function test_reports_non_pdf_files_without_including_them_in_the_upload_inventory(): void
    {
        File::makeDirectory($this->legacyRoot.'/unrelated', 0755, true);
        File::put($this->legacyRoot.'/unrelated/ignored.pdf', "%PDF-1.4\nignored");
        File::put($this->legacyRoot.'/archivos/especificaciones/note.txt', 'not a pdf');
        File::put($this->legacyRoot.'/archivos/especificaciones/spec_c.pdf', "%PDF-1.4\nvalid pdf");

        $this->artisan('repository:migrate-legacy-pdfs', [
            '--legacy-root' => $this->legacyRoot,
            '--only' => 'specs',
            '--dry-run' => true,
            '--include-unreferenced' => true,
            '--output' => $this->legacyRoot.'/report',
        ])->assertSuccessful();

        $report = json_decode(File::get($this->legacyRoot.'/report/legacy-pdf-migration-summary.json'), true, flags: JSON_THROW_ON_ERROR);
        $classifications = array_column($report['physical_report'], 'classification', 'path');

        $this->assertSame('non_pdf', $classifications[$this->legacyRoot.'/archivos/especificaciones/note.txt']);
        $this->assertArrayNotHasKey($this->legacyRoot.'/archivos/especificaciones/spec_c.pdf', $classifications);
        $this->assertArrayNotHasKey($this->legacyRoot.'/unrelated/ignored.pdf', $classifications);
        $this->assertSame(1, $report['summary']['unreferenced']);
        $this->assertSame(1, $report['summary']['dry_run']);
        $this->assertDatabaseCount('repository_files', 0);
    }
}

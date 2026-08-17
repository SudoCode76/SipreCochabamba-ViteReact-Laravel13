<?php

namespace Tests\Unit\Services\Files;

use App\Services\Files\PublicFileService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class PublicFileServiceTest extends TestCase
{
    public function test_it_resolves_current_and_legacy_public_paths(): void
    {
        Storage::fake('public');
        $files = app(PublicFileService::class);

        $paths = [
            'archivos/especificaciones/item.pdf',
            'public/archivos/fichas_tecnicas/ficha.pdf',
            'public/archivos/cotizaciones/cotizacion.pdf',
            'public/especificaciones/item-antiguo.pdf',
            'public/fichas_tecnicas/ficha-antigua.pdf',
            'public/fichas_tecnicas_ant/ficha-muy-antigua.pdf',
            'public/cotizaciones_ant/cotizacion-antigua.pdf',
            'archivos/items/especificaciones/item-laravel.pdf',
            'cotizaciones/valido/cotizacion-laravel.pdf',
        ];

        foreach ($paths as $path) {
            $normalized = $files->normalize($path);
            Storage::disk('public')->put($normalized, 'archivo');

            $this->assertTrue($files->exists($path));
            $this->assertSame('/storage/'.$normalized, $files->url($path));
            $this->assertSame(Storage::disk('public')->path($normalized), $files->absolutePath($path));
        }
    }

    public function test_it_keeps_remote_repository_urls_available(): void
    {
        $files = app(PublicFileService::class);
        $url = 'https://repository.test/files/document.pdf?token=abc';

        $this->assertTrue($files->exists($url));
        $this->assertSame($url, $files->url($url));
    }

    public function test_it_materializes_a_pdf_only_from_the_configured_repository_host_and_port(): void
    {
        config()->set('services.repository.endpoint', 'https://repository.test:8443/api/v1/repository/sipre');
        $url = 'https://repository.test:8443/files/document.pdf';
        Http::fake([
            $url => Http::response("%PDF-1.4\nexample", 200, ['Content-Type' => 'application/pdf']),
        ]);

        $path = app(PublicFileService::class)->materializeRemotePdf($url);

        $this->assertFileExists($path);
        $this->assertSame("%PDF-1.4\nexample", file_get_contents($path));
        @unlink($path);
    }

    public function test_it_rejects_remote_pdfs_outside_the_configured_repository_before_sending_a_request(): void
    {
        config()->set('services.repository.endpoint', 'https://repository.test/api/v1/repository/sipre');
        Http::fake();

        try {
            app(PublicFileService::class)->materializeRemotePdf('https://attacker.test/private.pdf');
            $this->fail('Expected an exception for an untrusted remote URL.');
        } catch (RuntimeException $exception) {
            $this->assertSame('La especificación remota debe usar HTTPS y pertenecer al repositorio configurado.', $exception->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_it_rejects_a_redirect_outside_the_configured_repository_host(): void
    {
        config()->set('services.repository.endpoint', 'https://repository.test/api/v1/repository/sipre');
        $url = 'https://repository.test/files/document.pdf';
        Http::fake([
            $url => Http::response('', 302, ['Location' => 'https://attacker.test/private.pdf']),
        ]);

        $this->expectExceptionObject(new RuntimeException('La especificación remota redirige fuera del repositorio configurado.'));
        app(PublicFileService::class)->materializeRemotePdf($url);
    }

    public function test_it_rejects_directory_traversal(): void
    {
        $files = app(PublicFileService::class);

        $this->assertNull($files->normalize('public/archivos/../../.env'));
        $this->assertFalse($files->exists('public/archivos/../../.env'));
    }
}

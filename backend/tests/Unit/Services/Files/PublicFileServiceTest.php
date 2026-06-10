<?php

namespace Tests\Unit\Services\Files;

use App\Services\Files\PublicFileService;
use Illuminate\Support\Facades\Storage;
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

    public function test_it_rejects_directory_traversal(): void
    {
        $files = app(PublicFileService::class);

        $this->assertNull($files->normalize('public/archivos/../../.env'));
        $this->assertFalse($files->exists('public/archivos/../../.env'));
    }
}

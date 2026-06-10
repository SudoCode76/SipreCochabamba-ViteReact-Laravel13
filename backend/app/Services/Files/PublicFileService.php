<?php

namespace App\Services\Files;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PublicFileService
{
    public const SPECIFICATIONS_DIRECTORY = 'archivos/especificaciones';

    public const TECHNICAL_SHEETS_DIRECTORY = 'archivos/fichas_tecnicas';

    public const QUOTES_DIRECTORY = 'archivos/cotizaciones';

    public function storeItemSpecification(UploadedFile $file, int $itemId): string
    {
        return $file->storeAs(
            self::SPECIFICATIONS_DIRECTORY,
            "especificacion_{$itemId}.{$file->extension()}",
            'public',
        );
    }

    public function storeItemTechnicalSheet(UploadedFile $file, int $itemId): string
    {
        return $file->storeAs(
            self::TECHNICAL_SHEETS_DIRECTORY,
            "ficha_{$itemId}.{$file->extension()}",
            'public',
        );
    }

    public function storeQuote(UploadedFile $file, string $type): string
    {
        $prefix = match ($type) {
            'valido' => 'cotizacion_valida_',
            'propuesto_1' => 'cotizacion_propuesto1_',
            'propuesto_2' => 'cotizacion_propuesto2_',
            default => 'cotizacion_',
        };

        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeName = Str::slug($originalName, '_') ?: 'archivo';
        $extension = strtolower($file->extension() ?: $file->getClientOriginalExtension() ?: 'pdf');
        $fileName = $prefix.$safeName.'.'.$extension;

        if (Storage::disk('public')->exists(self::QUOTES_DIRECTORY.'/'.$fileName)) {
            $fileName = $prefix.$safeName.'_'.Str::lower(Str::random(8)).'.'.$extension;
        }

        return $file->storeAs(self::QUOTES_DIRECTORY, $fileName, 'public');
    }

    public function normalize(?string $value): ?string
    {
        $rawPath = trim((string) $value);

        if ($rawPath === '') {
            return null;
        }

        if (Str::startsWith($rawPath, ['http://', 'https://'])) {
            $rawPath = (string) parse_url($rawPath, PHP_URL_PATH);
        }

        $path = str_replace('\\', '/', urldecode($rawPath));
        $path = preg_replace('~[?#].*$~', '', $path);

        foreach (['/public/', '/storage/'] as $marker) {
            $position = stripos($path, $marker);

            if ($position !== false) {
                $path = substr($path, $position + strlen($marker));
                break;
            }
        }

        $path = ltrim($path, '/');
        $path = preg_replace('#^(public|storage)/+#i', '', $path);
        $path = preg_replace('#/+#', '/', (string) $path);

        if ($path === '' || collect(explode('/', $path))->contains('..')) {
            return null;
        }

        return $path;
    }

    public function exists(?string $value): bool
    {
        $path = $this->normalize($value);

        return $path !== null && Storage::disk('public')->exists($path);
    }

    public function url(?string $value): ?string
    {
        $rawPath = trim((string) $value);

        if (Str::startsWith($rawPath, ['http://', 'https://'])) {
            return $rawPath;
        }

        $path = $this->normalize($rawPath);

        if ($path === null || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    public function absolutePath(?string $value): ?string
    {
        $rawPath = trim((string) $value);

        if ($rawPath !== '' && is_file($rawPath) && is_readable($rawPath)) {
            return $rawPath;
        }

        $path = $this->normalize($rawPath);

        if ($path === null || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $absolutePath = Storage::disk('public')->path($path);

        return is_readable($absolutePath) ? $absolutePath : null;
    }
}

<?php

namespace App\Services\Files;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

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
        $rawPath = trim((string) $value);

        if (Str::startsWith($rawPath, ['http://', 'https://'])) {
            return true;
        }

        $path = $this->normalize($rawPath);

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

    /**
     * Downloads an HTTPS PDF into an owner-only temporary file.
     * The caller is responsible for deleting the returned path.
     */
    public function materializeRemotePdf(string $url): string
    {
        if (! $this->isConfiguredRepositoryUrl($url)) {
            throw new RuntimeException('La especificación remota debe usar HTTPS y pertenecer al repositorio configurado.');
        }

        $path = tempnam(sys_get_temp_dir(), 'sipre-pdf-');
        if ($path === false) {
            throw new RuntimeException('No se pudo crear el archivo temporal para la especificación remota.');
        }
        @chmod($path, 0600);

        try {
            $requestUrl = $url;
            $response = null;

            for ($redirects = 0; $redirects <= 3; $redirects++) {
                $response = Http::accept('application/pdf')
                    ->connectTimeout((float) config('services.repository.connect_timeout', 10))
                    ->timeout((float) config('services.repository.timeout', 60))
                    ->withoutRedirecting()
                    ->sink($path)
                    ->get($requestUrl);

                if (! $response->redirect()) {
                    break;
                }

                $location = $response->header('Location');
                $redirectUrl = is_string($location) ? $this->resolveRedirectUrl($requestUrl, $location) : null;

                if ($redirectUrl === null || ! $this->isConfiguredRepositoryUrl($redirectUrl)) {
                    throw new RuntimeException('La especificación remota redirige fuera del repositorio configurado.');
                }

                $requestUrl = $redirectUrl;
            }

            if ($response === null || $response->redirect() || ! $response->successful()) {
                throw new RuntimeException('No se pudo descargar la especificación remota.');
            }

            $maxBytes = (int) config('services.repository.max_pdf_download_bytes', 25 * 1024 * 1024);
            $contentLength = $response->header('Content-Length');
            if ($contentLength !== null && is_numeric($contentLength) && (int) $contentLength > $maxBytes) {
                throw new RuntimeException('La especificación remota excede el tamaño permitido.');
            }

            $mime = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
            if (! in_array($mime, ['application/pdf', 'application/x-pdf', 'application/octet-stream'], true)) {
                throw new RuntimeException('La especificación remota no tiene un MIME de PDF válido.');
            }

            $size = filesize($path);
            $header = file_get_contents($path, false, null, 0, 1024);
            if ($size === false || $size < 5 || $size > $maxBytes || $header === false || ! str_starts_with($header, '%PDF-')) {
                throw new RuntimeException('La especificación remota no es un PDF válido.');
            }

            return $path;
        } catch (ConnectionException $exception) {
            @unlink($path);

            throw new RuntimeException('No se pudo conectar para descargar la especificación remota.', previous: $exception);
        } catch (\Throwable $exception) {
            @unlink($path);

            throw $exception;
        }
    }

    public function isHttpsUrl(?string $value): bool
    {
        $url = filter_var(trim((string) $value), FILTER_VALIDATE_URL);

        return $url !== false && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https';
    }

    private function isConfiguredRepositoryUrl(string $url): bool
    {
        $candidate = parse_url($url);
        $repository = parse_url((string) config('services.repository.endpoint'));

        if (! is_array($candidate) || ! is_array($repository)
            || strtolower((string) ($candidate['scheme'] ?? '')) !== 'https'
            || strtolower((string) ($repository['scheme'] ?? '')) !== 'https') {
            return false;
        }

        $candidateHost = strtolower((string) ($candidate['host'] ?? ''));
        $repositoryHost = strtolower((string) ($repository['host'] ?? ''));
        $candidatePort = $candidate['port'] ?? 443;
        $repositoryPort = $repository['port'] ?? 443;

        return $candidateHost !== ''
            && hash_equals($repositoryHost, $candidateHost)
            && $candidatePort === $repositoryPort;
    }

    private function resolveRedirectUrl(string $baseUrl, string $location): ?string
    {
        $location = trim($location);
        if ($location === '') {
            return null;
        }

        if (filter_var($location, FILTER_VALIDATE_URL) !== false) {
            return $location;
        }

        $base = parse_url($baseUrl);
        if (! is_array($base) || ! isset($base['scheme'], $base['host'])) {
            return null;
        }

        $authority = $base['scheme'].'://'.$base['host'].(isset($base['port']) ? ':'.$base['port'] : '');
        if (str_starts_with($location, '//')) {
            return $base['scheme'].':'.$location;
        }
        if (str_starts_with($location, '/')) {
            return $authority.$location;
        }

        $directory = rtrim(str_replace('\\', '/', dirname((string) ($base['path'] ?? '/'))), '/');

        return $authority.($directory === '' ? '/' : $directory.'/').$location;
    }
}

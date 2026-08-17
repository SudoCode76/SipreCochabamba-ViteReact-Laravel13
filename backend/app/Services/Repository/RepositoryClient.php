<?php

namespace App\Services\Repository;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class RepositoryClient
{
    /**
     * @param  UploadedFile|array<int, UploadedFile>  $files
     * @param  array<string, mixed>  $metadata
     * @return array<int, RepositoryUploadResult>
     */
    public function upload(UploadedFile|array $files, array $metadata = []): array
    {
        $files = is_array($files) ? array_values($files) : [$files];

        if ($files === []) {
            throw new RuntimeException('Debe proporcionar al menos un archivo para cargar.');
        }

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                throw new RuntimeException('Todos los archivos deben ser instancias de UploadedFile.');
            }
        }

        $endpoint = (string) config('services.repository.endpoint');

        if (blank($endpoint)) {
            throw new RuntimeException('El repositorio externo no está configurado. Revise REPOSITORY_API_URL.');
        }

        $payload = array_merge([
            'sistema_id' => (string) config('services.repository.system_id'),
            'collector' => (string) config('services.repository.collector'),
        ], $metadata);

        $streams = [];

        try {
            $request = $this->http();

            foreach ($files as $file) {
                $stream = fopen($file->getRealPath(), 'r');

                if ($stream === false) {
                    throw new RuntimeException("No se pudo abrir el archivo {$file->getClientOriginalName()} para su carga.");
                }

                $streams[] = $stream;
                $request->attach('file[]', $stream, $file->getClientOriginalName());
            }

            $response = $request->post($endpoint, $payload);
        } catch (ConnectionException $exception) {
            throw new RepositoryClientException(
                'No se pudo conectar con el repositorio externo.',
                $endpoint,
                null,
                $exception->getMessage(),
                'connection_error',
            );
        } finally {
            foreach ($streams as $stream) {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        }

        return $this->resultsOrFail($response, $endpoint, count($files));
    }

    private function http(): PendingRequest
    {
        return Http::asMultipart()
            ->acceptJson()
            ->connectTimeout((float) config('services.repository.connect_timeout', 10))
            ->timeout((float) config('services.repository.timeout', 60));
    }

    /**
     * @return array<int, RepositoryUploadResult>
     */
    private function resultsOrFail(Response $response, string $endpoint, int $fileCount): array
    {
        $payload = $this->responsePayload($response);

        if ($response->status() !== 201) {
            throw new RepositoryClientException(
                data_get($payload, 'message') ?: 'El repositorio externo rechazó el archivo.',
                $endpoint,
                $response->status(),
                $payload,
            );
        }

        $entries = data_get($payload, 'response');

        if (! is_array($payload)
            || data_get($payload, 'status') !== true
            || ! is_array($entries)
            || count($entries) !== $fileCount) {
            throw new RepositoryClientException(
                'El repositorio externo devolvió una respuesta inválida.',
                $endpoint,
                $response->status(),
                $payload,
                'invalid_response',
            );
        }

        $results = [];

        foreach ($entries as $entry) {
            if (! is_array($entry)
                || ! filled(data_get($entry, 'id_repository'))
                || ! filled(data_get($entry, 'url_file'))) {
                throw new RepositoryClientException(
                    'El repositorio externo devolvió una respuesta inválida.',
                    $endpoint,
                    $response->status(),
                    $payload,
                    'invalid_response',
                );
            }

            $results[] = new RepositoryUploadResult(
                (string) data_get($entry, 'id_repository'),
                (string) data_get($entry, 'url_file'),
                $entry,
            );
        }

        return $results;
    }

    private function responsePayload(Response $response): array|string|null
    {
        $json = $response->json();

        if (is_array($json)) {
            return $json;
        }

        return $response->body();
    }
}

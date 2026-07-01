<?php

namespace App\Services\Citizenship;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class CiudadaniaDigitalClient
{
    public function createAuthenticationUrl(string $redirectUri): array
    {
        $this->ensureConfigured();

        $endpoint = $this->url('autenticacion-v2/url');
        $requestPayload = array_merge($this->credentials(), [
            'redirect_uri' => $redirectUri,
        ]);

        try {
            $response = $this->http()->post($endpoint, $requestPayload);
        } catch (ConnectionException $exception) {
            throw $this->connectionException($endpoint, $requestPayload, $exception, 'authentication_url');
        }

        return $this->jsonOrFail($response, $endpoint, $requestPayload, 'authentication_url', 'Ciudadanía Digital rechazó la autenticación.');
    }

    public function createSigningUrl(string $filePath, array $payload): array
    {
        $this->ensureConfigured();

        $endpoint = $this->url('aprovador-v2/url');
        $requestPayload = array_merge($this->credentials(), $payload);

        try {
            $response = $this->http()
                ->attach('documento', Storage::disk('local')->get($filePath), basename($filePath))
                ->post($endpoint, $requestPayload);
        } catch (ConnectionException $exception) {
            throw $this->connectionException($endpoint, $requestPayload, $exception, 'create_url');
        }

        return $this->jsonOrFail($response, $endpoint, $requestPayload, 'create_url', 'Ciudadanía Digital rechazó la solicitud de firma.');
    }

    public function userInfo(?string $accessToken = null): array
    {
        $this->ensureConfigured();

        $endpoint = $this->url('users/info');
        $requestPayload = array_filter(array_merge($this->credentials(), [
            'acces_token' => $accessToken,
        ]));

        try {
            $response = $this->http()->post($endpoint, $requestPayload);
        } catch (ConnectionException $exception) {
            throw $this->connectionException($endpoint, $requestPayload, $exception, 'user_info');
        }

        return $this->jsonOrFail($response, $endpoint, $requestPayload, 'user_info', 'No se pudo obtener la informacion del usuario de Ciudadania Digital.');
    }

    public function signedDocuments(?string $accessToken = null, string $status = 'ACEPTADO'): array
    {
        $this->ensureConfigured();

        $endpoint = $this->url('aprovador-v2/document');
        $requestPayload = array_filter(array_merge($this->credentials(), [
            'acces_token' => $accessToken,
            'status' => $status,
        ]));

        try {
            $response = $this->http()->post($endpoint, $requestPayload);
        } catch (ConnectionException $exception) {
            throw $this->connectionException($endpoint, $requestPayload, $exception, 'fetch_signed_documents');
        }

        return $this->jsonOrFail($response, $endpoint, $requestPayload, 'fetch_signed_documents', 'No se pudieron obtener los documentos firmados.');
    }

    public function downloadDocument(string $url): string
    {
        try {
            $response = $this->http()->accept('application/pdf')->get($url);
        } catch (ConnectionException $exception) {
            throw $this->connectionException($url, [], $exception, 'download_signed_document');
        }

        if (! $response->successful()) {
            throw new CiudadaniaDigitalException(
                'No se pudo descargar el documento firmado desde Ciudadania Digital.',
                $url,
                $response->status(),
                null,
                $this->responsePayload($response),
                'download_signed_document',
            );
        }

        return $response->body();
    }

    public function validateSignedDocument(string $content, string $filename, ?string $accessToken = null): array
    {
        $this->ensureConfigured();

        $endpoint = $this->url('aprovador-v2/validate');
        $requestPayload = array_filter(array_merge($this->credentials(), [
            'acces_token' => $accessToken,
        ]));

        try {
            $response = $this->http()
                ->attach('documento', $content, $filename)
                ->post($endpoint, $requestPayload);
        } catch (ConnectionException $exception) {
            throw $this->connectionException($endpoint, $requestPayload, $exception, 'validate_signed_document');
        }

        return $this->jsonOrFail($response, $endpoint, $requestPayload, 'validate_signed_document', 'No se pudo validar el documento firmado.');
    }

    public function logout(?string $accessToken = null, ?string $redirectUri = null): ?array
    {
        if (! $accessToken || ! $this->isConfigured()) {
            return null;
        }

        $endpoint = $this->url('autenticacion-v2/user-logout');
        $requestPayload = array_merge($this->credentials(), [
            'acces_token' => $accessToken,
            'redirect_uri' => $redirectUri ?: config('services.ciudadania_digital.logout_redirect_uri'),
        ]);

        try {
            $response = $this->http()->post($endpoint, $requestPayload);
        } catch (ConnectionException) {
            return null;
        }

        return $response->json();
    }

    private function http(): PendingRequest
    {
        return Http::timeout((int) config('services.ciudadania_digital.timeout', 60))
            ->acceptJson();
    }

    private function url(string $path): string
    {
        $base = rtrim((string) config('services.ciudadania_digital.base_url'), '/');
        $prefix = trim((string) config('services.ciudadania_digital.prefix', ''), '/');

        return $base.'/'.($prefix ? $prefix.'/' : '').ltrim($path, '/');
    }

    private function credentials(): array
    {
        return [
            'client_id' => config('services.ciudadania_digital.client_id'),
            'secret_id' => config('services.ciudadania_digital.secret_id'),
        ];
    }

    private function jsonOrFail(Response $response, string $endpoint, array $requestPayload, string $phase, string $fallbackMessage): array
    {
        $payload = $this->responsePayload($response);

        if (! $response->successful()) {
            throw new CiudadaniaDigitalException(
                data_get($payload, 'message') ?: data_get($payload, 'error') ?: $fallbackMessage,
                $endpoint,
                $response->status(),
                $requestPayload,
                $payload,
                $phase,
            );
        }

        if (! is_array($payload)) {
            throw new CiudadaniaDigitalException(
                'Ciudadanía Digital devolvió una respuesta inválida.',
                $endpoint,
                $response->status(),
                $requestPayload,
                $payload,
                $phase,
                'invalid_response',
            );
        }

        return $payload;
    }

    private function responsePayload(Response $response): array|string|null
    {
        $json = $response->json();

        if (is_array($json)) {
            return $json;
        }

        return $response->body();
    }

    private function connectionException(string $endpoint, array $requestPayload, ConnectionException $exception, string $phase): CiudadaniaDigitalException
    {
        $message = $phase === 'create_url'
            ? 'No se pudo conectar con Ciudadanía Digital durante la aprobación del documento.'
            : 'No se pudo conectar con Ciudadanía Digital.';

        return new CiudadaniaDigitalException(
            $message,
            $endpoint,
            null,
            $requestPayload,
            $exception->getMessage(),
            $phase,
            'connection_error',
        );
    }

    private function ensureConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Ciudadanía Digital no está configurada. Revise CIUDADANIA_DIGITAL_BASE_URL, CLIENT_ID y SECRET_ID.');
        }
    }

    private function isConfigured(): bool
    {
        return filled(config('services.ciudadania_digital.base_url'))
            && filled(config('services.ciudadania_digital.client_id'))
            && filled(config('services.ciudadania_digital.secret_id'));
    }
}

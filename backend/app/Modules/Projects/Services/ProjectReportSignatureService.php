<?php

namespace App\Modules\Projects\Services;

use App\Models\Project;
use App\Models\ProjectReportSignature;
use App\Models\User;
use App\Services\Citizenship\CiudadaniaDigitalException;
use App\Services\Citizenship\CiudadaniaDigitalClient;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ProjectReportSignatureService
{
    public function __construct(
        private readonly ProjectSignableReportService $signableReportService,
        private readonly ProjectReportPdfResolver $pdfResolver,
        private readonly CiudadaniaDigitalClient $ciudadaniaDigitalClient,
    ) {}

    public function start(Project $project, string $reportKey, array $parameters, User $user): ProjectReportSignature
    {
        $this->assertCanStart($project, $reportKey, $user);

        $normalizedParameters = $this->normalizeParameters($parameters);
        $hash = $this->parametersHash($normalizedParameters);
        $previous = $this->latestSigned($project, $reportKey, $hash);

        if ($previous?->signed_file_path && Storage::disk('local')->exists($previous->signed_file_path)) {
            $basePath = $previous->signed_file_path;
        } else {
            $pdf = $this->pdfResolver->resolve($project, $reportKey, $normalizedParameters);
            $basePath = $this->storeBasePdf($project, $reportKey, $pdf['content']);
        }

        $signature = ProjectReportSignature::query()->create([
            'id_proyecto' => $project->id_proyecto,
            'trace_id' => (string) Str::uuid(),
            'report_key' => $reportKey,
            'parameters' => $normalizedParameters,
            'parameters_hash' => $hash,
            'status' => 'pending',
            'id_usuario' => $user->id_usuario,
            'base_file_path' => $basePath,
        ]);

        if (! $this->accessTokenFrom($parameters)) {
            return $this->requestAuthentication($signature);
        }

        return $this->requestApproval($signature, $parameters);
    }

    public function continueAfterAuthentication(int|string $signatureId, array $payload): ProjectReportSignature
    {
        $signature = ProjectReportSignature::query()->findOrFail($signatureId);
        $accessToken = $this->accessTokenFrom($payload);

        if (! $accessToken) {
            throw new RuntimeException($this->messageWithTrace('Ciudadanía Digital no devolvió el token de acceso.', $signature));
        }

        Cache::put($this->accessTokenCacheKey($signature), $accessToken, now()->addMinutes(30));

        return $this->requestApproval($signature, ['acces_token' => $accessToken]);
    }

    public function complete(string $code, array $payload = []): ProjectReportSignature
    {
        $signature = ProjectReportSignature::query()
            ->where('code', $code)
            ->orWhere('id', is_numeric($code) ? (int) $code : 0)
            ->latest('id')
            ->firstOrFail();

        try {
            $accessToken = $this->accessTokenFrom($payload) ?: Cache::get($this->accessTokenCacheKey($signature));
            $documentResponse = $this->ciudadaniaDigitalClient->approvedDocument($accessToken, (string) $signature->code);
            $signature->forceFill([
                'response_payload' => array_merge($signature->response_payload ?? [], [
                    'callback' => $this->sanitizePayload($payload),
                    'document_approval' => $documentResponse,
                ]),
            ])->save();

            $documentUrl = $this->documentUrlFromApproval($signature, $documentResponse);
            $content = $this->ciudadaniaDigitalClient->downloadDocument($documentUrl);

            if (! str_starts_with($content, '%PDF')) {
                throw new RuntimeException('El documento firmado descargado no es un PDF valido.');
            }

            $validationResponse = $this->ciudadaniaDigitalClient->validateSignedDocument($content, $this->signedDocumentName($signature), $accessToken);
            $signature->forceFill([
                'response_payload' => array_merge($signature->response_payload ?? [], [
                    'matched_document' => data_get($documentResponse, 'data'),
                    'signed_document_url' => $documentUrl,
                    'validation' => $validationResponse,
                ]),
            ])->save();

            if (! $this->isValidationSuccessful($validationResponse)) {
                throw new RuntimeException('Ciudadania Digital no valido correctamente el documento firmado.');
            }

            $signedPath = $this->storeSignedPdf($signature, $content);
            $logoutResponse = $this->ciudadaniaDigitalClient->logout($accessToken, $this->logoutCallbackUrl($signature));
            $logoutRedirectUrl = is_array($logoutResponse) ? $this->redirectUrlFromResponse($logoutResponse, false) : null;

            $signature->forceFill([
                'status' => 'signed',
                'signed_file_path' => $signedPath,
                'response_payload' => array_merge($signature->response_payload ?? [], [
                    'callback' => $this->sanitizePayload($payload),
                    'document_approval' => $documentResponse,
                    'matched_document' => data_get($documentResponse, 'data'),
                    'signed_document_url' => $documentUrl,
                    'validation' => $validationResponse,
                    'logout' => $logoutResponse,
                    'logout_redirect_url' => $logoutRedirectUrl,
                ]),
                'signed_at' => now(),
            ])->save();

            Cache::forget($this->accessTokenCacheKey($signature));

            return $signature->refresh();
        } catch (CiudadaniaDigitalException $exception) {
            $this->recordExternalError($signature, $exception, ['callback' => $this->sanitizePayload($payload)]);

            throw new RuntimeException($this->messageWithTrace($exception->getMessage(), $signature));
        } catch (RuntimeException $exception) {
            $signature->forceFill([
                'status' => 'error',
                'error_message' => $exception->getMessage(),
                'response_payload' => array_merge($signature->response_payload ?? [], ['callback' => $payload]),
            ])->save();

            throw $exception;
        }
    }

    public function history(Project $project, string $reportKey, ?string $parametersHash = null): array
    {
        return ProjectReportSignature::query()
            ->with('user')
            ->where('id_proyecto', $project->id_proyecto)
            ->where('report_key', $reportKey)
            ->when($parametersHash, fn ($query) => $query->where('parameters_hash', $parametersHash))
            ->latest('id')
            ->get()
            ->map(fn (ProjectReportSignature $signature): array => $this->serialize($signature))
            ->all();
    }

    public function latest(Project $project, string $reportKey, ?string $parametersHash = null): ?ProjectReportSignature
    {
        return ProjectReportSignature::query()
            ->with('user')
            ->where('id_proyecto', $project->id_proyecto)
            ->where('report_key', $reportKey)
            ->when($parametersHash, fn ($query) => $query->where('parameters_hash', $parametersHash))
            ->latest('id')
            ->first();
    }

    public function signedPdfContent(ProjectReportSignature $signature): string
    {
        $externalUrl = data_get($signature->response_payload, 'signed_document_url');

        if (is_string($externalUrl) && $externalUrl !== '') {
            try {
                $content = $this->ciudadaniaDigitalClient->downloadDocument($externalUrl);

                if (str_starts_with($content, '%PDF')) {
                    return $content;
                }
            } catch (CiudadaniaDigitalException $exception) {
                Log::notice('No se pudo descargar el PDF firmado externo, se usara copia local si existe.', [
                    'trace_id' => $signature->trace_id,
                    'signature_id' => $signature->id,
                    'phase' => $exception->phase(),
                    'http_status' => $exception->httpStatus(),
                ]);
            }
        }

        if ($signature->signed_file_path && Storage::disk('local')->exists($signature->signed_file_path)) {
            return Storage::disk('local')->get($signature->signed_file_path);
        }

        throw new RuntimeException('No existe un documento firmado para este reporte.');
    }

    public function latestSigned(Project $project, string $reportKey, ?string $parametersHash = null): ?ProjectReportSignature
    {
        return ProjectReportSignature::query()
            ->where('id_proyecto', $project->id_proyecto)
            ->where('report_key', $reportKey)
            ->when($parametersHash, fn ($query) => $query->where('parameters_hash', $parametersHash))
            ->where('status', 'signed')
            ->whereNotNull('signed_file_path')
            ->latest('id')
            ->first();
    }

    public function serialize(ProjectReportSignature $signature): array
    {
        return [
            'id' => $signature->id,
            'trace_id' => $signature->trace_id,
            'project_id' => $signature->id_proyecto,
            'report_key' => $signature->report_key,
            'parameters' => $signature->parameters ?? [],
            'status' => $signature->status,
            'code' => $signature->code,
            'user_id' => $signature->id_usuario,
            'user_name' => $signature->user?->funcionario,
            'sent_at' => $signature->sent_at?->toIso8601String(),
            'signed_at' => $signature->signed_at?->toIso8601String(),
            'has_signed_file' => filled($signature->signed_file_path),
            'redirect_url' => data_get($signature->response_payload, 'redirect_url')
                ?: data_get($signature->response_payload, 'data.url')
                ?: data_get($signature->response_payload, 'data.link')
                ?: data_get($signature->response_payload, 'url')
                ?: (is_string(data_get($signature->response_payload, 'data')) ? data_get($signature->response_payload, 'data') : null),
            'logout_redirect_url' => data_get($signature->response_payload, 'logout_redirect_url'),
            'signed_document_url' => data_get($signature->response_payload, 'signed_document_url'),
            'validation' => data_get($signature->response_payload, 'validation'),
            'citizenship_user' => data_get($signature->response_payload, 'ciudadania_user.data'),
            'validation_records' => data_get($signature->response_payload, 'validation.data.registros', []),
            'error_message' => $signature->error_message,
        ];
    }

    public function normalizeParameters(array $parameters): array
    {
        $allowed = Arr::only($parameters, ['format', 'type', 'fecha']);
        ksort($allowed);

        return array_filter($allowed, fn ($value): bool => $value !== null && $value !== '');
    }

    public function parametersHash(array $parameters): string
    {
        return hash('sha256', json_encode($parameters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function assertCanStart(Project $project, string $reportKey, User $user): void
    {
        $report = $this->signableReportService->findEnabled($reportKey);

        if (! $report) {
            throw ValidationException::withMessages([
                'report_key' => ['Este reporte no está habilitado para firma digital.'],
            ]);
        }

        if (! $this->signableReportService->projectStatusAllowsSigning($report, $project->isFrozen())) {
            throw ValidationException::withMessages([
                'project' => ['Este reporte solo permite firma digital en proyectos FINALIZADOS.'],
            ]);
        }

        if (! $this->signableReportService->canSign($user, $reportKey)) {
            throw new AuthorizationException('No tiene permisos para firmar este reporte.');
        }
    }

    private function storeBasePdf(Project $project, string $reportKey, string $content): string
    {
        $path = sprintf(
            'project-signatures/%d/%s/base-%s.pdf',
            $project->id_proyecto,
            $reportKey,
            Str::uuid()
        );

        Storage::disk('local')->put($path, $content);

        return $path;
    }

    private function storeSignedPdf(ProjectReportSignature $signature, string $content): string
    {
        $path = sprintf(
            'project-signatures/%d/%s/signed-%s.pdf',
            $signature->id_proyecto,
            $signature->report_key,
            Str::uuid()
        );

        Storage::disk('local')->put($path, $content);

        return $path;
    }

    private function extractPdfContent(array $response): string
    {
        $document = data_get($response, 'data.documento')
            ?: data_get($response, 'data.document')
            ?: data_get($response, 'data.file')
            ?: data_get($response, 'documento')
            ?: data_get($response, 'document')
            ?: data_get($response, 'file');

        if (! is_string($document) || $document === '') {
            throw new RuntimeException('Ciudadanía Digital no devolvió el PDF firmado en la respuesta.');
        }

        if (str_starts_with($document, 'data:')) {
            $document = (string) Str::after($document, ',');
        }

        $content = base64_decode($document, true);

        if ($content === false || ! str_starts_with($content, '%PDF')) {
            throw new RuntimeException('El documento firmado recibido no es un PDF válido.');
        }

        return $content;
    }

    private function findSignedDocument(ProjectReportSignature $signature, array $response): array
    {
        $documents = data_get($response, 'data', []);
        $expectedName = $this->signedDocumentName($signature);
        $expectedNameWithoutExtension = (string) Str::beforeLast($expectedName, '.pdf');

        if (! is_array($documents)) {
            throw new RuntimeException($this->messageWithTrace('Ciudadania Digital no devolvio una lista valida de documentos firmados.', $signature));
        }

        foreach ($documents as $document) {
            if (! is_array($document)) {
                continue;
            }

            $documentNames = array_filter([
                data_get($document, 'nombre_documento'),
                data_get($document, 'nombre'),
            ]);

            foreach ($documentNames as $documentName) {
                $documentName = trim((string) $documentName);

                if ($documentName === $expectedName || $documentName === $expectedNameWithoutExtension) {
                    $url = data_get($document, 'url_documento');

                    if (! is_string($url) || $url === '') {
                        throw new RuntimeException($this->messageWithTrace('El documento firmado encontrado no tiene URL de descarga.', $signature));
                    }

                    return $document;
                }
            }
        }

        throw new RuntimeException($this->messageWithTrace('No se encontro el documento firmado correspondiente a esta solicitud.', $signature));
    }

    private function documentUrlFromApproval(ProjectReportSignature $signature, array $response): string
    {
        $url = data_get($response, 'data.url_documento')
            ?: data_get($response, 'url_documento')
            ?: data_get($response, 'data.url_document')
            ?: data_get($response, 'url_document');

        if (! is_string($url) || $url === '') {
            throw new RuntimeException($this->messageWithTrace('Ciudadania Digital no devolvio la URL del documento firmado.', $signature));
        }

        return $url;
    }

    private function isValidationSuccessful(array $response): bool
    {
        return data_get($response, 'data.verificacion_exitosa') === true
            || data_get($response, 'data.verificacion_exitosa') === 'true'
            || data_get($response, 'data.verification_successful') === true
            || data_get($response, 'data.verification_successful') === 'true';
    }

    private function previousSignedFor(ProjectReportSignature $signature): ?ProjectReportSignature
    {
        return ProjectReportSignature::query()
            ->where('id_proyecto', $signature->id_proyecto)
            ->where('report_key', $signature->report_key)
            ->where('parameters_hash', $signature->parameters_hash)
            ->where('status', 'signed')
            ->whereNotNull('signed_file_path')
            ->where('id', '!=', $signature->id)
            ->latest('id')
            ->first();
    }

    private function assertCanDeriveFromPreviousSignature(
        ProjectReportSignature $signature,
        ProjectReportSignature $previousSigned,
        ?array $userInfo,
        ?string $accessToken
    ): string {
        $currentDocumentNumber = $this->citizenshipDocumentNumber($userInfo);

        if ($currentDocumentNumber === '') {
            throw ValidationException::withMessages([
                'signature' => ['No se pudo validar el documento de identidad del usuario de Ciudadanía Digital.'],
            ]);
        }

        $documentResponse = $this->ciudadaniaDigitalClient->approvedDocument($accessToken, (string) $previousSigned->code);
        $documentUrl = $this->documentUrlFromApproval($previousSigned, $documentResponse);
        $content = $this->ciudadaniaDigitalClient->downloadDocument($documentUrl);

        if (! str_starts_with($content, '%PDF')) {
            throw new RuntimeException('El documento firmado anterior descargado no es un PDF valido.');
        }

        $validationResponse = $this->ciudadaniaDigitalClient->validateSignedDocument(
            $content,
            $this->signedDocumentName($previousSigned),
            $accessToken
        );

        if (! $this->isValidationSuccessful($validationResponse)) {
            throw new RuntimeException('Ciudadania Digital no valido correctamente el documento firmado anterior.');
        }

        $previousSigned->forceFill([
            'response_payload' => array_merge($previousSigned->response_payload ?? [], [
                'document_approval' => $documentResponse,
                'signed_document_url' => $documentUrl,
                'validation' => $validationResponse,
            ]),
        ])->save();

        foreach ($this->validationRecords($validationResponse) as $record) {
            if ($this->normalizeDocumentNumber(data_get($record, 'nro_documento')) === $currentDocumentNumber) {
                throw ValidationException::withMessages([
                    'signature' => ['Esta persona ya firmó este documento.'],
                ]);
            }
        }

        return $documentUrl;
    }

    private function citizenshipDocumentNumber(?array $userInfo): string
    {
        return $this->normalizeDocumentNumber(
            data_get($userInfo, 'data.numero_documento')
            ?: data_get($userInfo, 'data.nro_documento')
            ?: data_get($userInfo, 'numero_documento')
            ?: data_get($userInfo, 'nro_documento')
        );
    }

    private function validationRecords(array $response): array
    {
        $records = data_get($response, 'data.registros', []);

        return is_array($records) ? $records : [];
    }

    private function normalizeDocumentNumber(mixed $value): string
    {
        return Str::upper(preg_replace('/[^A-Za-z0-9]/', '', (string) $value) ?: '');
    }

    private function safeUserInfo(ProjectReportSignature $signature, ?string $accessToken): ?array
    {
        if (! $accessToken) {
            return null;
        }

        try {
            return $this->ciudadaniaDigitalClient->userInfo($accessToken);
        } catch (CiudadaniaDigitalException $exception) {
            Log::notice('No se pudo obtener informacion auxiliar del usuario de Ciudadania Digital.', [
                'trace_id' => $signature->trace_id,
                'signature_id' => $signature->id,
                'phase' => $exception->phase(),
                'http_status' => $exception->httpStatus(),
            ]);

            return null;
        }
    }

    private function requestAuthentication(ProjectReportSignature $signature): ProjectReportSignature
    {
        try {
            $redirectUri = $this->loginCallbackUrl($signature);
            $response = $this->ciudadaniaDigitalClient->createAuthenticationUrl($redirectUri);
            $redirectUrl = $this->redirectUrlFromResponse($response);

            $signature->forceFill([
                'status' => 'auth_pending',
                'request_payload' => ['redirect_uri' => $redirectUri],
                'response_payload' => array_merge($response, ['redirect_url' => $redirectUrl]),
                'sent_at' => now(),
            ])->save();

            return $signature->refresh();
        } catch (CiudadaniaDigitalException $exception) {
            $this->recordExternalError($signature, $exception);

            throw ValidationException::withMessages([
                'signature' => [$this->messageWithTrace($exception->getMessage(), $signature)],
            ]);
        } catch (RuntimeException $exception) {
            $signature->forceFill([
                'status' => 'error',
                'error_message' => $this->messageWithTrace($exception->getMessage(), $signature),
            ])->save();

            throw ValidationException::withMessages([
                'signature' => [$this->messageWithTrace($exception->getMessage(), $signature)],
            ]);
        }
    }

    private function requestApproval(ProjectReportSignature $signature, array $parameters): ProjectReportSignature
    {
        $accessToken = $this->accessTokenFrom($parameters);
        $signatureCode = $this->signatureCode($signature);
        $validFrom = now();
        $validTo = $validFrom->copy()->addMonth();
        $previousSigned = $this->previousSignedFor($signature);

        if ($signature->code !== $signatureCode) {
            $signature->forceFill(['code' => $signatureCode])->save();
        }

        $payload = [
            'acces_token' => $accessToken,
            'save' => 'false',
            'version' => 'V2',
            'extencion_documento' => 'PDF',
            'descripcion_documento' => $this->description($signature->project, $signature->report_key),
            'nombre_documento' => $this->signedDocumentName($signature),
            'redirect_uri' => $this->approvalCallbackUrl($signature),
            'code' => $signatureCode,
            'format_sign' => 'false',
            'asignaciones' => '',
            'num_documento' => '',
            'valid_from' => $validFrom->toIso8601String(),
            'valid_to' => $validTo->toIso8601String(),
        ];

        try {
            if ($accessToken) {
                Cache::put($this->accessTokenCacheKey($signature), $accessToken, now()->addMinutes(30));
            }

            $userInfo = $this->safeUserInfo($signature, $accessToken);
            if ($previousSigned) {
                $payload['firmar_derivacion'] = 'true';
                $payload['url_document'] = $this->assertCanDeriveFromPreviousSignature(
                    $signature,
                    $previousSigned,
                    $userInfo,
                    $accessToken
                );
                $response = $this->ciudadaniaDigitalClient->createDerivedSigningUrl($payload);
            } else {
                $payload['is_derivated'] = 'true';
                $response = $this->ciudadaniaDigitalClient->createSigningUrl($signature->base_file_path, $payload);
            }
            $safeRequestPayload = Arr::except($payload, ['acces_token']);
            $signature->forceFill([
                'response_payload' => array_merge($response, [
                    'ciudadania_user' => $userInfo,
                    'document_name' => $this->signedDocumentName($signature),
                ]),
                'request_payload' => $safeRequestPayload,
            ])->save();

            $redirectUrl = $this->redirectUrlFromResponse($response);

            $signature->forceFill([
                'status' => 'sent',
                'code' => $signatureCode,
                'request_payload' => $safeRequestPayload,
                'response_payload' => array_merge($response, [
                    'ciudadania_user' => $userInfo,
                    'document_name' => $this->signedDocumentName($signature),
                    'redirect_url' => $redirectUrl,
                ]),
                'sent_at' => now(),
            ])->save();

            return $signature->refresh();
        } catch (CiudadaniaDigitalException $exception) {
            $this->recordExternalError($signature, $exception);

            throw ValidationException::withMessages([
                'signature' => [$this->messageWithTrace($exception->getMessage(), $signature)],
            ]);
        } catch (RuntimeException $exception) {
            $signature->forceFill([
                'status' => 'error',
                'error_message' => $this->messageWithTrace($exception->getMessage(), $signature),
            ])->save();

            throw ValidationException::withMessages([
                'signature' => [$this->messageWithTrace($exception->getMessage(), $signature)],
            ]);
        }
    }

    private function redirectUrlFromResponse(array $response, bool $required = true): ?string
    {
        $url = data_get($response, 'data.url')
            ?: data_get($response, 'data.redirect_url')
            ?: data_get($response, 'data.redirect')
            ?: data_get($response, 'data.link')
            ?: data_get($response, 'data.url_firma')
            ?: data_get($response, 'data.urlFirma')
            ?: data_get($response, 'url')
            ?: data_get($response, 'redirect_url')
            ?: data_get($response, 'redirect')
            ?: data_get($response, 'link')
            ?: (is_string(data_get($response, 'data')) ? data_get($response, 'data') : null);

        if (! is_string($url) || $url === '') {
            $url = $this->firstUrlInPayload($response);
        }

        if (! is_string($url) || $url === '') {
            if (! $required) {
                return null;
            }

            throw new RuntimeException('Ciudadanía Digital no devolvió una URL de firma válida.');
        }

        return $url;
    }

    private function firstUrlInPayload(array|string|null $payload): ?string
    {
        if (is_string($payload)) {
            return filter_var($payload, FILTER_VALIDATE_URL) ? $payload : null;
        }

        if (! is_array($payload)) {
            return null;
        }

        foreach ($payload as $value) {
            $url = $this->firstUrlInPayload($value);

            if ($url !== null) {
                return $url;
            }
        }

        return null;
    }

    private function loginCallbackUrl(ProjectReportSignature $signature): string
    {
        return $this->configuredCallbackUrl('login_redirect_uri', '/api/v1/citizenship/signature/login-callback', $signature);
    }

    private function approvalCallbackUrl(ProjectReportSignature $signature): string
    {
        return $this->configuredCallbackUrl('approval_redirect_uri', '/api/v1/citizenship/signature/approval-callback', $signature);
    }

    private function logoutCallbackUrl(ProjectReportSignature $signature): string
    {
        return $this->configuredCallbackUrl('logout_redirect_uri', '/api/v1/citizenship/signature/logout-callback', $signature);
    }

    private function configuredCallbackUrl(string $configKey, string $path, ProjectReportSignature $signature): string
    {
        $configuredUrl = (string) config('services.ciudadania_digital.'.$configKey);
        return $configuredUrl !== ''
            ? $configuredUrl
            : rtrim((string) config('app.url'), '/').$path;
    }

    private function description(Project $project, string $reportKey): string
    {
        return 'Firma digital de '.$reportKey.' del proyecto '.$project->nombre_proyecto;
    }

    private function filename(Project $project, string $reportKey): string
    {
        return Str::slug($project->nombre_proyecto.'-'.$reportKey).'.pdf';
    }

    private function signedDocumentName(ProjectReportSignature $signature): string
    {
        return sprintf('sipre_firma_%d_%s.pdf', $signature->id, Str::slug($signature->report_key, '_'));
    }

    private function signatureCode(ProjectReportSignature $signature): string
    {
        return $signature->code ?: 'code-'.$signature->id;
    }

    private function accessTokenFrom(array $payload): ?string
    {
        return data_get($payload, 'access_token')
            ?? data_get($payload, 'acces_token')
            ?? data_get($payload, 'accessToken')
            ?? data_get($payload, 'token')
            ?? data_get($payload, 'data.access_token')
            ?? data_get($payload, 'data.acces_token')
            ?? null;
    }

    private function accessTokenCacheKey(ProjectReportSignature $signature): string
    {
        return 'ciudadania_digital_signature_token:'.$signature->id;
    }

    private function recordExternalError(ProjectReportSignature $signature, CiudadaniaDigitalException $exception, array $extraResponse = []): void
    {
        $requestPayload = $this->sanitizePayload($exception->requestPayload());
        $responsePayload = $this->sanitizePayload($exception->responsePayload());

        if ($extraResponse !== []) {
            $responsePayload = is_array($responsePayload)
                ? array_merge($responsePayload, $extraResponse)
                : ['response' => $responsePayload, ...$extraResponse];
        }

        $signature->forceFill([
            'status' => 'error',
            'error_message' => $this->messageWithTrace($exception->getMessage(), $signature),
            'external_endpoint' => $exception->endpoint(),
            'external_http_status' => $exception->httpStatus(),
            'external_request_payload' => $requestPayload,
            'external_response_payload' => $responsePayload,
            'external_error_type' => $exception->errorType(),
            'external_phase' => $exception->phase(),
        ])->save();

        Log::warning('FirmaGAMC rechazó una solicitud de firma.', [
            'trace_id' => $signature->trace_id,
            'signature_id' => $signature->id,
            'project_id' => $signature->id_proyecto,
            'report_key' => $signature->report_key,
            'phase' => $exception->phase(),
            'endpoint' => $exception->endpoint(),
            'http_status' => $exception->httpStatus(),
            'error_type' => $exception->errorType(),
            'message' => $exception->getMessage(),
            'request' => $requestPayload,
            'response' => $responsePayload,
        ]);
    }

    private function messageWithTrace(string $message, ProjectReportSignature $signature): string
    {
        return $message.' Código de seguimiento: '.$signature->trace_id;
    }

    private function sanitizePayload(array|string|null $payload): array|string|null
    {
        if (is_string($payload) || $payload === null) {
            return $payload;
        }

        return collect($payload)
            ->mapWithKeys(function ($value, string $key): array {
                $normalized = Str::lower($key);

                if (Str::contains($normalized, ['secret', 'token', 'documento', 'document', 'file', 'pdf', 'cookie', 'authorization'])) {
                    return [$key => '[redacted]'];
                }

                if (is_array($value)) {
                    return [$key => $this->sanitizePayload($value)];
                }

                return [$key => $value];
            })
            ->all();
    }
}

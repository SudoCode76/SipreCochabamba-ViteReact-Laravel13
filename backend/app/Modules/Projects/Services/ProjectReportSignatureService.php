<?php

namespace App\Modules\Projects\Services;

use App\Models\Project;
use App\Models\ProjectReportPhysicalSignature;
use App\Models\ProjectReportSignature;
use App\Models\User;
use App\Services\Citizenship\CiudadaniaDigitalException;
use App\Services\Citizenship\CiudadaniaDigitalClient;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use setasign\Fpdi\Tcpdf\Fpdi;

class ProjectReportSignatureService
{
    private const DEFAULT_PHYSICAL_SIGNATURE_WIDTH = 56.0;
    private const DEFAULT_PHYSICAL_SIGNATURE_HEIGHT = 28.0;

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
        $this->deleteIncompleteAttempts($project, $reportKey, $hash, $user);

        $pdf = $this->pdfResolver->resolve($project, $reportKey, $normalizedParameters);
        $baseDocumentHash = $this->currentDocumentHash($project, $reportKey, $normalizedParameters);
        $previous = $this->latestSigned($project, $reportKey, $hash);
        $canDeriveFromPrevious = $previous
            && (
                ($previous->base_document_hash && hash_equals((string) $previous->base_document_hash, $baseDocumentHash))
                || (! $previous->base_document_hash && $this->isProjectFrozen($project))
            );

        if ($canDeriveFromPrevious && $previous?->signed_file_path && Storage::disk('local')->exists($previous->signed_file_path)) {
            $baseContent = Storage::disk('local')->get($previous->signed_file_path);
            $basePath = $previous->signed_file_path;
        } else {
            $baseContent = $pdf['content'];
            $basePath = $this->storeBasePdf($project, $reportKey, $baseContent);
        }

        $signature = ProjectReportSignature::query()->create([
            'id_proyecto' => $project->id_proyecto,
            'trace_id' => (string) Str::uuid(),
            'report_key' => $reportKey,
            'parameters' => $normalizedParameters,
            'parameters_hash' => $hash,
            'status' => 'pending',
            'code' => $this->newSignatureCode($project),
            'id_usuario' => $user->id_usuario,
            'base_file_path' => $basePath,
            'base_document_hash' => $baseDocumentHash,
        ]);

        if (! $this->accessTokenFrom($parameters)) {
            return $this->requestAuthentication($signature);
        }

        return $this->requestApproval($signature, $parameters);
    }

    public function continueAfterAuthentication(int|string $signatureId, array $payload): ProjectReportSignature
    {
        $signature = ProjectReportSignature::query()->findOrFail($signatureId);

        if ($signature->status === 'signed') {
            return $signature;
        }

        if ($signature->status === 'sent' && $this->storedRedirectUrl($signature)) {
            return $signature;
        }

        if ($signature->status === 'error') {
            throw new RuntimeException(
                $signature->error_message ?: $this->messageWithTrace('La solicitud de firma tiene un error previo.', $signature)
            );
        }

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
            ->first();

        if (! $signature) {
            throw new RuntimeException('La solicitud de firma ya no está activa. Inicie la firma nuevamente.');
        }

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
            ->where('status', 'signed')
            ->latest('id')
            ->get()
            ->map(fn (ProjectReportSignature $signature): array => $this->serialize($signature))
            ->all();
    }

    public function latestSignedSigners(Project $project, string $reportKey, ?string $parametersHash = null): array
    {
        $signature = $this->latestSigned($project, $reportKey, $parametersHash);

        return $signature
            ? $this->validationRecords(data_get($signature->response_payload, 'validation', []))
            : [];
    }

    public function physicalSignatureStatus(Project $project, string $reportKey, array $parameters, User $user): array
    {
        $normalizedParameters = $this->normalizeParameters($parameters);
        $parametersHash = $this->parametersHash($normalizedParameters);
        $logicalHash = $this->currentDocumentHash($project, $reportKey, $normalizedParameters);
        $latestSigned = $this->latestSignedForHash($project, $reportKey, $parametersHash, $logicalHash);
        $items = $this->physicalSignatures($project, $reportKey, $parametersHash, $logicalHash);
        $pageSizes = $latestSigned ? $this->signedPdfPageSizes($latestSigned) : [];
        $this->assignMissingPhysicalPlacements($items, $pageSizes);
        $items = $items->fresh(['user']);

        return [
            'count' => $items->count(),
            'already_marked' => $items->contains('id_usuario', $user->id_usuario),
            'can_mark' => (bool) $latestSigned && filled($user->firma_imagen_path),
            'needs_signature_image' => blank($user->firma_imagen_path),
            'latest_signed' => $latestSigned ? $this->serialize($latestSigned) : null,
            'page_sizes' => $pageSizes,
            'items' => $items->map(fn (ProjectReportPhysicalSignature $signature): array => $this->serializePhysicalSignature($signature))->all(),
        ];
    }

    public function markPhysicalSignature(Project $project, string $reportKey, array $parameters, User $user): array
    {
        $normalizedParameters = $this->normalizeParameters($parameters);
        $parametersHash = $this->parametersHash($normalizedParameters);
        $logicalHash = $this->currentDocumentHash($project, $reportKey, $normalizedParameters);
        $latestSigned = $this->latestSignedForHash($project, $reportKey, $parametersHash, $logicalHash);

        if (! $latestSigned) {
            throw ValidationException::withMessages([
                'signature' => ['Primero debe existir un PDF firmado digitalmente para este reporte actual.'],
            ]);
        }

        if (! $user->firma_imagen_path || ! Storage::disk('public')->exists($user->firma_imagen_path)) {
            throw ValidationException::withMessages([
                'signature_image' => ['Debe cargar una imagen de firma en su perfil antes de marcar este documento.'],
            ]);
        }

        $existing = ProjectReportPhysicalSignature::query()
            ->where('id_proyecto', $project->id_proyecto)
            ->where('report_key', $reportKey)
            ->where('parameters_hash', $parametersHash)
            ->where('logical_document_hash', $logicalHash)
            ->where('id_usuario', $user->id_usuario)
            ->first();

        $extension = pathinfo((string) $user->firma_imagen_path, PATHINFO_EXTENSION) ?: 'png';
        $snapshotPath = sprintf(
            'project-physical-signatures/%d/%s/%s/%d-%s.%s',
            $project->id_proyecto,
            $reportKey,
            $parametersHash,
            $user->id_usuario,
            Str::uuid(),
            $extension
        );

        Storage::disk('public')->put($snapshotPath, Storage::disk('public')->get($user->firma_imagen_path));

        if ($existing) {
            Storage::disk('public')->delete($existing->signature_image_path);
            $existing->forceFill([
                'signature_image_path' => $snapshotPath,
                'page' => null,
                'x' => null,
                'y' => null,
                'width' => null,
                'height' => null,
                'marked_at' => now(),
            ])->save();
        } else {
            ProjectReportPhysicalSignature::query()->create([
                'id_proyecto' => $project->id_proyecto,
                'report_key' => $reportKey,
                'parameters_hash' => $parametersHash,
                'logical_document_hash' => $logicalHash,
                'id_usuario' => $user->id_usuario,
                'signature_image_path' => $snapshotPath,
                'marked_at' => now(),
            ]);
        }

        return $this->physicalSignatureStatus($project, $reportKey, $normalizedParameters, $user);
    }

    public function updatePhysicalSignaturePositions(Project $project, string $reportKey, array $parameters, array $positions, User $user): array
    {
        $normalizedParameters = $this->normalizeParameters($parameters);
        $parametersHash = $this->parametersHash($normalizedParameters);
        $logicalHash = $this->currentDocumentHash($project, $reportKey, $normalizedParameters);
        $latestSigned = $this->latestSignedForHash($project, $reportKey, $parametersHash, $logicalHash);

        if (! $latestSigned) {
            throw ValidationException::withMessages([
                'signature' => ['No existe un PDF firmado digitalmente vigente para ajustar firmas físicas.'],
            ]);
        }

        $items = $this->physicalSignatures($project, $reportKey, $parametersHash, $logicalHash);
        $byId = $items->keyBy('id');
        $pageSizes = $this->signedPdfPageSizes($latestSigned);
        $this->assignMissingPhysicalPlacements($items, $pageSizes);
        $items = $items->fresh(['user']);
        $byId = $items->keyBy('id');
        $normalized = [];
        $seenIds = [];

        foreach ($positions as $position) {
            $id = (int) ($position['id'] ?? 0);

            if (in_array($id, $seenIds, true)) {
                throw ValidationException::withMessages([
                    'positions' => ['Una firma física fue enviada más de una vez.'],
                ]);
            }

            if (! $byId->has($id)) {
                throw ValidationException::withMessages([
                    'positions' => ['Una de las firmas no pertenece a este documento.'],
                ]);
            }

            $placement = [
                'id' => $id,
                'page' => (int) ($position['page'] ?? 1),
                'x' => round((float) ($position['x'] ?? 0), 2),
                'y' => round((float) ($position['y'] ?? 0), 2),
                'width' => round((float) ($position['width'] ?? self::DEFAULT_PHYSICAL_SIGNATURE_WIDTH), 2),
                'height' => round((float) ($position['height'] ?? self::DEFAULT_PHYSICAL_SIGNATURE_HEIGHT), 2),
            ];

            if (! $this->placementInsidePage($placement, $pageSizes)) {
                throw ValidationException::withMessages([
                    'positions' => ['Una de las firmas queda fuera de la página.'],
                ]);
            }

            $normalized[] = $placement;
            $seenIds[] = $id;
        }

        $submittedById = collect($normalized)->keyBy('id');
        $allPlacements = $items
            ->map(fn (ProjectReportPhysicalSignature $signature): array => $submittedById->get($signature->id) ?: $this->placementFromSignature($signature))
            ->values()
            ->all();

        if ($this->placementsOverlap($allPlacements)) {
            throw ValidationException::withMessages([
                'positions' => ['Las firmas físicas no pueden superponerse.'],
            ]);
        }

        DB::transaction(function () use ($byId, $normalized): void {
            foreach ($normalized as $placement) {
                $byId[$placement['id']]->forceFill(Arr::except($placement, ['id']))->save();
            }
        });

        return $this->physicalSignatureStatus($project, $reportKey, $normalizedParameters, $user);
    }

    public function physicalSignedPdf(Project $project, string $reportKey, array $parameters): array
    {
        $normalizedParameters = $this->normalizeParameters($parameters);
        $parametersHash = $this->parametersHash($normalizedParameters);
        $logicalHash = $this->currentDocumentHash($project, $reportKey, $normalizedParameters);
        $latestSigned = $this->latestSignedForHash($project, $reportKey, $parametersHash, $logicalHash);
        $physicalSignatures = $this->physicalSignatures($project, $reportKey, $parametersHash, $logicalHash);

        if (! $latestSigned) {
            throw ValidationException::withMessages([
                'signature' => ['No existe un PDF firmado digitalmente para este reporte actual.'],
            ]);
        }

        if ($physicalSignatures->isEmpty()) {
            throw ValidationException::withMessages([
                'signature' => ['No hay firmas fisicas marcadas para este documento.'],
            ]);
        }

        return [
            'filename' => 'reporte_firmado_con_firmas_fisicas.pdf',
            'content' => $this->stampPhysicalSignatures($this->signedPdfContent($latestSigned), $physicalSignatures),
        ];
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

    private function latestSignedForHash(Project $project, string $reportKey, string $parametersHash, string $logicalHash): ?ProjectReportSignature
    {
        return ProjectReportSignature::query()
            ->where('id_proyecto', $project->id_proyecto)
            ->where('report_key', $reportKey)
            ->where('parameters_hash', $parametersHash)
            ->where('base_document_hash', $logicalHash)
            ->where('status', 'signed')
            ->whereNotNull('signed_file_path')
            ->latest('id')
            ->first();
    }

    private function physicalSignatures(Project $project, string $reportKey, string $parametersHash, string $logicalHash)
    {
        return ProjectReportPhysicalSignature::query()
            ->with('user')
            ->where('id_proyecto', $project->id_proyecto)
            ->where('report_key', $reportKey)
            ->where('parameters_hash', $parametersHash)
            ->where('logical_document_hash', $logicalHash)
            ->orderBy('marked_at')
            ->get();
    }

    private function serializePhysicalSignature(ProjectReportPhysicalSignature $signature): array
    {
        return [
            'id' => $signature->id,
            'user_id' => $signature->id_usuario,
            'user_name' => $signature->user?->funcionario ?: 'Usuario',
            'signature_image_url' => $signature->signature_image_path ? url(Storage::url($signature->signature_image_path)) : null,
            'page' => $signature->page,
            'x' => $signature->x,
            'y' => $signature->y,
            'width' => $signature->width,
            'height' => $signature->height,
            'marked_at' => $signature->marked_at?->toIso8601String(),
        ];
    }

    private function signedPdfPageSizes(ProjectReportSignature $signature): array
    {
        return $this->pdfPageSizes($this->signedPdfContent($signature));
    }

    private function pdfPageSizes(string $sourcePdf): array
    {
        $sourcePath = tempnam(sys_get_temp_dir(), 'sipre_pages_');

        if ($sourcePath === false) {
            return [];
        }

        file_put_contents($sourcePath, $sourcePdf);
        $compatiblePath = $this->fpdiCompatiblePdfPath($sourcePath);

        try {
            $pdf = new Fpdi();
            $pageCount = $pdf->setSourceFile($compatiblePath);
            $sizes = [];

            for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
                $templateId = $pdf->importPage($pageNumber);
                $size = $pdf->getTemplateSize($templateId);
                $sizes[] = [
                    'page' => $pageNumber,
                    'width' => round((float) $size['width'], 2),
                    'height' => round((float) $size['height'], 2),
                    'orientation' => (string) $size['orientation'],
                ];
            }

            return $sizes;
        } finally {
            @unlink($sourcePath);
            if ($compatiblePath !== $sourcePath) {
                @unlink($compatiblePath);
            }
        }
    }

    private function assignMissingPhysicalPlacements($signatures, array $pageSizes): void
    {
        if ($signatures->isEmpty() || $pageSizes === []) {
            return;
        }

        $lastPage = $pageSizes[array_key_last($pageSizes)];
        $existing = $signatures
            ->filter(fn (ProjectReportPhysicalSignature $signature): bool => filled($signature->page))
            ->map(fn (ProjectReportPhysicalSignature $signature): array => $this->placementFromSignature($signature))
            ->values()
            ->all();

        foreach ($signatures as $signature) {
            if (filled($signature->page)) {
                continue;
            }

            $placement = $this->nextDefaultPlacement($lastPage, $existing);
            $signature->forceFill($placement)->save();
            $existing[] = ['id' => $signature->id, ...$placement];
        }
    }

    private function nextDefaultPlacement(array $pageSize, array $existing): array
    {
        $width = self::DEFAULT_PHYSICAL_SIGNATURE_WIDTH;
        $height = self::DEFAULT_PHYSICAL_SIGNATURE_HEIGHT;
        $margin = 10.0;
        $gap = 4.0;
        $page = (int) $pageSize['page'];
        $pageWidth = (float) $pageSize['width'];
        $pageHeight = (float) $pageSize['height'];
        $columns = max(1, (int) floor(($pageWidth - ($margin * 2) + $gap) / ($width + $gap)));

        for ($row = 0; $row < 10; $row++) {
            for ($column = 0; $column < $columns; $column++) {
                $candidate = [
                    'page' => $page,
                    'x' => round($margin + ($column * ($width + $gap)), 2),
                    'y' => round($pageHeight - $margin - $height - ($row * ($height + $gap)), 2),
                    'width' => $width,
                    'height' => $height,
                ];

                if (! $this->placementsOverlap([['id' => 0, ...$candidate], ...$existing])) {
                    return $candidate;
                }
            }
        }

        return [
            'page' => $page,
            'x' => $margin,
            'y' => max($margin, $pageHeight - $margin - $height),
            'width' => $width,
            'height' => $height,
        ];
    }

    private function placementFromSignature(ProjectReportPhysicalSignature $signature): array
    {
        return [
            'id' => $signature->id,
            'page' => (int) $signature->page,
            'x' => (float) $signature->x,
            'y' => (float) $signature->y,
            'width' => (float) $signature->width,
            'height' => (float) $signature->height,
        ];
    }

    private function placementInsidePage(array $placement, array $pageSizes): bool
    {
        $page = collect($pageSizes)->firstWhere('page', (int) $placement['page']);

        if (! $page) {
            return false;
        }

        return $placement['width'] > 0
            && $placement['height'] > 0
            && $placement['x'] >= 0
            && $placement['y'] >= 0
            && ($placement['x'] + $placement['width']) <= ((float) $page['width'] + 0.01)
            && ($placement['y'] + $placement['height']) <= ((float) $page['height'] + 0.01);
    }

    private function placementsOverlap(array $placements): bool
    {
        for ($i = 0; $i < count($placements); $i++) {
            for ($j = $i + 1; $j < count($placements); $j++) {
                $a = $placements[$i];
                $b = $placements[$j];

                if ((int) $a['page'] !== (int) $b['page']) {
                    continue;
                }

                if (
                    $a['x'] < $b['x'] + $b['width']
                    && $a['x'] + $a['width'] > $b['x']
                    && $a['y'] < $b['y'] + $b['height']
                    && $a['y'] + $a['height'] > $b['y']
                ) {
                    return true;
                }
            }
        }

        return false;
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
            'base_document_hash' => $signature->base_document_hash,
            'has_signed_file' => filled($signature->signed_file_path),
            'redirect_url' => data_get($signature->response_payload, 'redirect_url')
                ?: data_get($signature->response_payload, 'data.url')
                ?: data_get($signature->response_payload, 'data.link')
                ?: data_get($signature->response_payload, 'url')
                ?: (is_string(data_get($signature->response_payload, 'data')) ? data_get($signature->response_payload, 'data') : null),
            'logout_redirect_url' => data_get($signature->response_payload, 'logout_redirect_url'),
            'logout_confirmed_at' => data_get($signature->response_payload, 'logout_confirmed_at'),
            'signed_document_url' => data_get($signature->response_payload, 'signed_document_url'),
            'validation' => data_get($signature->response_payload, 'validation'),
            'citizenship_user' => data_get($signature->response_payload, 'ciudadania_user.data'),
            'validation_records' => data_get($signature->response_payload, 'validation.data.registros', []),
            'error_message' => $signature->error_message,
        ];
    }

    public function citizenshipSession(User $user): array
    {
        $session = $this->currentCitizenshipSession($user);

        if (! $session) {
            return [
                'active' => false,
                'can_logout' => false,
                'logout_redirect_url' => null,
                'signature' => null,
            ];
        }

        $signature = $session['signature'];
        $logoutRedirectUrl = $this->storedLogoutRedirectUrl($signature);

        return [
            'active' => true,
            'can_logout' => true,
            'logout_redirect_url' => $logoutRedirectUrl,
            'signature' => $this->serialize($signature),
        ];
    }

    public function logoutCitizenshipSession(User $user): array
    {
        $session = $this->currentCitizenshipSession($user);

        if (! $session) {
            return [
                'active' => false,
                'can_logout' => false,
                'logout_redirect_url' => null,
                'signature' => null,
            ];
        }

        $signature = $session['signature'];
        $accessToken = $session['access_token'];
        $logoutRedirectUrl = $this->storedLogoutRedirectUrl($signature);

        if (! $logoutRedirectUrl) {
            $logoutResponse = $this->ciudadaniaDigitalClient->logout($accessToken, $this->logoutCallbackUrl($signature));
            $logoutRedirectUrl = is_array($logoutResponse) ? $this->redirectUrlFromResponse($logoutResponse, false) : null;

            if ($logoutRedirectUrl) {
                $signature->forceFill([
                    'response_payload' => array_merge($signature->response_payload ?? [], [
                        'logout' => $logoutResponse,
                        'logout_redirect_url' => $logoutRedirectUrl,
                    ]),
                ])->save();
                $signature->refresh();
            }
        }

        if ($logoutRedirectUrl) {
            Cache::forget($this->accessTokenCacheKey($signature));

            $signature->forceFill([
                'response_payload' => array_merge($signature->response_payload ?? [], [
                    'logout_requested_at' => now()->toIso8601String(),
                ]),
            ])->save();
            $signature->refresh();
        }

        return [
            'active' => $logoutRedirectUrl === null,
            'can_logout' => $logoutRedirectUrl !== null,
            'logout_redirect_url' => $logoutRedirectUrl,
            'signature' => $this->serialize($signature),
        ];
    }

    public function confirmCitizenshipLogout(?ProjectReportSignature $signature): ?ProjectReportSignature
    {
        if (! $signature) {
            return null;
        }

        Cache::forget($this->accessTokenCacheKey($signature));

        $signature->forceFill([
            'response_payload' => array_merge($signature->response_payload ?? [], [
                'logout_confirmed_at' => now()->toIso8601String(),
            ]),
        ])->save();

        return $signature->refresh();
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

    public function currentDocumentHash(Project $project, string $reportKey, array $parameters): string
    {
        $normalizedParameters = $this->normalizeParameters($parameters);
        $project = $project->fresh() ?? $project;
        $projectItems = DB::table('proyecto_item')
            ->where('id_proyecto', $project->id_proyecto)
            ->where('estado', 'AC')
            ->orderBy('id_proyecto_item')
            ->get();
        $projectItemIds = $projectItems->pluck('id_proyecto_item')->filter()->values()->all();

        return hash('sha256', json_encode([
            'report_key' => $reportKey,
            'parameters' => $normalizedParameters,
            'project' => $this->stableHashRow($project->getAttributes(), [
                'id_proyecto',
                'id_proyecto_raiz',
                'numero_version',
                'nombre_proyecto',
                'fecha',
                'aprobado',
                'estado',
            ]),
            'project_items' => $this->stableHashRows($projectItems, [
                'id_proyecto_item',
                'id_item',
                'id_modulo',
                'estado',
                'cantidad',
                'precio',
                'prioridad',
                'nombre_snapshot',
                'grupo_snapshot',
                'subgrupo_snapshot',
                'unidad_snapshot',
                'estado_catalogo_snapshot',
            ]),
            'input_snapshots' => $projectItemIds === []
                ? []
                : $this->stableHashRows(DB::table('proyecto_item_insumo_snapshot')
                    ->whereIn('id_proyecto_item', $projectItemIds)
                    ->orderBy('id_proyecto_item')
                    ->orderBy('id_snapshot')
                    ->get(), [
                        'id_proyecto_item',
                        'id_insumo',
                        'descripcion',
                        'tipo',
                        'unidad',
                        'cantidad',
                        'precio_unitario',
                        'parcial',
                        'estado',
                    ]),
            'percentage_snapshots' => $this->stableHashRows(DB::table('proyecto_porcentaje_snapshot')
                ->where('id_proyecto', $project->id_proyecto)
                ->orderBy('formato')
                ->orderBy('codigo')
                ->orderBy('id_snapshot')
                ->get(), [
                    'formato',
                    'codigo',
                    'descripcion',
                    'porcentaje',
                    'estado',
                ]),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function stableHashRows(iterable $rows, array $keys): array
    {
        return collect($rows)
            ->map(fn ($row): array => $this->stableHashRow((array) $row, $keys))
            ->values()
            ->all();
    }

    private function stableHashRow(array $row, array $keys): array
    {
        return Arr::only($row, $keys);
    }

    private function stampPhysicalSignatures(string $sourcePdf, $signatures): string
    {
        $sourcePath = tempnam(sys_get_temp_dir(), 'sipre_signed_');

        if ($sourcePath === false) {
            throw new RuntimeException('No se pudo preparar el PDF para agregar firmas fisicas.');
        }

        file_put_contents($sourcePath, $sourcePdf);

        $compatiblePath = $this->fpdiCompatiblePdfPath($sourcePath);

        try {
            $pdf = new Fpdi();
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetAutoPageBreak(false);
            $pdf->SetMargins(0, 0, 0);
            $pageCount = $pdf->setSourceFile($compatiblePath);
            $templateSizes = [];

            for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
                $templateId = $pdf->importPage($pageNumber);
                $templateSizes[$pageNumber] = $pdf->getTemplateSize($templateId);
            }

            $lastPage = [
                'page' => $pageCount,
                'width' => (float) $templateSizes[$pageCount]['width'],
                'height' => (float) $templateSizes[$pageCount]['height'],
            ];
            $placements = [];

            foreach ($signatures as $signature) {
                $placement = filled($signature->page)
                    ? $this->placementFromSignature($signature)
                    : ['id' => $signature->id, ...$this->nextDefaultPlacement($lastPage, $placements)];
                $placements[] = $placement;
            }

            for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
                $templateId = $pdf->importPage($pageNumber);
                $size = $templateSizes[$pageNumber];
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId, 0, 0, $size['width'], $size['height']);

                foreach ($signatures as $index => $signature) {
                    $placement = $placements[$index] ?? null;

                    if (! $placement || (int) $placement['page'] !== $pageNumber) {
                        continue;
                    }

                    $x = (float) $placement['x'];
                    $y = (float) $placement['y'];
                    $slotWidth = (float) $placement['width'];
                    $slotHeight = (float) $placement['height'];
                    $nameHeight = min(4.0, max(0.0, $slotHeight * 0.25));
                    $imageSlotHeight = max(1.0, $slotHeight - $nameHeight);
                    $imagePath = Storage::disk('public')->path($signature->signature_image_path);

                    if (is_readable($imagePath)) {
                        $dimensions = getimagesize($imagePath);
                        $maxImageWidth = $slotWidth;
                        $maxImageHeight = $imageSlotHeight;
                        $imageWidth = $maxImageWidth;
                        $imageHeight = $maxImageHeight;

                        if (is_array($dimensions) && ($dimensions[0] ?? 0) > 0 && ($dimensions[1] ?? 0) > 0) {
                            $ratio = (float) $dimensions[0] / (float) $dimensions[1];

                            if ($ratio >= ($maxImageWidth / $maxImageHeight)) {
                                $imageWidth = $maxImageWidth;
                                $imageHeight = $maxImageWidth / $ratio;
                            } else {
                                $imageHeight = $maxImageHeight;
                                $imageWidth = min($maxImageWidth, $maxImageHeight * $ratio);
                            }
                        }

                        $pdf->Image(
                            $imagePath,
                            $x + (($slotWidth - $imageWidth) / 2),
                            $y + (($slotHeight - $imageHeight) / 2),
                            $imageWidth,
                            $imageHeight
                        );
                    }

                    if ($nameHeight > 0) {
                        $pdf->SetFont('helvetica', '', 5);
                        $pdf->SetTextColor(25, 25, 25);
                        $pdf->SetXY($x, $y + $imageSlotHeight);
                        $pdf->MultiCell(
                            $slotWidth,
                            $nameHeight,
                            (string) ($signature->user?->funcionario ?: 'Usuario'),
                            0,
                            'C',
                            false,
                            1
                        );
                    }
                }
            }

            return $pdf->Output('', 'S');
        } finally {
            @unlink($sourcePath);
            if ($compatiblePath !== $sourcePath) {
                @unlink($compatiblePath);
            }
        }
    }

    private function fpdiCompatiblePdfPath(string $sourcePath): string
    {
        $ghostscript = $this->ghostscriptBinary();

        if ($ghostscript === null) {
            return $sourcePath;
        }

        $targetPath = tempnam(sys_get_temp_dir(), 'sipre_pdf14_');

        if ($targetPath === false) {
            return $sourcePath;
        }

        $command = sprintf(
            '%s -q -dSAFER -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/prepress -sOutputFile=%s %s 2>&1',
            escapeshellarg($ghostscript),
            escapeshellarg($targetPath),
            escapeshellarg($sourcePath)
        );

        $output = [];
        $exitCode = 1;
        exec($command, $output, $exitCode);

        if ($exitCode === 0 && is_readable($targetPath) && str_starts_with((string) file_get_contents($targetPath, false, null, 0, 4), '%PDF')) {
            return $targetPath;
        }

        Log::warning('No se pudo normalizar PDF firmado para FPDI.', [
            'exit_code' => $exitCode,
            'output' => implode("\n", array_slice($output, -5)),
        ]);

        @unlink($targetPath);

        return $sourcePath;
    }

    private function ghostscriptBinary(): ?string
    {
        foreach (['/usr/bin/gs', '/usr/local/bin/gs'] as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        return null;
    }

    public function documentHash(string $content): string
    {
        return hash('sha256', $content);
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

    private function deleteIncompleteAttempts(Project $project, string $reportKey, string $parametersHash, User $user): void
    {
        ProjectReportSignature::query()
            ->where('id_proyecto', $project->id_proyecto)
            ->where('report_key', $reportKey)
            ->where('parameters_hash', $parametersHash)
            ->where('id_usuario', $user->id_usuario)
            ->whereIn('status', ['pending', 'auth_pending', 'sent', 'error'])
            ->whereNull('signed_file_path')
            ->get()
            ->each(function (ProjectReportSignature $signature): void {
                foreach (array_filter([$signature->base_file_path]) as $path) {
                    Storage::disk('local')->delete($path);
                }

                Cache::forget($this->accessTokenCacheKey($signature));
                $signature->delete();
            });
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
            ->where(function ($query) use ($signature): void {
                $query->where('base_document_hash', $signature->base_document_hash);

                if ($this->isProjectFrozen($signature->project)) {
                    $query->orWhereNull('base_document_hash');
                }
            })
            ->where('id', '!=', $signature->id)
            ->latest('id')
            ->first();
    }

    private function isProjectFrozen(Project $project): bool
    {
        return $project->aprobado === 'RV'
            || filled($project->fecha_finalizacion)
            || (bool) ($project->is_frozen ?? false);
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
                'response_payload' => array_merge($response, [
                    'redirect_url' => $redirectUrl,
                    'auth_state' => $this->stateFromUrl($redirectUrl),
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

    private function requestApproval(ProjectReportSignature $signature, array $parameters): ProjectReportSignature
    {
        if ($signature->status === 'sent' && $this->storedRedirectUrl($signature)) {
            return $signature;
        }

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
            'signed_position' => 'BOTTOM',
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

    private function storedRedirectUrl(ProjectReportSignature $signature): ?string
    {
        $url = data_get($signature->response_payload, 'redirect_url')
            ?: data_get($signature->response_payload, 'data.url')
            ?: data_get($signature->response_payload, 'data.link');

        return is_string($url) && $url !== '' ? $url : null;
    }

    private function storedLogoutRedirectUrl(ProjectReportSignature $signature): ?string
    {
        $url = data_get($signature->response_payload, 'logout_redirect_url');

        return is_string($url) && $url !== '' ? $url : null;
    }

    private function currentCitizenshipSession(User $user): ?array
    {
        return ProjectReportSignature::query()
            ->with('user')
            ->where('id_usuario', $user->id_usuario)
            ->whereIn('status', ['auth_pending', 'sent', 'error'])
            ->latest('id')
            ->get()
            ->map(function (ProjectReportSignature $signature): ?array {
                if ($this->hasCitizenshipSessionCloseMarker($signature)) {
                    return null;
                }

                $cacheKey = $this->accessTokenCacheKey($signature);
                $accessToken = Cache::get($cacheKey);

                if (! is_string($accessToken) || $accessToken === '') {
                    Cache::forget($cacheKey);
                    $this->markCitizenshipSessionClosed($signature, 'logout_token_missing_at');

                    return null;
                }

                try {
                    $this->ciudadaniaDigitalClient->userInfo($accessToken);
                } catch (CiudadaniaDigitalException) {
                    Cache::forget($cacheKey);
                    $this->markCitizenshipSessionClosed($signature, 'citizenship_session_closed_at');

                    return null;
                }

                return [
                    'signature' => $signature,
                    'access_token' => $accessToken,
                ];
            })
            ->first(fn (?array $session): bool => $session !== null);
    }

    private function hasCitizenshipSessionCloseMarker(ProjectReportSignature $signature): bool
    {
        foreach (['logout_requested_at', 'logout_token_missing_at', 'logout_confirmed_at', 'citizenship_session_closed_at'] as $key) {
            if (filled(data_get($signature->response_payload, $key))) {
                return true;
            }
        }

        return false;
    }

    private function markCitizenshipSessionClosed(ProjectReportSignature $signature, string $key): void
    {
        $signature->forceFill([
            'response_payload' => array_merge($signature->response_payload ?? [], [
                $key => now()->toIso8601String(),
            ]),
        ])->save();
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

    private function stateFromUrl(?string $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $state = $query['state'] ?? null;

        return is_string($state) && $state !== '' ? $state : null;
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
        $appUrl = rtrim((string) config('app.url'), '/');
        $fallbackUrl = $appUrl.$path;

        if ($configuredUrl === '') {
            return $fallbackUrl;
        }

        if ($this->urlOrigin($configuredUrl) === $this->urlOrigin($appUrl)) {
            return $configuredUrl;
        }

        Log::warning('Callback de FirmaGAMC descartado por pertenecer a otro backend.', [
            'signature_id' => $signature->id,
            'trace_id' => $signature->trace_id,
            'config_key' => $configKey,
            'configured_url' => $configuredUrl,
            'app_url' => $appUrl,
            'fallback_url' => $fallbackUrl,
        ]);

        return $fallbackUrl;
    }

    private function urlOrigin(string $url): string
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return $scheme !== '' && $host !== '' ? $scheme.'://'.$host.$port : '';
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
        if ($signature->code) {
            return $signature->code;
        }

        $code = $this->newSignatureCode($signature->project);
        $signature->forceFill(['code' => $code])->save();

        return $code;
    }

    private function newSignatureCode(Project $project): string
    {
        return sprintf('sipre-%d-%s', $project->id_proyecto, Str::uuid());
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

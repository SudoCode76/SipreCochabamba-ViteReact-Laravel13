<?php

namespace App\Modules\Projects\Services;

use App\Models\Item;
use App\Models\Project;
use App\Models\ProjectReportPhysicalSignature;
use App\Models\ProjectReportSignature;
use App\Models\ProjectSignableReport;
use App\Models\User;
use App\Modules\Items\Services\ItemReportPdfResolver;
use App\Services\Citizenship\CiudadaniaDigitalClient;
use App\Services\Citizenship\CiudadaniaDigitalException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
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

    private const SIGNATURE_FOOTER_HEIGHT = 30.0;

    private const PHYSICAL_ZONE_MARGIN = 4.0;

    private const PHYSICAL_SIGNATURE_GAP = 4.0;

    private const MIN_PHYSICAL_SIGNATURE_WIDTH = 20.0;

    private const MIN_PHYSICAL_SIGNATURE_HEIGHT = 12.0;

    public function __construct(
        private readonly ProjectSignableReportService $signableReportService,
        private readonly ProjectSignatureAccessService $signatureAccessService,
        private readonly ProjectReportPdfResolver $pdfResolver,
        private readonly ItemReportPdfResolver $itemPdfResolver,
        private readonly CiudadaniaDigitalClient $ciudadaniaDigitalClient,
    ) {}

    public function start(Project|Item $project, string $reportKey, array $parameters, User $user): ProjectReportSignature
    {
        $this->assertCanStart($project, $reportKey, $user);

        $requestedPageScope = ($parameters['page_scope'] ?? null) === 'all' || $this->shouldSignAllPages($parameters) ? 'all' : 'last';
        $requestedLayoutHash = (string) ($parameters['layout_hash'] ?? '');
        $normalizedParameters = $this->normalizeParameters($parameters);
        $hash = $this->parametersHash($normalizedParameters);

        if ($project instanceof Project && $this->forSubject(ProjectReportSignature::query(), $project)
            ->where('report_key', $reportKey)
            ->where('parameters_hash', $hash)
            ->whereIn('status', ['pending', 'auth_pending', 'sent'])
            ->exists()) {
            throw ValidationException::withMessages([
                'signature' => ['Ya existe una firma digital en proceso para este PDF.'],
            ]);
        }

        $this->deleteIncompleteAttempts($project, $reportKey, $hash, $user);

        $pdf = $project instanceof Item
            ? $this->itemPdfResolver->resolve($project, $reportKey, $normalizedParameters)
            : $this->pdfResolver->resolve($project, $reportKey, $normalizedParameters);
        $baseDocumentHash = $this->currentDocumentHash($project, $reportKey, $normalizedParameters);
        $previous = $this->latestSigned($project, $reportKey, $hash);
        $canDeriveFromPrevious = $previous
            && (
                ($previous->base_document_hash && hash_equals((string) $previous->base_document_hash, $baseDocumentHash))
                || (! $previous->base_document_hash && $project instanceof Project && $this->isProjectFrozen($project))
            );

        if ($project instanceof Project && $previous && ! $canDeriveFromPrevious) {
            throw ValidationException::withMessages([
                'signature' => ['El reporte cambió después de su firma digital. Cree una nueva versión para volver a firmarlo.'],
            ]);
        }

        $pageScope = $previous
            ? (string) (data_get($previous->request_payload, 'page_scope') ?: $requestedPageScope)
            : $requestedPageScope;
        $layoutHash = $previous
            ? (string) (data_get($previous->request_payload, 'layout_hash') ?: $requestedLayoutHash)
            : $requestedLayoutHash;

        if ($canDeriveFromPrevious && $previous?->signed_file_path && Storage::disk('local')->exists($previous->signed_file_path)) {
            $baseContent = Storage::disk('local')->get($previous->signed_file_path);
            $basePath = $previous->signed_file_path;
        } elseif ($project instanceof Project) {
            $items = $this->physicalSignatures($project, $reportKey, $hash, $baseDocumentHash);
            $currentLayoutHash = $items->isEmpty()
                ? ''
                : $this->projectLayoutHash($project, $reportKey, $normalizedParameters, $pageScope, $items);
            $missing = $this->projectSignatureBlockers(
                $this->signatureAccessService->signers($project),
                $project->signature_signers_locked_at !== null
            );

            if ($missing !== []) {
                throw ValidationException::withMessages([
                    'signature_images' => collect($missing)
                        ->map(fn (array $missingUser): string => $missingUser['full_name'].': '.$missingUser['reason'])
                        ->all(),
                ]);
            }

            if ($layoutHash === '' || $currentLayoutHash === '' || ! hash_equals($currentLayoutHash, $layoutHash)) {
                throw ValidationException::withMessages([
                    'layout_hash' => ['La previsualización cambió o no fue preparada. Vuelva a revisarla antes de firmar.'],
                ]);
            }

            $reserved = $this->reserveProjectSignatureAreas($pdf['content'], $items->count(), $pageScope);
            $baseContent = $this->stampPhysicalSignatures($reserved['content'], $items, $pageScope);
            $basePath = $this->storeBasePdf($project, $reportKey, $baseContent);
        } else {
            $baseContent = $this->reserveSignatureFooter($pdf['content']);
            $basePath = $this->storeBasePdf($project, $reportKey, $baseContent);
        }

        $signature = ProjectReportSignature::query()->create([
            ...$this->subjectColumns($project),
            'trace_id' => (string) Str::uuid(),
            'report_key' => $reportKey,
            'parameters' => $normalizedParameters,
            'parameters_hash' => $hash,
            'status' => 'pending',
            'code' => $this->newSignatureCode($project),
            'id_usuario' => $user->id_usuario,
            'base_file_path' => $basePath,
            'base_document_hash' => $baseDocumentHash,
            'request_payload' => [
                'sign_all_pages' => $pageScope === 'all',
                'page_scope' => $pageScope,
                'layout_hash' => $layoutHash ?: null,
                'derived_from_signature_id' => $canDeriveFromPrevious ? $previous?->id : null,
            ],
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

        if ($signature->status === 'cancelled') {
            throw new RuntimeException('La solicitud de firma fue cancelada. Inicie una nueva previsualización.');
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

            $subject = $this->signatureSubject($signature);
            if ($subject instanceof Project && ! data_get($signature->request_payload, 'derived_from_signature_id')) {
                $physical = $this->physicalSignatures(
                    $subject,
                    $signature->report_key,
                    $signature->parameters_hash,
                    (string) $signature->base_document_hash
                );
                $this->signatureAccessService->lock($subject, $physical);
            }

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

    public function history(Project|Item $project, string $reportKey, ?string $parametersHash = null): array
    {
        return $this->forSubject(ProjectReportSignature::query(), $project)
            ->with('user')
            ->where('report_key', $reportKey)
            ->when($parametersHash, fn ($query) => $query->where('parameters_hash', $parametersHash))
            ->where('status', 'signed')
            ->latest('id')
            ->get()
            ->map(fn (ProjectReportSignature $signature): array => $this->serialize($signature))
            ->all();
    }

    public function latestSignedSigners(Project|Item $project, string $reportKey, ?string $parametersHash = null): array
    {
        $signature = $this->latestSigned($project, $reportKey, $parametersHash);

        return $signature
            ? $this->validationRecords(data_get($signature->response_payload, 'validation', []))
            : [];
    }

    public function prepareProjectPreview(Project $project, string $reportKey, array $parameters, string $pageScope, User $user): array
    {
        $this->assertCanStart($project, $reportKey, $user);
        $this->assertProjectPreviewEditable($project, $reportKey, $parameters);

        $pageScope = $pageScope === 'all' ? 'all' : 'last';
        $normalized = $this->normalizeParameters($parameters);
        $parametersHash = $this->parametersHash($normalized);
        $logicalHash = $this->currentDocumentHash($project, $reportKey, $normalized);
        $signers = $this->signatureAccessService->signers($project);
        $missing = $this->projectSignatureBlockers($signers, $project->signature_signers_locked_at !== null);

        if ($missing !== []) {
            return $this->projectPreviewPayload($project, $reportKey, $normalized, $pageScope, $user, collect(), [], $missing);
        }

        $items = $this->syncProjectPhysicalSignatures(
            $project,
            $reportKey,
            $parametersHash,
            $logicalHash,
            $pageScope,
            $signers
        );
        $geometry = $this->projectPreviewGeometry($project, $reportKey, $normalized, $pageScope, $items->count());
        $this->assignProjectPreviewPlacements($items, $geometry);
        $items = $this->physicalSignatures($project, $reportKey, $parametersHash, $logicalHash);

        return $this->projectPreviewPayload($project, $reportKey, $normalized, $pageScope, $user, $items, $geometry, []);
    }

    public function projectPreviewStatus(Project $project, string $reportKey, array $parameters, User $user): array
    {
        $normalized = $this->normalizeParameters($parameters);
        $parametersHash = $this->parametersHash($normalized);
        $logicalHash = $this->currentDocumentHash($project, $reportKey, $normalized);
        $items = $this->physicalSignatures($project, $reportKey, $parametersHash, $logicalHash);
        $pageScope = (string) ($items->first()?->page_scope ?: ($parameters['page_scope'] ?? 'last'));
        $signers = $this->signatureAccessService->signers($project);
        $missing = $this->projectSignatureBlockers($signers, $project->signature_signers_locked_at !== null);
        $geometry = $items->isEmpty()
            ? []
            : $this->projectPreviewGeometry($project, $reportKey, $normalized, $pageScope, $items->count());

        return $this->projectPreviewPayload($project, $reportKey, $normalized, $pageScope, $user, $items, $geometry, $missing);
    }

    public function projectPreviewPdf(Project $project, string $reportKey, array $parameters, User $user): array
    {
        $status = $this->projectPreviewStatus($project, $reportKey, $parameters, $user);

        if (! ($status['ready'] ?? false)) {
            throw ValidationException::withMessages([
                'signature' => ['La previsualización no está lista para firmarse.'],
            ]);
        }

        $pdf = $this->pdfResolver->resolve($project, $reportKey, $this->normalizeParameters($parameters));
        $reserved = $this->reserveProjectSignatureAreas(
            $pdf['content'],
            count($status['items']),
            $status['page_scope']
        );

        return ['filename' => 'previsualizacion_firmas.pdf', 'content' => $reserved['content']];
    }

    public function cancel(Project $project, ProjectReportSignature $signature, User $user): ProjectReportSignature
    {
        if ((int) $signature->id_proyecto !== (int) $project->id_proyecto) {
            throw new AuthorizationException('La solicitud no pertenece a este proyecto.');
        }

        if ($signature->id_usuario !== $user->id_usuario && ! $this->signatureAccessService->canManage($project, $user)) {
            throw new AuthorizationException('No puede cancelar esta solicitud de firma.');
        }

        if (! in_array($signature->status, ['pending', 'auth_pending', 'sent'], true)) {
            throw ValidationException::withMessages(['signature' => ['La solicitud ya no puede cancelarse.']]);
        }

        Cache::forget($this->accessTokenCacheKey($signature));
        $signature->forceFill([
            'status' => 'cancelled',
            'error_message' => 'Solicitud cancelada por el usuario.',
            'response_payload' => array_merge($signature->response_payload ?? [], [
                'cancelled_at' => now()->toIso8601String(),
                'cancelled_by' => $user->id_usuario,
            ]),
        ])->save();

        return $signature->refresh();
    }

    public function physicalSignatureStatus(Project|Item $project, string $reportKey, array $parameters, User $user): array
    {
        if ($project instanceof Project) {
            return $this->projectPreviewStatus($project, $reportKey, $parameters, $user);
        }

        $normalizedParameters = $this->normalizeParameters($parameters);
        $parametersHash = $this->parametersHash($normalizedParameters);
        $logicalHash = $this->currentDocumentHash($project, $reportKey, $normalizedParameters);
        $latestSigned = $this->latestSignedForHash($project, $reportKey, $parametersHash, $logicalHash);
        $items = $this->physicalSignatures($project, $reportKey, $parametersHash, $logicalHash);
        $pageSizes = $latestSigned ? $this->signedPdfPageSizes($latestSigned) : [];
        $signatureAccess = $project instanceof Project
            ? $this->signatureAccessService->decision($project, $user)
            : ['mode' => null, 'allowed' => true, 'message' => null];
        $report = $this->signableReportService->findEnabled($reportKey);
        $projectStatusAllowsSigning = ! $project instanceof Project
            || ($report && $this->signableReportService->projectStatusAllowsSigning($report, $project->isFrozen()));
        $canAdjust = (bool) $latestSigned
            && $report
            && $projectStatusAllowsSigning
            && $this->signableReportService->canSignPhysically($user, $reportKey)
            && $signatureAccess['allowed'];
        $this->assignMissingPhysicalPlacements($items, $pageSizes);
        $items = $items->fresh(['user']);

        return [
            'count' => $items->count(),
            'already_marked' => $items->contains('id_usuario', $user->id_usuario),
            'can_mark' => $canAdjust && filled($user->firma_imagen_path),
            'can_adjust' => $canAdjust,
            'needs_signature_image' => blank($user->firma_imagen_path),
            'signature_access' => $signatureAccess,
            'latest_signed' => $latestSigned ? $this->serialize($latestSigned) : null,
            'page_sizes' => $pageSizes,
            'items' => $items->map(fn (ProjectReportPhysicalSignature $signature): array => $this->serializePhysicalSignature($signature))->all(),
        ];
    }

    public function markPhysicalSignature(Project|Item $project, string $reportKey, array $parameters, User $user): array
    {
        if ($project instanceof Project) {
            throw ValidationException::withMessages([
                'signature' => ['Las firmas físicas del proyecto se preparan automáticamente antes de Ciudadanía Digital.'],
            ]);
        }

        $this->assertCanUsePhysicalSignatures($project, $reportKey, $user);

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

        $existing = $this->forSubject(ProjectReportPhysicalSignature::query(), $project)
            ->where('report_key', $reportKey)
            ->where('parameters_hash', $parametersHash)
            ->where('logical_document_hash', $logicalHash)
            ->where('id_usuario', $user->id_usuario)
            ->first();

        $extension = pathinfo((string) $user->firma_imagen_path, PATHINFO_EXTENSION) ?: 'png';
        $snapshotPath = sprintf(
            '%s-physical-signatures/%d/%s/%s/%d-%s.%s',
            $project instanceof Item ? 'item' : 'project',
            $this->subjectId($project),
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
                ...$this->subjectColumns($project),
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

    public function updatePhysicalSignaturePositions(Project|Item $project, string $reportKey, array $parameters, array $positions, User $user): array
    {
        if ($project instanceof Project) {
            return $this->updateProjectPreviewPositions($project, $reportKey, $parameters, $positions, $user);
        }

        $this->assertCanUsePhysicalSignatures($project, $reportKey, $user);

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

            if (! $this->placementInsideEveryPage($placement, $pageSizes)) {
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

    private function updateProjectPreviewPositions(Project $project, string $reportKey, array $parameters, array $positions, User $user): array
    {
        $this->assertCanStart($project, $reportKey, $user);
        $this->assertProjectPreviewEditable($project, $reportKey, $parameters);

        $normalized = $this->normalizeParameters($parameters);
        $parametersHash = $this->parametersHash($normalized);
        $logicalHash = $this->currentDocumentHash($project, $reportKey, $normalized);
        $items = $this->physicalSignatures($project, $reportKey, $parametersHash, $logicalHash);

        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['positions' => ['Primero prepare la previsualización de firmas.']]);
        }

        $pageScope = (string) ($items->first()->page_scope ?: 'last');
        $geometry = $this->projectPreviewGeometry($project, $reportKey, $normalized, $pageScope, $items->count());
        $currentHash = $this->projectLayoutHash($project, $reportKey, $normalized, $pageScope, $items);

        if (filled($parameters['layout_hash'] ?? null) && ! hash_equals($currentHash, (string) $parameters['layout_hash'])) {
            throw ValidationException::withMessages([
                'layout_hash' => ['La previsualización cambió. Vuelva a abrirla antes de guardar.'],
            ]);
        }

        $byId = $items->keyBy('id');
        $normalizedPositions = [];

        foreach ($positions as $position) {
            $id = (int) ($position['id'] ?? 0);

            if (! $byId->has($id) || collect($normalizedPositions)->contains('id', $id)) {
                throw ValidationException::withMessages(['positions' => ['Una firma no pertenece a esta previsualización o está repetida.']]);
            }

            $placement = [
                'id' => $id,
                'page' => (int) $geometry['signature_page'],
                'x' => round((float) ($position['x'] ?? 0), 2),
                'y' => round((float) ($position['y'] ?? 0), 2),
                'width' => round((float) ($position['width'] ?? 0), 2),
                'height' => round((float) ($position['height'] ?? 0), 2),
            ];

            if ($placement['width'] < self::MIN_PHYSICAL_SIGNATURE_WIDTH
                || $placement['height'] < self::MIN_PHYSICAL_SIGNATURE_HEIGHT
                || ! $this->placementInsidePhysicalZone($placement, $geometry['physical_zone'])) {
                throw ValidationException::withMessages([
                    'positions' => ['Las firmas deben permanecer dentro de la zona física reservada y respetar el tamaño mínimo.'],
                ]);
            }

            $normalizedPositions[] = $placement;
        }

        $submitted = collect($normalizedPositions)->keyBy('id');
        $all = $items->map(fn (ProjectReportPhysicalSignature $signature): array => (
            $submitted->get($signature->id) ?: $this->placementFromSignature($signature)
        ))->values()->all();

        if ($this->placementsOverlap($all)) {
            throw ValidationException::withMessages(['positions' => ['Las firmas físicas no pueden superponerse.']]);
        }

        DB::transaction(function () use ($byId, $normalizedPositions): void {
            foreach ($normalizedPositions as $placement) {
                $byId[$placement['id']]->forceFill(Arr::except($placement, ['id']))->save();
            }
        });

        return $this->projectPreviewStatus($project, $reportKey, $normalized, $user);
    }

    private function assertProjectPreviewEditable(Project $project, string $reportKey, array $parameters): void
    {
        $normalized = $this->normalizeParameters($parameters);
        $hash = $this->parametersHash($normalized);
        $logicalHash = $this->currentDocumentHash($project, $reportKey, $normalized);
        $previousSigned = $this->latestSigned($project, $reportKey, $hash);

        if ($previousSigned) {
            throw ValidationException::withMessages([
                'signature' => [filled($previousSigned->base_document_hash)
                    && ! hash_equals((string) $previousSigned->base_document_hash, $logicalHash)
                        ? 'El reporte cambió después de su firma digital. Cree una nueva versión para volver a firmarlo.'
                        : 'Este PDF ya tiene una firma digital y sus firmas físicas no pueden modificarse. Cree una nueva versión.'],
            ]);
        }

        if ($this->forSubject(ProjectReportSignature::query(), $project)
            ->where('report_key', $reportKey)
            ->where('parameters_hash', $hash)
            ->whereIn('status', ['pending', 'auth_pending', 'sent'])
            ->exists()) {
            throw ValidationException::withMessages([
                'signature' => ['Existe una firma digital en proceso. Cancélela antes de modificar la previsualización.'],
            ]);
        }
    }

    private function projectSignatureBlockers(Collection $signers, bool $locked): array
    {
        return $signers->map(function (User $signer) use ($locked): ?array {
            $path = $locked ? $signer->signature_image_path_snapshot : $signer->firma_imagen_path;
            $reason = match (true) {
                $signer->estado !== 'AC' => 'Usuario inactivo',
                blank($path) || ! Storage::disk('public')->exists($path) => 'No tiene una imagen de firma cargada',
                default => null,
            };

            return $reason ? [
                'id' => $signer->id_usuario,
                'full_name' => $signer->funcionario,
                'reason' => $reason,
            ] : null;
        })->filter()->values()->all();
    }

    private function syncProjectPhysicalSignatures(
        Project $project,
        string $reportKey,
        string $parametersHash,
        string $logicalHash,
        string $pageScope,
        Collection $signers
    ): Collection {
        $query = $this->forSubject(ProjectReportPhysicalSignature::query(), $project)
            ->where('report_key', $reportKey)
            ->where('parameters_hash', $parametersHash)
            ->where('logical_document_hash', $logicalHash);
        $existing = (clone $query)->get()->keyBy('id_usuario');
        $selectedIds = $signers->pluck('id_usuario')->map(fn ($id): int => (int) $id);

        foreach ($existing->reject(fn ($signature, $userId): bool => $selectedIds->contains((int) $userId)) as $obsolete) {
            Storage::disk('public')->delete($obsolete->signature_image_path);
            $obsolete->delete();
        }

        foreach ($signers as $signer) {
            $sourcePath = $project->signature_signers_locked_at
                ? $signer->signature_image_path_snapshot
                : $signer->firma_imagen_path;
            $sourceHash = hash('sha256', Storage::disk('public')->get($sourcePath));
            $signature = $existing->get($signer->id_usuario);
            $currentHash = $signature && Storage::disk('public')->exists($signature->signature_image_path)
                ? hash('sha256', Storage::disk('public')->get($signature->signature_image_path))
                : null;
            $scopeChanged = $signature && $signature->page_scope !== $pageScope;

            if (! $signature || $currentHash !== $sourceHash) {
                $extension = pathinfo((string) $sourcePath, PATHINFO_EXTENSION) ?: 'png';
                $snapshotPath = sprintf(
                    'project-physical-previews/%d/%s/%s/%d-%s.%s',
                    $project->id_proyecto,
                    $reportKey,
                    $parametersHash,
                    $signer->id_usuario,
                    Str::uuid(),
                    $extension
                );
                Storage::disk('public')->put($snapshotPath, Storage::disk('public')->get($sourcePath));

                if ($signature) {
                    Storage::disk('public')->delete($signature->signature_image_path);
                    $signature->forceFill(['signature_image_path' => $snapshotPath])->save();
                } else {
                    $signature = ProjectReportPhysicalSignature::query()->create([
                        ...$this->subjectColumns($project),
                        'report_key' => $reportKey,
                        'parameters_hash' => $parametersHash,
                        'logical_document_hash' => $logicalHash,
                        'id_usuario' => $signer->id_usuario,
                        'signature_image_path' => $snapshotPath,
                        'page_scope' => $pageScope,
                        'marked_at' => now(),
                    ]);
                }
            }

            if ($scopeChanged || $signature->page_scope !== $pageScope) {
                $signature->forceFill([
                    'page_scope' => $pageScope,
                    'page' => null,
                    'x' => null,
                    'y' => null,
                    'width' => null,
                    'height' => null,
                ])->save();
            }
        }

        return (clone $query)->with('user')->orderBy('marked_at')->get();
    }

    private function projectPreviewPayload(
        Project $project,
        string $reportKey,
        array $parameters,
        string $pageScope,
        User $user,
        Collection $items,
        array $geometry,
        array $missing
    ): array {
        $parametersHash = $this->parametersHash($parameters);
        $logicalHash = $this->currentDocumentHash($project, $reportKey, $parameters);
        $latestSigned = $this->latestSignedForHash($project, $reportKey, $parametersHash, $logicalHash);
        $pending = $this->forSubject(ProjectReportSignature::query(), $project)
            ->where('report_key', $reportKey)
            ->where('parameters_hash', $parametersHash)
            ->whereIn('status', ['pending', 'auth_pending', 'sent'])
            ->latest('id')
            ->first();
        $access = $this->signatureAccessService->decision($project, $user);
        $ready = $missing === [] && $items->isNotEmpty() && filled($project->signature_signers_configured_at);
        $layoutHash = $ready ? $this->projectLayoutHash($project, $reportKey, $parameters, $pageScope, $items) : null;

        return [
            'ready' => $ready,
            'can_send' => $ready && ! $latestSigned && ! $pending && $access['allowed']
                && $this->signableReportService->canSign($user, $reportKey),
            'can_adjust' => $ready && ! $latestSigned && ! $pending && $access['allowed']
                && $this->signableReportService->canSign($user, $reportKey),
            'page_scope' => $pageScope,
            'layout_hash' => $layoutHash,
            'missing_users' => $missing,
            'signature_access' => $access,
            'signers_locked' => filled($project->signature_signers_locked_at),
            'latest_signed' => $latestSigned ? $this->serialize($latestSigned) : null,
            'pending_signature' => $pending ? $this->serialize($pending) : null,
            'page_sizes' => $geometry['page_sizes'] ?? [],
            'physical_zone' => $geometry['physical_zone'] ?? null,
            'digital_zone' => $geometry['digital_zone'] ?? null,
            'signature_page' => $geometry['signature_page'] ?? null,
            'items' => $items->map(fn (ProjectReportPhysicalSignature $signature): array => $this->serializePhysicalSignature($signature))->all(),
        ];
    }

    private function projectLayoutHash(Project $project, string $reportKey, array $parameters, string $pageScope, Collection $items): string
    {
        return hash('sha256', json_encode([
            'project_id' => $project->id_proyecto,
            'report_key' => $reportKey,
            'parameters' => $this->normalizeParameters($parameters),
            'document_hash' => $this->currentDocumentHash($project, $reportKey, $parameters),
            'page_scope' => $pageScope,
            'signatures' => $items->sortBy('id_usuario')->map(function (ProjectReportPhysicalSignature $signature): array {
                return [
                    'user_id' => $signature->id_usuario,
                    'image_hash' => Storage::disk('public')->exists($signature->signature_image_path)
                        ? hash('sha256', Storage::disk('public')->get($signature->signature_image_path))
                        : null,
                    'page' => $signature->page,
                    'x' => $signature->x,
                    'y' => $signature->y,
                    'width' => $signature->width,
                    'height' => $signature->height,
                ];
            })->values()->all(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function projectPreviewGeometry(Project $project, string $reportKey, array $parameters, string $pageScope, int $count): array
    {
        $pdf = $this->pdfResolver->resolve($project, $reportKey, $parameters);

        return Arr::except($this->reserveProjectSignatureAreas($pdf['content'], $count, $pageScope), ['content']);
    }

    private function reserveProjectSignatureAreas(string $sourcePdf, int $count, string $pageScope): array
    {
        $sourcePath = tempnam(sys_get_temp_dir(), 'sipre_preview_');

        if ($sourcePath === false) {
            throw new RuntimeException('No se pudo preparar la previsualización de firmas.');
        }

        file_put_contents($sourcePath, $sourcePdf);
        $compatiblePath = $this->fpdiCompatiblePdfPath($sourcePath);

        try {
            $pdf = new Fpdi;
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetAutoPageBreak(false);
            $pdf->SetMargins(0, 0, 0);
            $pageCount = $pdf->setSourceFile($compatiblePath);
            $templates = [];
            $pageSizes = [];

            for ($page = 1; $page <= $pageCount; $page++) {
                $template = $pdf->importPage($page);
                $size = $pdf->getTemplateSize($template);
                $templates[$page] = ['id' => $template, ...$size];
                $pageSizes[] = [
                    'page' => $page,
                    'width' => round((float) $size['width'], 2),
                    'height' => round((float) $size['height'], 2),
                    'orientation' => (string) $size['orientation'],
                ];
            }

            $targetPages = $pageScope === 'all' ? range(1, $pageCount) : [$pageCount];
            $targetSizes = collect($pageSizes)->whereIn('page', $targetPages);
            $width = (float) $targetSizes->min('width');
            $height = (float) $targetSizes->min('height');
            $layout = $this->physicalZoneLayout($width, $height, $count);
            $totalReserved = self::SIGNATURE_FOOTER_HEIGHT + $layout['height'];

            foreach ($templates as $page => $template) {
                $pageWidth = (float) $template['width'];
                $pageHeight = (float) $template['height'];
                $pdf->AddPage($template['orientation'], [$pageWidth, $pageHeight]);

                if (in_array($page, $targetPages, true)) {
                    $scale = ($pageHeight - $totalReserved) / $pageHeight;
                    $scaledWidth = $pageWidth * $scale;
                    $scaledHeight = $pageHeight * $scale;
                    $pdf->useTemplate($template['id'], ($pageWidth - $scaledWidth) / 2, 0, $scaledWidth, $scaledHeight);
                } else {
                    $pdf->useTemplate($template['id'], 0, 0, $pageWidth, $pageHeight);
                }
            }

            return [
                'content' => $pdf->Output('', 'S'),
                'page_sizes' => $pageSizes,
                'signature_page' => $pageScope === 'all' ? 1 : $pageCount,
                'physical_zone' => [
                    'x' => self::PHYSICAL_ZONE_MARGIN,
                    'y' => round($height - $totalReserved + self::PHYSICAL_ZONE_MARGIN, 2),
                    'width' => round($width - (self::PHYSICAL_ZONE_MARGIN * 2), 2),
                    'height' => round($layout['height'] - (self::PHYSICAL_ZONE_MARGIN * 2), 2),
                    'default_width' => $layout['signature_width'],
                    'default_height' => $layout['signature_height'],
                    'columns' => $layout['columns'],
                ],
                'digital_zone' => [
                    'x' => 0,
                    'y' => round($height - self::SIGNATURE_FOOTER_HEIGHT, 2),
                    'width' => $width,
                    'height' => self::SIGNATURE_FOOTER_HEIGHT,
                ],
            ];
        } finally {
            @unlink($sourcePath);
            if ($compatiblePath !== $sourcePath) {
                @unlink($compatiblePath);
            }
        }
    }

    private function physicalZoneLayout(float $pageWidth, float $pageHeight, int $count): array
    {
        foreach ([[self::DEFAULT_PHYSICAL_SIGNATURE_WIDTH, self::DEFAULT_PHYSICAL_SIGNATURE_HEIGHT], [self::MIN_PHYSICAL_SIGNATURE_WIDTH, self::MIN_PHYSICAL_SIGNATURE_HEIGHT]] as [$width, $height]) {
            $usableWidth = $pageWidth - (self::PHYSICAL_ZONE_MARGIN * 2);
            $columns = max(1, (int) floor(($usableWidth + self::PHYSICAL_SIGNATURE_GAP) / ($width + self::PHYSICAL_SIGNATURE_GAP)));
            $rows = max(1, (int) ceil(max(1, $count) / $columns));
            $zoneHeight = (self::PHYSICAL_ZONE_MARGIN * 2) + ($rows * $height) + (($rows - 1) * self::PHYSICAL_SIGNATURE_GAP);

            if ($zoneHeight + self::SIGNATURE_FOOTER_HEIGHT <= $pageHeight * 0.55) {
                return [
                    'height' => $zoneHeight,
                    'signature_width' => $width,
                    'signature_height' => $height,
                    'columns' => $columns,
                ];
            }
        }

        throw ValidationException::withMessages([
            'user_ids' => ['La cantidad de firmantes no cabe en el área reservada del PDF.'],
        ]);
    }

    private function assignProjectPreviewPlacements(Collection $items, array $geometry): void
    {
        $zone = $geometry['physical_zone'];
        $existing = [];

        foreach ($items as $index => $signature) {
            $placement = $this->hasPhysicalPlacement($signature)
                ? $this->placementFromSignature($signature)
                : null;

            if ($placement && $this->placementInsidePhysicalZone($placement, $zone)
                && ! $this->placementsOverlap([...$existing, $placement])) {
                $existing[] = $placement;
                continue;
            }

            $column = $index % $zone['columns'];
            $row = intdiv($index, $zone['columns']);
            $placement = [
                'page' => $geometry['signature_page'],
                'x' => round($zone['x'] + ($column * ($zone['default_width'] + self::PHYSICAL_SIGNATURE_GAP)), 2),
                'y' => round($zone['y'] + ($row * ($zone['default_height'] + self::PHYSICAL_SIGNATURE_GAP)), 2),
                'width' => $zone['default_width'],
                'height' => $zone['default_height'],
            ];
            $signature->forceFill($placement)->save();
            $existing[] = ['id' => $signature->id, ...$placement];
        }
    }

    private function placementInsidePhysicalZone(array $placement, array $zone): bool
    {
        return $placement['x'] >= $zone['x']
            && $placement['y'] >= $zone['y']
            && ($placement['x'] + $placement['width']) <= ($zone['x'] + $zone['width'] + 0.01)
            && ($placement['y'] + $placement['height']) <= ($zone['y'] + $zone['height'] + 0.01);
    }

    private function assertCanUsePhysicalSignatures(Project|Item $project, string $reportKey, User $user): void
    {
        $report = $this->signableReportService->findEnabled($reportKey);

        if (! $report) {
            throw ValidationException::withMessages([
                'report_key' => ['Este reporte no está habilitado para firma.'],
            ]);
        }

        if ($project instanceof Project && ! $this->signableReportService->projectStatusAllowsSigning($report, $project->isFrozen())) {
            throw ValidationException::withMessages([
                'project' => ['Este reporte solo permite firmas en proyectos FINALIZADOS.'],
            ]);
        }

        if (! $this->signableReportService->canSignPhysically($user, $reportKey)) {
            throw new AuthorizationException('No tiene permisos para firmar físicamente este reporte.');
        }

        if ($project instanceof Project) {
            $access = $this->signatureAccessService->decision($project, $user);

            if (! $access['configured']) {
                throw ValidationException::withMessages(['user_ids' => [$access['message']]]);
            }

            if (! $access['allowed']) {
                throw new AuthorizationException($access['message']);
            }
        }
    }

    public function physicalSignedPdf(Project|Item $project, string $reportKey, array $parameters): array
    {
        if ($project instanceof Project) {
            $normalized = $this->normalizeParameters($parameters);
            $signature = $this->latestSigned($project, $reportKey, $this->parametersHash($normalized));

            if (! $signature) {
                throw ValidationException::withMessages(['signature' => ['No existe un PDF firmado para este reporte.']]);
            }

            return [
                'filename' => 'reporte_firmado.pdf',
                'content' => $this->signedPdfContent($signature),
            ];
        }

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

    public function latest(Project|Item $project, string $reportKey, ?string $parametersHash = null): ?ProjectReportSignature
    {
        return $this->forSubject(ProjectReportSignature::query(), $project)
            ->with('user')
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

    public function latestSigned(Project|Item $project, string $reportKey, ?string $parametersHash = null): ?ProjectReportSignature
    {
        return $this->forSubject(ProjectReportSignature::query(), $project)
            ->where('report_key', $reportKey)
            ->when($parametersHash, fn ($query) => $query->where('parameters_hash', $parametersHash))
            ->where('status', 'signed')
            ->whereNotNull('signed_file_path')
            ->latest('id')
            ->first();
    }

    private function latestSignedForHash(Project|Item $project, string $reportKey, string $parametersHash, string $logicalHash): ?ProjectReportSignature
    {
        return $this->forSubject(ProjectReportSignature::query(), $project)
            ->where('report_key', $reportKey)
            ->where('parameters_hash', $parametersHash)
            ->where('base_document_hash', $logicalHash)
            ->where('status', 'signed')
            ->whereNotNull('signed_file_path')
            ->latest('id')
            ->first();
    }

    private function physicalSignatures(Project|Item $project, string $reportKey, string $parametersHash, string $logicalHash)
    {
        return $this->forSubject(ProjectReportPhysicalSignature::query(), $project)
            ->with('user')
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
            'page_scope' => $signature->page_scope ?: 'all',
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
            $pdf = new Fpdi;
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

        $sharedPage = $this->sharedPhysicalPageSize($pageSizes);
        $existing = $signatures
            ->filter(fn (ProjectReportPhysicalSignature $signature): bool => $this->hasPhysicalPlacement($signature))
            ->map(fn (ProjectReportPhysicalSignature $signature): array => $this->placementFromSignature($signature))
            ->values()
            ->all();

        foreach ($signatures as $signature) {
            if ($this->hasPhysicalPlacement($signature)) {
                continue;
            }

            $placement = $this->nextDefaultPlacement($sharedPage, $existing);
            $signature->forceFill($placement)->save();
            $existing[] = ['id' => $signature->id, ...$placement];
        }
    }

    private function hasPhysicalPlacement(ProjectReportPhysicalSignature $signature): bool
    {
        return $signature->x !== null
            && $signature->y !== null
            && $signature->width !== null
            && $signature->height !== null;
    }

    private function sharedPhysicalPageSize(array $pageSizes): array
    {
        return [
            'page' => (int) ($pageSizes[0]['page'] ?? 1),
            'width' => min(array_map(fn (array $page): float => (float) $page['width'], $pageSizes)),
            'height' => min(array_map(fn (array $page): float => (float) $page['height'], $pageSizes)),
        ];
    }

    private function nextDefaultPlacement(array $pageSize, array $existing): array
    {
        $width = self::DEFAULT_PHYSICAL_SIGNATURE_WIDTH;
        $height = min(self::DEFAULT_PHYSICAL_SIGNATURE_HEIGHT, max(14.0, $this->signatureFooterHeight((float) $pageSize['height']) * 0.38));
        $margin = 10.0;
        $gap = 4.0;
        $page = (int) $pageSize['page'];
        $pageWidth = (float) $pageSize['width'];
        $pageHeight = (float) $pageSize['height'];
        $columns = max(1, (int) floor(($pageWidth - ($margin * 2) + $gap) / ($width + $gap)));
        $footerTop = $pageHeight - $this->signatureFooterHeight($pageHeight) + 2.0;
        $maxRows = max(1, (int) floor((($pageHeight - $margin) - $footerTop + $gap) / ($height + $gap)));

        for ($row = 0; $row < $maxRows; $row++) {
            for ($column = 0; $column < $columns; $column++) {
                $candidate = [
                    'page' => $page,
                    'x' => round($margin + ($column * ($width + $gap)), 2),
                    'y' => round($footerTop + ($row * ($height + $gap)), 2),
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
            'y' => min($footerTop, $pageHeight - $margin - $height),
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

    private function placementInsideEveryPage(array $placement, array $pageSizes): bool
    {
        if ($pageSizes === []) {
            return false;
        }

        foreach ($pageSizes as $page) {
            if (
                $placement['width'] <= 0
                || $placement['height'] <= 0
                || $placement['x'] < 0
                || $placement['y'] < 0
                || ($placement['x'] + $placement['width']) > ((float) $page['width'] + 0.01)
                || ($placement['y'] + $placement['height']) > ((float) $page['height'] + 0.01)
            ) {
                return false;
            }
        }

        return true;
    }

    private function placementsOverlap(array $placements): bool
    {
        for ($i = 0; $i < count($placements); $i++) {
            for ($j = $i + 1; $j < count($placements); $j++) {
                $a = $placements[$i];
                $b = $placements[$j];

                if (
                    $a['x'] < $b['x'] + $b['width']
                    && $b['x'] < $a['x'] + $a['width']
                    && $a['y'] < $b['y'] + $b['height']
                    && $b['y'] < $a['y'] + $a['height']
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public function serialize(ProjectReportSignature $signature): array
    {
        $subject = $signature->id_item ? 'item' : 'project';

        return [
            'id' => $signature->id,
            'trace_id' => $signature->trace_id,
            'subject' => $subject,
            'project_id' => $signature->id_proyecto,
            'item_id' => $signature->id_item,
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
            'page_scope' => data_get($signature->request_payload, 'page_scope'),
            'layout_hash' => data_get($signature->request_payload, 'layout_hash'),
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
        $allowed = Arr::only($parameters, ['format', 'type', 'fecha', 'mode', 'tipo_desglose']);
        ksort($allowed);

        return array_filter($allowed, fn ($value): bool => $value !== null && $value !== '');
    }

    public function parametersHash(array $parameters): string
    {
        return hash('sha256', json_encode($parameters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function currentDocumentHash(Project|Item $project, string $reportKey, array $parameters): string
    {
        if ($project instanceof Item) {
            return $this->currentItemDocumentHash($project, $reportKey, $parameters);
        }

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

    private function currentItemDocumentHash(Item $item, string $reportKey, array $parameters): string
    {
        $normalizedParameters = $this->normalizeParameters($parameters);
        $item = $item->fresh(['unitMeasure', 'groupCatalog', 'subgroupCatalog']) ?? $item;

        $composition = DB::table('item_insumo')
            ->leftJoin('insumo', 'insumo.id_insumo', '=', 'item_insumo.id_insumo')
            ->leftJoin('unidad_medida', 'unidad_medida.id_unidad_medida', '=', 'insumo.unidad_medida')
            ->where('item_insumo.id_item', $item->id_item)
            ->where('item_insumo.estado', 'AC')
            ->orderBy('item_insumo.tipo')
            ->orderBy('insumo.descripcion')
            ->orderBy('item_insumo.id_insumo')
            ->get([
                'item_insumo.id_insumo',
                'item_insumo.tipo',
                'item_insumo.cantidad',
                'item_insumo.estado',
                'insumo.descripcion as insumo',
                'insumo.precio as precio_insumo',
                'insumo.estado as estado_insumo',
                'unidad_medida.descripcion as unidad_insumo',
            ]);

        return hash('sha256', json_encode([
            'subject' => 'item',
            'report_key' => $reportKey,
            'parameters' => $normalizedParameters,
            'item' => [
                'id_item' => $item->id_item,
                'item' => $item->item,
                'precio' => $item->precio,
                'unidad' => $item->unitMeasure?->descripcion,
                'grupo' => $item->groupCatalog?->nombre_grupo,
                'subgrupo' => $item->subgroupCatalog?->descripcion,
                'estado' => $item->estado,
            ],
            'composition' => $this->stableHashRows($composition, [
                'id_insumo',
                'tipo',
                'cantidad',
                'estado',
                'insumo',
                'precio_insumo',
                'estado_insumo',
                'unidad_insumo',
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

    private function subjectColumns(Project|Item $subject): array
    {
        return $subject instanceof Item
            ? ['id_proyecto' => null, 'id_item' => $subject->id_item]
            : ['id_proyecto' => $subject->id_proyecto, 'id_item' => null];
    }

    private function subjectId(Project|Item $subject): int
    {
        return (int) ($subject instanceof Item ? $subject->id_item : $subject->id_proyecto);
    }

    private function forSubject($query, Project|Item $subject)
    {
        return $subject instanceof Item
            ? $query->where('id_item', $subject->id_item)
            : $query->where('id_proyecto', $subject->id_proyecto);
    }

    private function signatureSubject(ProjectReportSignature $signature): Project|Item
    {
        if ($signature->id_item) {
            return $signature->item ?: Item::query()->findOrFail($signature->id_item);
        }

        return $signature->project ?: Project::query()->findOrFail($signature->id_proyecto);
    }

    private function stampPhysicalSignatures(string $sourcePdf, $signatures, ?string $pageScope = null): string
    {
        $sourcePath = tempnam(sys_get_temp_dir(), 'sipre_signed_');

        if ($sourcePath === false) {
            throw new RuntimeException('No se pudo preparar el PDF para agregar firmas fisicas.');
        }

        file_put_contents($sourcePath, $sourcePdf);

        $compatiblePath = $this->fpdiCompatiblePdfPath($sourcePath);

        try {
            $pdf = new Fpdi;
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetAutoPageBreak(false);
            $pdf->SetMargins(0, 0, 0);
            $pageCount = $pdf->setSourceFile($compatiblePath);
            $templateSizes = [];
            $pageSizes = [];

            for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
                $templateId = $pdf->importPage($pageNumber);
                $templateSizes[$pageNumber] = $pdf->getTemplateSize($templateId);
                $pageSizes[] = [
                    'page' => $pageNumber,
                    'width' => (float) $templateSizes[$pageNumber]['width'],
                    'height' => (float) $templateSizes[$pageNumber]['height'],
                ];
            }

            $pageScope ??= (string) ($signatures->first()?->page_scope ?: 'all');
            $targetPages = $pageScope === 'all' ? range(1, $pageCount) : [$pageCount];
            $minimumTargetHeight = min(array_map(
                fn (int $page): float => (float) $templateSizes[$page]['height'],
                $targetPages
            ));

            $sharedPage = $this->sharedPhysicalPageSize($pageSizes);
            $placements = [];

            foreach ($signatures as $signature) {
                $placement = $this->hasPhysicalPlacement($signature)
                    ? $this->placementFromSignature($signature)
                    : ['id' => $signature->id, ...$this->nextDefaultPlacement($sharedPage, $placements)];
                $placements[] = $placement;
            }

            for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
                $templateId = $pdf->importPage($pageNumber);
                $size = $templateSizes[$pageNumber];
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId, 0, 0, $size['width'], $size['height']);

                if (! in_array($pageNumber, $targetPages, true)) {
                    continue;
                }

                foreach ($signatures as $index => $signature) {
                    $placement = $placements[$index] ?? null;

                    if (! $placement) {
                        continue;
                    }

                    $x = (float) $placement['x'];
                    $y = (float) $placement['y'] + ((float) $size['height'] - $minimumTargetHeight);
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

    private function reserveSignatureFooter(string $sourcePdf): string
    {
        $sourcePath = tempnam(sys_get_temp_dir(), 'sipre_sign_base_');

        if ($sourcePath === false) {
            throw new RuntimeException('No se pudo preparar el PDF para firma digital.');
        }

        file_put_contents($sourcePath, $sourcePdf);
        $compatiblePath = $this->fpdiCompatiblePdfPath($sourcePath);

        try {
            $pdf = new Fpdi;
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetAutoPageBreak(false);
            $pdf->SetMargins(0, 0, 0);
            $pageCount = $pdf->setSourceFile($compatiblePath);

            for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
                $templateId = $pdf->importPage($pageNumber);
                $size = $pdf->getTemplateSize($templateId);
                $pageWidth = (float) $size['width'];
                $pageHeight = (float) $size['height'];
                $footerHeight = $this->signatureFooterHeight($pageHeight);
                $scale = max(0.1, min(1.0, ($pageHeight - $footerHeight) / $pageHeight));
                $scaledWidth = $pageWidth * $scale;
                $scaledHeight = $pageHeight * $scale;

                $pdf->AddPage($size['orientation'], [$pageWidth, $pageHeight]);
                $pdf->useTemplate(
                    $templateId,
                    ($pageWidth - $scaledWidth) / 2,
                    0,
                    $scaledWidth,
                    $scaledHeight
                );
            }

            return $pdf->Output('', 'S');
        } finally {
            @unlink($sourcePath);
            if ($compatiblePath !== $sourcePath) {
                @unlink($compatiblePath);
            }
        }
    }

    private function signatureFooterHeight(float $pageHeight): float
    {
        return self::SIGNATURE_FOOTER_HEIGHT;
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

    private function assertCanStart(Project|Item $project, string $reportKey, User $user): void
    {
        $report = $this->signableReportService->findEnabled($reportKey);

        if (! $report) {
            throw ValidationException::withMessages([
                'report_key' => ['Este reporte no está habilitado para firma digital.'],
            ]);
        }

        if ($project instanceof Project && ! $this->signableReportService->projectStatusAllowsSigning($report, $project->isFrozen())) {
            throw ValidationException::withMessages([
                'project' => ['Este reporte solo permite firma digital en proyectos FINALIZADOS.'],
            ]);
        }

        if (! $this->signableReportService->canSign($user, $reportKey)) {
            throw new AuthorizationException('No tiene permisos para firmar este reporte.');
        }

        if ($project instanceof Project) {
            $access = $this->signatureAccessService->decision($project, $user);

            if (! $access['configured']) {
                throw ValidationException::withMessages(['user_ids' => [$access['message']]]);
            }

            if (! $access['allowed']) {
                throw new AuthorizationException($access['message']);
            }
        }
    }

    private function storeBasePdf(Project|Item $project, string $reportKey, string $content): string
    {
        $path = sprintf(
            '%s-signatures/%d/%s/base-%s.pdf',
            $project instanceof Item ? 'item' : 'project',
            $this->subjectId($project),
            $reportKey,
            Str::uuid()
        );

        Storage::disk('local')->put($path, $content);

        return $path;
    }

    private function deleteIncompleteAttempts(Project|Item $project, string $reportKey, string $parametersHash, User $user): void
    {
        $this->forSubject(ProjectReportSignature::query(), $project)
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
            '%s-signatures/%d/%s/signed-%s.pdf',
            $signature->id_item ? 'item' : 'project',
            $signature->id_item ?: $signature->id_proyecto,
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
        $query = $signature->id_item
            ? ProjectReportSignature::query()->where('id_item', $signature->id_item)
            : ProjectReportSignature::query()->where('id_proyecto', $signature->id_proyecto);

        return $query
            ->where('report_key', $signature->report_key)
            ->where('parameters_hash', $signature->parameters_hash)
            ->where('status', 'signed')
            ->whereNotNull('signed_file_path')
            ->where(function ($query) use ($signature): void {
                $query->where('base_document_hash', $signature->base_document_hash);

                if ($signature->project && $this->isProjectFrozen($signature->project)) {
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
            $requestPayload = is_array($signature->request_payload) ? $signature->request_payload : [];

            $signature->forceFill([
                'status' => 'auth_pending',
                'request_payload' => array_merge($requestPayload, ['redirect_uri' => $redirectUri]),
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
        if ($resolved = $this->resolvedApprovalSignature($signature)) {
            return $resolved;
        }

        $lock = Cache::lock('ciudadania_digital_approval:'.$signature->id, 60);

        if (! $lock->get()) {
            return $this->waitForApprovalSignature($signature);
        }

        try {
            $signature = $signature->fresh() ?: $signature;

            if ($resolved = $this->resolvedApprovalSignature($signature)) {
                return $resolved;
            }

            return $this->requestApprovalUnlocked($signature, $parameters);
        } finally {
            try {
                $lock->release();
            } catch (\Throwable) {
                // ponytail: lock may expire before release; nothing useful to recover here.
            }
        }
    }

    private function requestApprovalUnlocked(ProjectReportSignature $signature, array $parameters): ProjectReportSignature
    {
        $accessToken = $this->accessTokenFrom($parameters);
        $signatureCode = $this->signatureCode($signature);
        $validFrom = now();
        $validTo = $validFrom->copy()->addDays($this->validityDaysFor($signature));
        $previousSigned = $this->previousSignedFor($signature);
        $signAllPages = $this->shouldSignAllPages($parameters)
            || (bool) data_get($signature->request_payload, 'sign_all_pages');

        if ($signature->code !== $signatureCode) {
            $signature->forceFill(['code' => $signatureCode])->save();
        }

        $payload = [
            'acces_token' => $accessToken,
            'save' => 'false',
            'version' => 'V2',
            'extencion_documento' => 'PDF',
            'descripcion_documento' => $this->description($this->signatureSubject($signature), $signature->report_key),
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
                if ($signAllPages) {
                    $payload['page'] = 'ALL';
                }
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

    private function validityDaysFor(ProjectReportSignature $signature): int
    {
        $days = ProjectSignableReport::query()
            ->where('report_key', $signature->report_key)
            ->value('validity_days');

        return max(1, min(3650, (int) ($days ?? 30)));
    }

    private function resolvedApprovalSignature(ProjectReportSignature $signature): ?ProjectReportSignature
    {
        $signature = $signature->fresh() ?: $signature;

        if ($signature->status === 'signed') {
            return $signature;
        }

        if ($signature->status === 'sent' && $this->storedRedirectUrl($signature)) {
            return $signature;
        }

        return null;
    }

    private function waitForApprovalSignature(ProjectReportSignature $signature): ProjectReportSignature
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            usleep(250_000);

            if ($resolved = $this->resolvedApprovalSignature($signature)) {
                return $resolved;
            }

            $signature = $signature->fresh() ?: $signature;

            if ($signature->status === 'error') {
                throw new RuntimeException(
                    $signature->error_message ?: $this->messageWithTrace('La solicitud de firma tiene un error previo.', $signature)
                );
            }
        }

        throw new RuntimeException($this->messageWithTrace(
            'La solicitud de firma está en proceso. Intente nuevamente en unos segundos.',
            $signature->fresh() ?: $signature
        ));
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

    private function description(Project|Item $project, string $reportKey): string
    {
        $subjectName = $project instanceof Item ? $project->item : $project->nombre_proyecto;
        $subjectLabel = $project instanceof Item ? 'item' : 'proyecto';

        return 'Firma digital de '.$reportKey.' del '.$subjectLabel.' '.$subjectName;
    }

    private function filename(Project|Item $project, string $reportKey): string
    {
        $subjectName = $project instanceof Item ? $project->item : $project->nombre_proyecto;

        return Str::slug($subjectName.'-'.$reportKey).'.pdf';
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

        $code = $this->newSignatureCode($this->signatureSubject($signature));
        $signature->forceFill(['code' => $code])->save();

        return $code;
    }

    private function newSignatureCode(Project|Item $project): string
    {
        if ($project instanceof Item) {
            return sprintf('sipre-item-%d-%s', $this->subjectId($project), Str::uuid());
        }

        return sprintf('sipre-%d-%s', $this->subjectId($project), Str::uuid());
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

    private function shouldSignAllPages(array $payload): bool
    {
        return filter_var(data_get($payload, 'sign_all_pages'), FILTER_VALIDATE_BOOLEAN);
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
            'subject' => $signature->id_item ? 'item' : 'project',
            'project_id' => $signature->id_proyecto,
            'item_id' => $signature->id_item,
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

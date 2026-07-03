<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectReportSignature;
use App\Models\ProjectSignableReport;
use App\Modules\Projects\Services\ProjectReportSignatureService;
use App\Modules\Projects\Services\ProjectSignableReportService;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ProjectReportSignatureController extends Controller
{
    private const PENDING_SIGNATURE_COOKIE = 'sipre_pending_signature';

    public function __construct(
        private readonly ProjectSignableReportService $signableReportService,
        private readonly ProjectReportSignatureService $signatureService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'items' => $this->signableReportService->list($request->user()),
            'permissions' => [
                'can_manage' => $this->signableReportService->canManage($request->user()),
            ],
        ], 'Reportes firmables obtenidos correctamente.');
    }

    public function update(Request $request, string $reportKey): JsonResponse
    {
        if (! $this->signableReportService->canManage($request->user())) {
            return ApiResponse::error('No tiene permisos para administrar reportes firmables.', [
                'authorization' => ['No tiene permisos para administrar reportes firmables.'],
            ], 403);
        }

        $validated = $request->validate([
            'is_enabled' => ['required_without:requires_finalized_project', 'boolean'],
            'requires_finalized_project' => ['required_without:is_enabled', 'boolean'],
        ]);

        $report = ProjectSignableReport::query()
            ->where('report_key', $reportKey)
            ->firstOrFail();

        $report->forceFill(collect($validated)
            ->map(fn ($value): bool => (bool) $value)
            ->all())->save();

        return ApiResponse::success([
            'report' => [
                'report_key' => $report->report_key,
                'name' => $report->name,
                'description' => $report->description,
                'is_enabled' => (bool) $report->is_enabled,
                'requires_finalized_project' => (bool) $report->requires_finalized_project,
            ],
        ], 'Configuración de firma actualizada correctamente.');
    }

    public function status(Request $request, Project $project): JsonResponse
    {
        $reportKey = (string) $request->query('report_key', '');

        $request->validate([
            'report_key' => ['nullable', 'string', Rule::in(array_keys(ProjectSignableReportService::REPORTS))],
            'format' => ['nullable', 'string'],
            'type' => ['nullable', 'integer'],
            'fecha' => ['nullable', 'date'],
        ]);

        $reports = $this->signableReportService->list($request->user());

        if ($reportKey !== '') {
            $parameters = $this->signatureService->normalizeParameters($request->query());
            $hash = $this->signatureService->parametersHash($parameters);
            $latest = $this->signatureService->latest($project, $reportKey, $hash);
            $latestSigned = $this->signatureService->latestSigned($project, $reportKey, $hash);
            $report = $reports->firstWhere('report_key', $reportKey);
            $projectIsFinalized = $project->isFrozen();
            $currentDocumentHash = $latestSigned
                ? $this->signatureService->currentDocumentHash($project, $reportKey, $parameters)
                : null;
            $signedDocumentHash = $latestSigned?->base_document_hash;
            $isCurrentPdfSigned = filled($currentDocumentHash)
                && filled($signedDocumentHash)
                && hash_equals((string) $signedDocumentHash, (string) $currentDocumentHash);

            return ApiResponse::success([
                'project_is_finalized' => $projectIsFinalized,
                'project_is_frozen' => $projectIsFinalized,
                'project_status_allows_signing' => $report
                    ? (! ($report['requires_finalized_project'] ?? true) || $projectIsFinalized)
                    : false,
                'report' => $report,
                'latest_signature' => $latest ? $this->signatureService->serialize($latest) : null,
                'latest_signed' => $latestSigned ? $this->signatureService->serialize($latestSigned) : null,
                'current_document_hash' => $currentDocumentHash,
                'signed_document_hash' => $signedDocumentHash,
                'is_current_pdf_signed' => $isCurrentPdfSigned,
                'is_signed_stale' => $latestSigned
                    && ! $projectIsFinalized
                    && filled($currentDocumentHash)
                    && filled($signedDocumentHash)
                    && ! $isCurrentPdfSigned,
            ], 'Estado de firma obtenido correctamente.');
        }

        $projectIsFinalized = $project->isFrozen();

        return ApiResponse::success([
            'project_is_finalized' => $projectIsFinalized,
            'reports' => $reports,
        ], 'Estado de firma obtenido correctamente.');
    }

    public function sign(Request $request, Project $project, string $reportKey): JsonResponse
    {
        if (! array_key_exists($reportKey, ProjectSignableReportService::REPORTS)) {
            return ApiResponse::error('El reporte solicitado no existe.', [
                'report_key' => ['El reporte solicitado no existe.'],
            ], 404);
        }

        if (! $this->signableReportService->canSign($request->user(), $reportKey)) {
            return ApiResponse::error('No tiene permisos para firmar este reporte.', [
                'authorization' => ['No tiene permisos para firmar este reporte.'],
            ], 403);
        }

        $validated = $request->validate([
            'format' => ['nullable', 'string', 'in:PCA,PC_FPS,PC_UPRE,PC_FNDR,PC_OBRAS'],
            'type' => ['nullable', 'integer', 'in:1,2,3'],
            'fecha' => ['nullable', 'date'],
            'access_token' => ['nullable', 'string'],
            'acces_token' => ['nullable', 'string'],
        ]);

        try {
            $signature = $this->signatureService->start($project, $reportKey, $validated, $request->user());
        } catch (AuthorizationException $exception) {
            return ApiResponse::error($exception->getMessage() ?: 'No tiene permisos para firmar este reporte.', [
                'authorization' => [$exception->getMessage() ?: 'No tiene permisos para firmar este reporte.'],
            ], 403);
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            $firstMessage = collect($errors)->flatten()->first()
                ?: $exception->getMessage()
                ?: 'No se pudo crear la solicitud de firma.';

            return ApiResponse::error($firstMessage, $errors, 422);
        }

        $serializedSignature = $this->signatureService->serialize($signature);

        return ApiResponse::success([
            'signature' => $serializedSignature,
            'redirect_url' => $serializedSignature['redirect_url'] ?? null,
        ], 'Solicitud de firma creada correctamente.', 201)
            ->withCookie($this->pendingSignatureCookie((string) $signature->id));
    }

    public function signatures(Request $request, Project $project, string $reportKey): JsonResponse
    {
        if (! array_key_exists($reportKey, ProjectSignableReportService::REPORTS)) {
            return ApiResponse::error('El reporte solicitado no existe.', [
                'report_key' => ['El reporte solicitado no existe.'],
            ], 404);
        }

        $validated = $request->validate([
            'format' => ['nullable', 'string'],
            'type' => ['nullable', 'integer'],
            'fecha' => ['nullable', 'date'],
        ]);
        $parameters = $this->signatureService->normalizeParameters($validated);
        $hash = $this->signatureService->parametersHash($parameters);
        $latestSigned = $this->signatureService->latestSigned($project, $reportKey, $hash);

        return ApiResponse::success([
            'items' => $this->signatureService->history($project, $reportKey, $hash),
            'latest_signed' => $latestSigned ? $this->signatureService->serialize($latestSigned) : null,
            'signers' => $this->signatureService->latestSignedSigners($project, $reportKey, $hash),
        ], 'Historial de firmas obtenido correctamente.');
    }

    public function latestSigned(Request $request, Project $project, string $reportKey)
    {
        $validated = $request->validate([
            'format' => ['nullable', 'string'],
            'type' => ['nullable', 'integer'],
            'fecha' => ['nullable', 'date'],
        ]);

        $parameters = $this->signatureService->normalizeParameters($validated);
        $signature = $this->signatureService->latestSigned($project, $reportKey, $this->signatureService->parametersHash($parameters));

        if (! $signature?->signed_file_path || ! Storage::disk('local')->exists($signature->signed_file_path)) {
            return ApiResponse::error('No existe un documento firmado para este reporte.', null, 404);
        }

        return response($this->signatureService->signedPdfContent($signature), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="reporte_firmado.pdf"',
        ]);
    }

    public function loginCallback(Request $request): JsonResponse|RedirectResponse
    {
        $signature = $this->signatureForLoginCallback($request);
        $signatureId = $signature ? (string) $signature->id : '';

        if ($signatureId === '') {
            $traceId = (string) Str::uuid();
            $message = 'No se recibió la solicitud de firma. Código de seguimiento: '.$traceId;
            $recentPendingSignatures = ProjectReportSignature::query()
                ->where('status', 'auth_pending')
                ->latest('id')
                ->limit(10)
                ->get(['id', 'trace_id', 'id_usuario', 'created_at']);

            Log::warning('No se pudo correlacionar el callback de login de Ciudadanía Digital.', [
                'trace_id' => $traceId,
                'request_host' => $request->getHost(),
                'request_origin' => $request->getSchemeAndHttpHost(),
                'app_url' => config('app.url'),
                'configured_login_redirect_uri' => config('services.ciudadania_digital.login_redirect_uri'),
                'state' => $request->query('state') ?: $request->input('state'),
                'has_pending_cookie' => $request->hasCookie(self::PENDING_SIGNATURE_COOKIE),
                'pending_cookie_signature_id' => $this->signatureIdFromCookie($request) ?: null,
                'recent_auth_pending_count' => $recentPendingSignatures->count(),
                'recent_auth_pending' => $recentPendingSignatures
                    ->map(fn (ProjectReportSignature $pending): array => [
                        'id' => $pending->id,
                        'trace_id' => $pending->trace_id,
                        'user_id' => $pending->id_usuario,
                        'created_at' => optional($pending->created_at)->toIso8601String(),
                    ])
                    ->all(),
            ]);

            if (! $this->wantsJson($request)) {
                return redirect()->to($this->frontendSignatureCallbackUrl('login', [
                    'error_message' => $message,
                ]));
            }

            return ApiResponse::error($message, [
                'signature' => [$message],
            ], 422);
        }

        try {
            $signature = $this->signatureService->continueAfterAuthentication($signatureId, $request->all());
            $serializedSignature = $this->signatureService->serialize($signature);
            $redirectUrl = $serializedSignature['redirect_url'] ?? null;

            if (! $redirectUrl) {
                if (! $this->wantsJson($request)) {
                    return redirect()->to($this->frontendSignatureCallbackUrl('login', [
                        'signature' => $serializedSignature['id'] ?? $signatureId,
                        'error_message' => 'Ciudadanía Digital no devolvió una URL de firma válida.',
                    ]));
                }

                return ApiResponse::error('Ciudadanía Digital no devolvió una URL de firma válida.', [
                    'signature' => ['Ciudadanía Digital no devolvió una URL de firma válida.'],
                ], 422);
            }

            if (! $this->wantsJson($request)) {
                $debugAccessToken = $request->input('access_token') ?: $request->input('acces_token');

                return redirect()->to($this->frontendSignatureCallbackUrl('login', array_filter([
                    'signature' => $serializedSignature['id'] ?? $signatureId,
                    'debug_access_token' => is_string($debugAccessToken) ? $debugAccessToken : null,
                    'redirect_url' => $redirectUrl,
                ], static fn ($value) => $value !== null && $value !== '')));
            }

            return ApiResponse::success([
                'signature' => $serializedSignature,
                'redirect_url' => $redirectUrl,
            ], 'Autenticación validada correctamente.');
        } catch (RuntimeException $exception) {
            if (! $this->wantsJson($request)) {
                return redirect()->to($this->frontendSignatureCallbackUrl('login', [
                    'signature' => $signatureId,
                    'error_message' => $exception->getMessage(),
                ]));
            }

            return ApiResponse::error($exception->getMessage(), [
                'signature' => [$exception->getMessage()],
            ], 422);
        }
    }

    public function approvalCallback(Request $request): JsonResponse|RedirectResponse
    {
        $code = $this->signatureReferenceFrom($request);

        if ($code === '') {
            if (! $this->wantsJson($request)) {
                return redirect()->to($this->frontendSignatureCallbackUrl('approval', [
                    'error_message' => 'No se recibió el código de firma.',
                ]));
            }

            return ApiResponse::error('No se recibió el código de firma.', [
                'code' => ['No se recibió el código de firma.'],
            ], 422);
        }

        try {
            $signature = $this->signatureService->complete($code, $request->all());
            $serializedSignature = $this->signatureService->serialize($signature);
            $logoutRedirectUrl = $serializedSignature['logout_redirect_url'] ?? null;

            if (! $this->wantsJson($request)) {
                if ($logoutRedirectUrl) {
                    return redirect()->away($logoutRedirectUrl);
                }

                return redirect()->to($this->frontendSignatureCallbackUrl('logout', [
                    'signature' => $serializedSignature['id'] ?? null,
                    'completed' => '1',
                ]));
            }

            return ApiResponse::success([
                'signature' => $serializedSignature,
                'logout_redirect_url' => $logoutRedirectUrl,
            ], 'Documento firmado guardado correctamente.');
        } catch (RuntimeException $exception) {
            if (! $this->wantsJson($request)) {
                return redirect()->to($this->frontendSignatureCallbackUrl('approval', [
                    'code' => $code,
                    'error_message' => $exception->getMessage(),
                ]));
            }

            return ApiResponse::error($exception->getMessage(), [
                'signature' => [$exception->getMessage()],
            ], 422);
        }
    }

    public function logoutCallback(Request $request): JsonResponse|RedirectResponse
    {
        $signature = $this->signatureForLogoutCallback($request);

        if (! $this->wantsJson($request)) {
            return redirect()->to($this->frontendSignatureCallbackUrl('logout', [
                'signature' => $signature?->id,
                'completed' => '1',
            ]))->withCookie($this->forgetPendingSignatureCookie());
        }

        return ApiResponse::success([
            'signature' => $signature ? $this->signatureService->serialize($signature) : null,
        ], 'Sesión de Ciudadanía Digital cerrada correctamente.')
            ->withCookie($this->forgetPendingSignatureCookie());
    }

    public function callback(Request $request): JsonResponse|RedirectResponse
    {
        $signatureId = $this->signatureIdFromRequest($request);
        $pendingSignature = $signatureId !== ''
            ? ProjectReportSignature::query()->find($signatureId)
            : null;

        if ($pendingSignature?->status === 'auth_pending') {
            return $this->loginCallback($request);
        }

        return $this->approvalCallback($request);
    }

    private function signatureReferenceFrom(Request $request): string
    {
        foreach (['code', 'signature'] as $key) {
            $value = $request->query($key) ?: $request->input($key);

            if (filled($value)) {
                return (string) $value;
            }
        }

        return $this->signatureIdFromCookie($request);
    }

    private function wantsJson(Request $request): bool
    {
        return $request->expectsJson();
    }

    private function frontendSignatureCallbackUrl(string $phase, array $query = []): string
    {
        $paths = [
            'login' => '/ciudadania-digital/login/callback',
            'approval' => '/ciudadania-digital/aprobacion/callback',
            'logout' => '/ciudadania-digital/logout/callback',
        ];
        $frontendUrl = rtrim((string) config('services.ciudadania_digital.frontend_url'), '/');
        $url = ($frontendUrl !== '' ? $frontendUrl : url('/')).($paths[$phase] ?? $paths['login']);
        $query = collect($query)
            ->filter(fn ($value): bool => $value !== null && $value !== '')
            ->all();

        return $query === [] ? $url : $url.'?'.http_build_query($query);
    }

    private function signatureIdFromRequest(Request $request): string
    {
        $value = $request->query('signature') ?: $request->input('signature');

        return filled($value) ? (string) $value : $this->signatureIdFromCookie($request);
    }

    private function signatureForLoginCallback(Request $request): ?ProjectReportSignature
    {
        $signatureId = $this->signatureIdFromRequest($request);

        if ($signatureId !== '' && ctype_digit($signatureId)) {
            $signature = ProjectReportSignature::query()
                ->whereKey($signatureId)
                ->whereIn('status', ['auth_pending', 'sent'])
                ->first();

            if ($signature) {
                return $signature;
            }
        }

        $state = $request->query('state') ?: $request->input('state');

        if (filled($state)) {
            $signature = ProjectReportSignature::query()
                ->where('status', 'auth_pending')
                ->where('response_payload->auth_state', (string) $state)
                ->latest('id')
                ->first();

            if ($signature) {
                return $signature;
            }
        }

        return ProjectReportSignature::query()
            ->where('status', 'auth_pending')
            ->when(
                $request->user(),
                fn ($query, $user) => $query->where('id_usuario', $user->id_usuario)
            )
            ->latest('id')
            ->first();
    }

    private function signatureForLogoutCallback(Request $request): ?ProjectReportSignature
    {
        $signatureId = $this->signatureIdFromRequest($request);

        if ($signatureId !== '' && ctype_digit($signatureId)) {
            $signature = ProjectReportSignature::query()->find($signatureId);

            if ($signature) {
                return $signature;
            }
        }

        $code = $request->query('code') ?: $request->input('code');

        if (filled($code)) {
            $signature = ProjectReportSignature::query()
                ->where('code', (string) $code)
                ->latest('id')
                ->first();

            if ($signature) {
                return $signature;
            }
        }

        return ProjectReportSignature::query()
            ->where('status', 'signed')
            ->where('signed_at', '>=', now()->subMinutes(30))
            ->latest('signed_at')
            ->first();
    }

    private function signatureIdFromCookie(Request $request): string
    {
        $value = $request->cookie(self::PENDING_SIGNATURE_COOKIE);

        if (! is_string($value) || $value === '') {
            return '';
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return '';
        }
    }

    private function pendingSignatureCookie(string $signatureId)
    {
        return cookie(
            self::PENDING_SIGNATURE_COOKIE,
            Crypt::encryptString($signatureId),
            120,
            '/',
            null,
            request()->isSecure(),
            true,
            false,
            'Lax'
        );
    }

    private function forgetPendingSignatureCookie()
    {
        return cookie()->forget(self::PENDING_SIGNATURE_COOKIE, '/');
    }
}

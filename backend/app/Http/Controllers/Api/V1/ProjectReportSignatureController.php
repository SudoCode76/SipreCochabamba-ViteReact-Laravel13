<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Project;
use App\Models\ProjectReportSignature;
use App\Models\ProjectSignableReport;
use App\Modules\Projects\Services\ProjectPermissionService;
use App\Modules\Projects\Services\ProjectReportSignatureService;
use App\Modules\Projects\Services\ProjectSignableReportService;
use App\Modules\Projects\Services\ProjectSignatureAccessService;
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
        private readonly ProjectSignatureAccessService $signatureAccessService,
        private readonly ProjectPermissionService $projectPermissionService,
    ) {}

    public function projectAccess(Request $request, Project $project): JsonResponse
    {
        $permissions = $this->projectPermissionService->resolve($request->user());

        if (! $this->signatureAccessService->canManage($project, $request->user())
            && ! ($permissions['can_view'] ?? false)
            && ! ($permissions['can_edit'] ?? false)) {
            return ApiResponse::error('No tiene permisos para consultar esta configuración.', [
                'authorization' => ['No tiene permisos para consultar esta configuración.'],
            ], 403);
        }

        return ApiResponse::success(
            $this->signatureAccessService->configuration($project, $request->user()),
            'Configuración de firmantes obtenida correctamente.'
        );
    }

    public function updateProjectAccess(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'mode' => ['nullable', 'string', Rule::in([ProjectSignatureAccessService::MODE_SELECTED])],
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'distinct', 'exists:usuario,id_usuario'],
            'confirm_signed_removals' => ['nullable', 'boolean'],
        ]);

        try {
            $result = $this->signatureAccessService->update(
                $project,
                $request->user(),
                ProjectSignatureAccessService::MODE_SELECTED,
                $validated['user_ids'],
                (bool) ($validated['confirm_signed_removals'] ?? false),
                $request->ip()
            );
        } catch (AuthorizationException $exception) {
            return ApiResponse::error($exception->getMessage(), [
                'authorization' => [$exception->getMessage()],
            ], 403);
        }

        if ($result['requires_confirmation'] ?? false) {
            return response()->json([
                'success' => false,
                'message' => 'Algunas personas que se retirarán ya tienen firmas registradas.',
                'errors' => [
                    'confirmation' => ['Confirme que desea retirar su autorización sin eliminar sus firmas existentes.'],
                ],
                'data' => $result,
            ], 409);
        }

        return ApiResponse::success(
            $result['configuration'],
            'Configuración de firmantes actualizada correctamente.'
        );
    }

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
            'is_enabled' => ['required_without_all:requires_finalized_project,validity_days', 'boolean'],
            'requires_finalized_project' => ['required_without_all:is_enabled,validity_days', 'boolean'],
            'validity_days' => ['required_without_all:is_enabled,requires_finalized_project', 'integer', 'min:1', 'max:3650'],
        ]);

        $report = ProjectSignableReport::query()
            ->where('report_key', $reportKey)
            ->firstOrFail();

        $updates = collect($validated)
            ->map(fn ($value, string $key) => $key === 'validity_days' ? (int) $value : (bool) $value)
            ->all();

        $scope = ProjectSignableReportService::REPORTS[$report->report_key]['scope'] ?? 'project';
        if ($scope === 'item') {
            $updates['requires_finalized_project'] = false;
        }

        $report->forceFill($updates)->save();

        return ApiResponse::success([
            'report' => [
                'scope' => $scope,
                'report_key' => $report->report_key,
                'name' => $report->name,
                'description' => $report->description,
                'is_enabled' => (bool) $report->is_enabled,
                'requires_finalized_project' => $scope === 'item' ? false : (bool) $report->requires_finalized_project,
                'validity_days' => (int) ($report->validity_days ?? 30),
            ],
        ], 'Configuración de firma actualizada correctamente.');
    }

    public function status(Request $request, Project $project): JsonResponse
    {
        return $this->subjectStatus($request, $project);
    }

    public function itemStatus(Request $request, Item $item): JsonResponse
    {
        return $this->subjectStatus($request, $item);
    }

    private function subjectStatus(Request $request, Project|Item $subject): JsonResponse
    {
        $reportKey = (string) $request->query('report_key', '');

        $request->validate([
            'report_key' => ['nullable', 'string', Rule::in(array_keys(ProjectSignableReportService::REPORTS))],
            'format' => ['nullable', 'string'],
            'type' => ['nullable', 'integer'],
            'fecha' => ['nullable', 'date'],
            'mode' => ['nullable', 'string'],
            'tipo_desglose' => ['nullable', 'integer'],
        ]);

        $reports = $this->signableReportService->list($request->user());

        if ($reportKey !== '') {
            $parameters = $this->signatureService->normalizeParameters($request->query());
            $hash = $this->signatureService->parametersHash($parameters);
            $latest = $this->signatureService->latest($subject, $reportKey, $hash);
            $latestSigned = $this->signatureService->latestSigned($subject, $reportKey, $hash);
            $report = $this->reportAllowedForSubject($reportKey, $subject)
                ? $reports->firstWhere('report_key', $reportKey)
                : null;
            $signatureAccess = $subject instanceof Project
                ? $this->signatureAccessService->decision($subject, $request->user())
                : ['mode' => null, 'allowed' => true, 'message' => null];
            if ($report) {
                $report['can_sign'] = (bool) ($report['can_sign'] ?? false) && $signatureAccess['allowed'];
            }
            $projectIsFinalized = $subject instanceof Item ? true : $subject->isFrozen();
            $subjectCanBecomeStale = $subject instanceof Item || ! $projectIsFinalized;
            $currentDocumentHash = $latestSigned
                ? $this->signatureService->currentDocumentHash($subject, $reportKey, $parameters)
                : null;
            $signedDocumentHash = $latestSigned?->base_document_hash;
            $isCurrentPdfSigned = filled($currentDocumentHash)
                && filled($signedDocumentHash)
                && hash_equals((string) $signedDocumentHash, (string) $currentDocumentHash);
            $isSignedStale = (bool) $latestSigned
                && $subjectCanBecomeStale
                && (! filled($signedDocumentHash) || ! $isCurrentPdfSigned);

            return ApiResponse::success([
                'project_is_finalized' => $projectIsFinalized,
                'project_is_frozen' => $projectIsFinalized,
                'project_status_allows_signing' => $subject instanceof Item
                    ? (bool) ($report['is_enabled'] ?? false)
                    : ($report ? (! ($report['requires_finalized_project'] ?? true) || $projectIsFinalized) : false),
                'report' => $report,
                'latest_signature' => $latest ? $this->signatureService->serialize($latest) : null,
                'latest_signed' => $latestSigned ? $this->signatureService->serialize($latestSigned) : null,
                'current_document_hash' => $currentDocumentHash,
                'signed_document_hash' => $signedDocumentHash,
                'is_current_pdf_signed' => $isCurrentPdfSigned,
                'is_signed_stale' => $isSignedStale,
                'signature_access' => $signatureAccess,
            ], 'Estado de firma obtenido correctamente.');
        }

        $projectIsFinalized = $subject instanceof Item ? true : $subject->isFrozen();

        return ApiResponse::success([
            'project_is_finalized' => $projectIsFinalized,
            'reports' => $reports,
        ], 'Estado de firma obtenido correctamente.');
    }

    public function sign(Request $request, Project $project, string $reportKey): JsonResponse
    {
        return $this->subjectSign($request, $project, $reportKey);
    }

    public function itemSign(Request $request, Item $item, string $reportKey): JsonResponse
    {
        return $this->subjectSign($request, $item, $reportKey);
    }

    private function subjectSign(Request $request, Project|Item $subject, string $reportKey): JsonResponse
    {
        if (! $this->reportAllowedForSubject($reportKey, $subject)) {
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
            'mode' => ['nullable', 'string'],
            'tipo_desglose' => ['nullable', 'integer'],
            'access_token' => ['nullable', 'string'],
            'acces_token' => ['nullable', 'string'],
            'sign_all_pages' => ['nullable', 'boolean'],
            'page_scope' => ['nullable', 'string', Rule::in(['last', 'all'])],
            'layout_hash' => ['nullable', 'string', 'size:64'],
        ]);

        try {
            $signature = $this->signatureService->start($subject, $reportKey, $validated, $request->user());
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
        return $this->subjectSignatures($request, $project, $reportKey);
    }

    public function itemSignatures(Request $request, Item $item, string $reportKey): JsonResponse
    {
        return $this->subjectSignatures($request, $item, $reportKey);
    }

    private function subjectSignatures(Request $request, Project|Item $subject, string $reportKey): JsonResponse
    {
        if (! $this->reportAllowedForSubject($reportKey, $subject)) {
            return ApiResponse::error('El reporte solicitado no existe.', [
                'report_key' => ['El reporte solicitado no existe.'],
            ], 404);
        }

        $validated = $request->validate([
            'format' => ['nullable', 'string'],
            'type' => ['nullable', 'integer'],
            'fecha' => ['nullable', 'date'],
            'mode' => ['nullable', 'string'],
            'tipo_desglose' => ['nullable', 'integer'],
        ]);
        $parameters = $this->signatureService->normalizeParameters($validated);
        $hash = $this->signatureService->parametersHash($parameters);
        $latestSigned = $this->signatureService->latestSigned($subject, $reportKey, $hash);

        return ApiResponse::success([
            'items' => $this->signatureService->history($subject, $reportKey, $hash),
            'latest_signed' => $latestSigned ? $this->signatureService->serialize($latestSigned) : null,
            'signers' => $this->signatureService->latestSignedSigners($subject, $reportKey, $hash),
        ], 'Historial de firmas obtenido correctamente.');
    }

    public function latestSigned(Request $request, Project $project, string $reportKey)
    {
        return $this->subjectLatestSigned($request, $project, $reportKey);
    }

    public function itemLatestSigned(Request $request, Item $item, string $reportKey)
    {
        return $this->subjectLatestSigned($request, $item, $reportKey);
    }

    private function subjectLatestSigned(Request $request, Project|Item $subject, string $reportKey)
    {
        $validated = $request->validate([
            'format' => ['nullable', 'string'],
            'type' => ['nullable', 'integer'],
            'fecha' => ['nullable', 'date'],
            'mode' => ['nullable', 'string'],
            'tipo_desglose' => ['nullable', 'integer'],
        ]);

        $parameters = $this->signatureService->normalizeParameters($validated);
        $signature = $this->signatureService->latestSigned($subject, $reportKey, $this->signatureService->parametersHash($parameters));

        if (! $signature?->signed_file_path || ! Storage::disk('local')->exists($signature->signed_file_path)) {
            return ApiResponse::error('No existe un documento firmado para este reporte.', null, 404);
        }

        return response($this->signatureService->signedPdfContent($signature), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="reporte_firmado.pdf"',
        ]);
    }

    public function physicalSignatures(Request $request, Project $project, string $reportKey): JsonResponse
    {
        return $this->subjectPhysicalSignatures($request, $project, $reportKey);
    }

    public function preparePreview(Request $request, Project $project, string $reportKey): JsonResponse
    {
        if (! $this->reportAllowedForSubject($reportKey, $project)) {
            return ApiResponse::error('El reporte solicitado no existe.', null, 404);
        }

        $validated = $request->validate([
            'format' => ['nullable', 'string'],
            'type' => ['nullable', 'integer'],
            'fecha' => ['nullable', 'date'],
            'mode' => ['nullable', 'string'],
            'tipo_desglose' => ['nullable', 'integer'],
            'page_scope' => ['required', 'string', Rule::in(['last', 'all'])],
        ]);
        $pageScope = $validated['page_scope'];
        unset($validated['page_scope']);

        try {
            $preview = $this->signatureService->prepareProjectPreview(
                $project,
                $reportKey,
                $validated,
                $pageScope,
                $request->user()
            );
        } catch (AuthorizationException $exception) {
            return ApiResponse::error($exception->getMessage(), ['authorization' => [$exception->getMessage()]], 403);
        }

        $preview['preview_url'] = url("/api/v1/projects/{$project->id_proyecto}/reports/{$reportKey}/signature-preview/pdf")
            .'?'.http_build_query($validated);

        return ApiResponse::success($preview, 'Previsualización de firmas preparada correctamente.');
    }

    public function updatePreviewPositions(Request $request, Project $project, string $reportKey): JsonResponse
    {
        $validated = $request->validate([
            'format' => ['nullable', 'string'],
            'type' => ['nullable', 'integer'],
            'fecha' => ['nullable', 'date'],
            'mode' => ['nullable', 'string'],
            'tipo_desglose' => ['nullable', 'integer'],
            'layout_hash' => ['required', 'string', 'size:64'],
            'positions' => ['required', 'array'],
            'positions.*.id' => ['required', 'integer'],
            'positions.*.x' => ['required', 'numeric', 'min:0'],
            'positions.*.y' => ['required', 'numeric', 'min:0'],
            'positions.*.width' => ['required', 'numeric', 'min:20'],
            'positions.*.height' => ['required', 'numeric', 'min:12'],
        ]);
        $positions = $validated['positions'];
        unset($validated['positions']);

        try {
            $preview = $this->signatureService->updatePhysicalSignaturePositions(
                $project,
                $reportKey,
                $validated,
                $positions,
                $request->user()
            );
        } catch (AuthorizationException $exception) {
            return ApiResponse::error($exception->getMessage(), ['authorization' => [$exception->getMessage()]], 403);
        }

        $preview['preview_url'] = url("/api/v1/projects/{$project->id_proyecto}/reports/{$reportKey}/signature-preview/pdf")
            .'?'.http_build_query($this->signatureService->normalizeParameters($validated));

        return ApiResponse::success($preview, 'Posiciones de firmas guardadas correctamente.');
    }

    public function previewPdf(Request $request, Project $project, string $reportKey)
    {
        $validated = $request->validate([
            'format' => ['nullable', 'string'],
            'type' => ['nullable', 'integer'],
            'fecha' => ['nullable', 'date'],
            'mode' => ['nullable', 'string'],
            'tipo_desglose' => ['nullable', 'integer'],
        ]);
        $pdf = $this->signatureService->projectPreviewPdf($project, $reportKey, $validated, $request->user());

        return response($pdf['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$pdf['filename'].'"',
        ]);
    }

    public function cancel(Request $request, Project $project, ProjectReportSignature $signature): JsonResponse
    {
        try {
            $cancelled = $this->signatureService->cancel($project, $signature, $request->user());
        } catch (AuthorizationException $exception) {
            return ApiResponse::error($exception->getMessage(), ['authorization' => [$exception->getMessage()]], 403);
        }

        return ApiResponse::success(
            ['signature' => $this->signatureService->serialize($cancelled)],
            'Solicitud de firma cancelada correctamente.'
        );
    }

    public function itemPhysicalSignatures(Request $request, Item $item, string $reportKey): JsonResponse
    {
        return $this->subjectPhysicalSignatures($request, $item, $reportKey);
    }

    private function subjectPhysicalSignatures(Request $request, Project|Item $subject, string $reportKey): JsonResponse
    {
        if (! $this->reportAllowedForSubject($reportKey, $subject)) {
            return ApiResponse::error('El reporte solicitado no existe.', [
                'report_key' => ['El reporte solicitado no existe.'],
            ], 404);
        }

        $validated = $request->validate([
            'format' => ['nullable', 'string'],
            'type' => ['nullable', 'integer'],
            'fecha' => ['nullable', 'date'],
            'mode' => ['nullable', 'string'],
            'tipo_desglose' => ['nullable', 'integer'],
        ]);

        return ApiResponse::success(
            $this->signatureService->physicalSignatureStatus($subject, $reportKey, $validated, $request->user()),
            'Firmas fisicas obtenidas correctamente.'
        );
    }

    public function markPhysicalSignature(Request $request, Project $project, string $reportKey): JsonResponse
    {
        return $this->subjectMarkPhysicalSignature($request, $project, $reportKey);
    }

    public function itemMarkPhysicalSignature(Request $request, Item $item, string $reportKey): JsonResponse
    {
        return $this->subjectMarkPhysicalSignature($request, $item, $reportKey);
    }

    private function subjectMarkPhysicalSignature(Request $request, Project|Item $subject, string $reportKey): JsonResponse
    {
        if (! $this->reportAllowedForSubject($reportKey, $subject)) {
            return ApiResponse::error('El reporte solicitado no existe.', [
                'report_key' => ['El reporte solicitado no existe.'],
            ], 404);
        }

        $validated = $request->validate([
            'format' => ['nullable', 'string'],
            'type' => ['nullable', 'integer'],
            'fecha' => ['nullable', 'date'],
            'mode' => ['nullable', 'string'],
            'tipo_desglose' => ['nullable', 'integer'],
        ]);

        try {
            $status = $this->signatureService->markPhysicalSignature($subject, $reportKey, $validated, $request->user());
        } catch (AuthorizationException $exception) {
            return ApiResponse::error($exception->getMessage(), [
                'authorization' => [$exception->getMessage()],
            ], 403);
        }

        return ApiResponse::success($status, 'Firma fisica marcada correctamente.');
    }

    public function updatePhysicalSignaturePositions(Request $request, Project $project, string $reportKey): JsonResponse
    {
        return $this->subjectUpdatePhysicalSignaturePositions($request, $project, $reportKey);
    }

    public function itemUpdatePhysicalSignaturePositions(Request $request, Item $item, string $reportKey): JsonResponse
    {
        return $this->subjectUpdatePhysicalSignaturePositions($request, $item, $reportKey);
    }

    private function subjectUpdatePhysicalSignaturePositions(Request $request, Project|Item $subject, string $reportKey): JsonResponse
    {
        if (! $this->reportAllowedForSubject($reportKey, $subject)) {
            return ApiResponse::error('El reporte solicitado no existe.', [
                'report_key' => ['El reporte solicitado no existe.'],
            ], 404);
        }

        $validated = $request->validate([
            'format' => ['nullable', 'string'],
            'type' => ['nullable', 'integer'],
            'fecha' => ['nullable', 'date'],
            'mode' => ['nullable', 'string'],
            'tipo_desglose' => ['nullable', 'integer'],
            'positions' => ['required', 'array'],
            'positions.*.id' => ['required', 'integer'],
            'positions.*.page' => ['required', 'integer', 'min:1'],
            'positions.*.x' => ['required', 'numeric', 'min:0'],
            'positions.*.y' => ['required', 'numeric', 'min:0'],
            'positions.*.width' => ['required', 'numeric', 'min:1'],
            'positions.*.height' => ['required', 'numeric', 'min:1'],
        ]);

        $positions = $validated['positions'];
        unset($validated['positions']);

        return ApiResponse::success(
            $this->signatureService->updatePhysicalSignaturePositions($subject, $reportKey, $validated, $positions, $request->user()),
            'Posiciones de firmas fisicas actualizadas correctamente.'
        );
    }

    public function physicalSignaturesPdf(Request $request, Project $project, string $reportKey)
    {
        return $this->subjectPhysicalSignaturesPdf($request, $project, $reportKey);
    }

    public function itemPhysicalSignaturesPdf(Request $request, Item $item, string $reportKey)
    {
        return $this->subjectPhysicalSignaturesPdf($request, $item, $reportKey);
    }

    private function subjectPhysicalSignaturesPdf(Request $request, Project|Item $subject, string $reportKey)
    {
        if (! $this->reportAllowedForSubject($reportKey, $subject)) {
            return ApiResponse::error('El reporte solicitado no existe.', [
                'report_key' => ['El reporte solicitado no existe.'],
            ], 404);
        }

        $validated = $request->validate([
            'format' => ['nullable', 'string'],
            'type' => ['nullable', 'integer'],
            'fecha' => ['nullable', 'date'],
            'mode' => ['nullable', 'string'],
            'tipo_desglose' => ['nullable', 'integer'],
        ]);
        $pdf = $this->signatureService->physicalSignedPdf($subject, $reportKey, $validated);

        return response($pdf['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$pdf['filename'].'"',
        ]);
    }

    private function reportAllowedForSubject(string $reportKey, Project|Item $subject): bool
    {
        $definition = ProjectSignableReportService::REPORTS[$reportKey] ?? null;

        if (! $definition) {
            return false;
        }

        $scope = $definition['scope'] ?? 'project';

        return $subject instanceof Item ? $scope === 'item' : $scope === 'project';
    }

    public function citizenshipSession(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->signatureService->citizenshipSession($request->user()),
            'Estado de sesión de Ciudadanía Digital obtenido correctamente.'
        );
    }

    public function logoutCitizenshipSession(Request $request): JsonResponse
    {
        $session = $this->signatureService->logoutCitizenshipSession($request->user());

        if (($session['active'] ?? false) && ! ($session['logout_redirect_url'] ?? null)) {
            return ApiResponse::error(
                'No se pudo generar el cierre de sesión de Ciudadanía Digital. Intente iniciar la firma nuevamente.',
                ['session' => ['La sesión ya no dispone de un token válido para cerrarse.']],
                422
            );
        }

        return ApiResponse::success($session, 'Cierre de sesión de Ciudadanía Digital iniciado correctamente.');
    }

    public function loginCallback(Request $request): JsonResponse|RedirectResponse
    {
        $requestedSignatureId = $this->signatureIdFromRequest($request);
        $signature = $this->signatureForLoginCallback($request);
        $signatureId = $signature ? (string) $signature->id : '';

        if ($signatureId === '') {
            $traceId = (string) Str::uuid();
            $message = $requestedSignatureId !== '' && ctype_digit($requestedSignatureId)
                ? 'La solicitud de firma ya no está activa. Inicie la firma nuevamente.'
                : 'No se recibió la solicitud de firma. Código de seguimiento: '.$traceId;
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
        $signature = $this->signatureService->confirmCitizenshipLogout(
            $this->signatureForLogoutCallback($request)
        );

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

            return null;
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

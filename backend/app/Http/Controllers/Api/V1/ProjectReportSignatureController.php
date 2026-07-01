<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectReportSignature;
use App\Models\ProjectSignableReport;
use App\Modules\Projects\Services\ProjectReportSignatureService;
use App\Modules\Projects\Services\ProjectSignableReportService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use RuntimeException;

class ProjectReportSignatureController extends Controller
{
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
            'is_enabled' => ['required', 'boolean'],
        ]);

        $report = ProjectSignableReport::query()
            ->where('report_key', $reportKey)
            ->firstOrFail();

        $report->forceFill(['is_enabled' => (bool) $validated['is_enabled']])->save();

        return ApiResponse::success([
            'report' => [
                'report_key' => $report->report_key,
                'name' => $report->name,
                'description' => $report->description,
                'is_enabled' => (bool) $report->is_enabled,
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
            $latest = $this->signatureService->latestSigned($project, $reportKey, $hash);

            return ApiResponse::success([
                'project_is_finalized' => $project->isFrozen(),
                'report' => $reports->firstWhere('report_key', $reportKey),
                'latest_signed' => $latest ? $this->signatureService->serialize($latest) : null,
            ], 'Estado de firma obtenido correctamente.');
        }

        return ApiResponse::success([
            'project_is_finalized' => $project->isFrozen(),
            'reports' => $reports,
        ], 'Estado de firma obtenido correctamente.');
    }

    public function sign(Request $request, Project $project, string $reportKey): JsonResponse
    {
        $validated = $request->validate([
            'format' => ['nullable', 'string', 'in:PCA,PC_FPS,PC_UPRE,PC_FNDR,PC_OBRAS'],
            'type' => ['nullable', 'integer', 'in:1,2,3'],
            'fecha' => ['nullable', 'date'],
            'access_token' => ['nullable', 'string'],
            'acces_token' => ['nullable', 'string'],
        ]);

        $signature = $this->signatureService->start($project, $reportKey, $validated, $request->user());

        $serializedSignature = $this->signatureService->serialize($signature);

        return ApiResponse::success([
            'signature' => $serializedSignature,
            'redirect_url' => $serializedSignature['redirect_url'] ?? null,
        ], 'Solicitud de firma creada correctamente.', 201);
    }

    public function signatures(Request $request, Project $project, string $reportKey): JsonResponse
    {
        if (! array_key_exists($reportKey, ProjectSignableReportService::REPORTS)) {
            return ApiResponse::error('El reporte solicitado no existe.', [
                'report_key' => ['El reporte solicitado no existe.'],
            ], 404);
        }

        return ApiResponse::success([
            'items' => $this->signatureService->history($project, $reportKey),
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

        return response(Storage::disk('local')->get($signature->signed_file_path), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="reporte_firmado.pdf"',
        ]);
    }

    public function loginCallback(Request $request): JsonResponse
    {
        $signatureId = (string) ($request->query('signature') ?: $request->input('signature'));

        if ($signatureId === '') {
            return ApiResponse::error('No se recibió la solicitud de firma.', [
                'signature' => ['No se recibió la solicitud de firma.'],
            ], 422);
        }

        try {
            $signature = $this->signatureService->continueAfterAuthentication($signatureId, $request->all());
            $serializedSignature = $this->signatureService->serialize($signature);
            $redirectUrl = $serializedSignature['redirect_url'] ?? null;

            if (! $redirectUrl) {
                return ApiResponse::error('Ciudadanía Digital no devolvió una URL de firma válida.', [
                    'signature' => ['Ciudadanía Digital no devolvió una URL de firma válida.'],
                ], 422);
            }

            return ApiResponse::success([
                'signature' => $serializedSignature,
                'redirect_url' => $redirectUrl,
            ], 'Autenticación validada correctamente.');
        } catch (RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), [
                'signature' => [$exception->getMessage()],
            ], 422);
        }
    }

    public function approvalCallback(Request $request): JsonResponse
    {
        $code = (string) ($request->query('code') ?: $request->input('code') ?: $request->query('signature') ?: $request->input('signature'));

        if ($code === '') {
            return ApiResponse::error('No se recibió el código de firma.', [
                'code' => ['No se recibió el código de firma.'],
            ], 422);
        }

        try {
            $signature = $this->signatureService->complete($code, $request->all());
            $serializedSignature = $this->signatureService->serialize($signature);

            return ApiResponse::success([
                'signature' => $serializedSignature,
                'logout_redirect_url' => $serializedSignature['logout_redirect_url'] ?? null,
            ], 'Documento firmado guardado correctamente.');
        } catch (RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), [
                'signature' => [$exception->getMessage()],
            ], 422);
        }
    }

    public function logoutCallback(Request $request): JsonResponse
    {
        $signatureId = (string) ($request->query('signature') ?: $request->input('signature'));
        $signature = $signatureId !== ''
            ? ProjectReportSignature::query()->find($signatureId)
            : null;

        return ApiResponse::success([
            'signature' => $signature ? $this->signatureService->serialize($signature) : null,
        ], 'Sesión de Ciudadanía Digital cerrada correctamente.');
    }

    public function callback(Request $request): JsonResponse|RedirectResponse
    {
        $signatureId = (string) ($request->query('signature') ?: $request->input('signature'));
        $pendingSignature = $signatureId !== ''
            ? ProjectReportSignature::query()->find($signatureId)
            : null;

        if ($pendingSignature?->status === 'auth_pending') {
            return $this->loginCallback($request);
        }

        return $this->approvalCallback($request);
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Authorization\IndexAuthorizationRequest;
use App\Http\Requests\Authorization\UpdateAuthorizationStatusRequest;
use App\Http\Resources\Authorization\AuthorizationResource;
use App\Models\Authorization;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AuthorizationController extends Controller
{
    public function __construct(private readonly AuditService $auditService) {}

    public function context(): JsonResponse
    {
        $modules = Authorization::query()
            ->selectRaw("LOWER(COALESCE(NULLIF(TRIM(tipo_elemento), ''), NULLIF(TRIM(tabla), ''))) as module")
            ->where(function ($query): void {
                $query->whereNotNull('tipo_elemento')
                    ->orWhereNotNull('tabla');
            })
            ->distinct()
            ->orderBy('module')
            ->pluck('module')
            ->filter(fn (?string $value): bool => filled(trim((string) $value)))
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Contexto de autorizaciones obtenido correctamente.',
            'data' => [
                'statuses' => [
                    ['code' => 'PE', 'label' => 'PENDIENTE'],
                    ['code' => 'AP', 'label' => 'AUTORIZADO'],
                    ['code' => 'NP', 'label' => 'NO PROCEDE'],
                ],
                'processable_statuses' => [
                    ['code' => 'AP', 'label' => 'AUTORIZADO'],
                    ['code' => 'NP', 'label' => 'NO PROCEDE'],
                ],
                'modules' => $modules->map(fn (string $module): array => [
                    'value' => $module,
                    'label' => strtoupper($module),
                ])->all(),
                'permissions' => [
                    'can_view' => true,
                    'can_process' => true,
                ],
                'filters' => ['search', 'status', 'module', 'page', 'per_page'],
                'endpoints' => [
                    'list' => '/api/v1/authorizations',
                    'show' => '/api/v1/authorizations/{id}',
                    'update_status' => '/api/v1/authorizations/{id}/status',
                ],
            ],
        ]);
    }

    public function index(IndexAuthorizationRequest $request): JsonResponse
    {
        $query = Authorization::query()->with('requester');

        if ($request->filled('search')) {
            $search = trim($request->string('search')->toString());

            $query->where(function ($query) use ($search): void {
                $query->where('elemento', 'like', "%{$search}%")
                    ->orWhere('tipo_elemento', 'like', "%{$search}%")
                    ->orWhere('tabla', 'like', "%{$search}%")
                    ->orWhere('nro_autorizacion', 'like', "%{$search}%")
                    ->orWhereHas('requester', fn ($query) => $query->where('funcionario', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('estado', strtoupper($request->string('status')->toString()));
        }

        if ($request->filled('module')) {
            $module = strtolower(trim($request->string('module')->toString()));

            $query->where(function ($query) use ($module): void {
                $query->whereRaw("LOWER(COALESCE(tipo_elemento, '')) = ?", [$module])
                    ->orWhereRaw("LOWER(COALESCE(tabla, '')) = ?", [$module]);
            });
        }

        $authorizations = $query
            ->orderByDesc('fecha')
            ->orderByDesc((new Authorization)->getKeyName())
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return response()->json([
            'success' => true,
            'message' => 'Autorizaciones obtenidas correctamente.',
            'data' => [
                'items' => AuthorizationResource::collection($authorizations->getCollection())->resolve(),
                'meta' => [
                    'current_page' => $authorizations->currentPage(),
                    'per_page' => $authorizations->perPage(),
                    'total' => $authorizations->total(),
                    'from' => $authorizations->firstItem(),
                    'to' => $authorizations->lastItem(),
                    'last_page' => $authorizations->lastPage(),
                    'has_more_pages' => $authorizations->hasMorePages(),
                ],
            ],
        ]);
    }

    public function show(Authorization $authorization): JsonResponse
    {
        $authorization->load('requester');

        return response()->json([
            'success' => true,
            'message' => 'Autorizacion obtenida correctamente.',
            'data' => [
                'authorization' => AuthorizationResource::make($authorization)->resolve(),
            ],
        ]);
    }

    public function updateStatus(UpdateAuthorizationStatusRequest $request, Authorization $authorization): JsonResponse
    {
        $authorization->load('requester');

        if (strtoupper((string) $authorization->estado) !== 'PE') {
            throw ValidationException::withMessages([
                'authorization' => ['La autorizacion ya fue procesada y no admite cambios adicionales.'],
            ]);
        }

        $nextStatus = strtoupper($request->string('estado')->toString());

        $payload = [
            'estado' => $nextStatus,
            'nro_autorizacion' => $nextStatus === 'AP'
                ? $this->generateAuthorizationNumber($authorization)
                : null,
        ];

        if (Schema::hasColumn($authorization->getTable(), 'usuario_adm')) {
            $payload['usuario_adm'] = $request->user()?->id_usuario;
        }

        if (Schema::hasColumn($authorization->getTable(), 'fecha_aut')) {
            $payload['fecha_aut'] = now();
        }

        $authorization->update($payload);

        $authorization->refresh()->load('requester');
        $this->auditService->record($request->user(), $request->ip(), 'ADMINISTRACION: se proceso la autorizacion '.$authorization->getKey());

        return response()->json([
            'success' => true,
            'message' => 'Autorizacion procesada correctamente.',
            'data' => [
                'authorization' => AuthorizationResource::make($authorization)->resolve(),
            ],
        ]);
    }

    private function generateAuthorizationNumber(Authorization $authorization): int
    {
        $baseValue = $authorization->getAttribute('num_sec') ?: $authorization->getAttribute($authorization->getKeyName());
        $sequence = (string) $baseValue;

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $candidate = (int) ($sequence.random_int(10, 9999));

            if (! Authorization::query()->where('nro_autorizacion', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new RuntimeException('No se pudo generar un numero de autorizacion unico.');
    }
}

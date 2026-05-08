<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\IndexUserRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Requests\User\UpdateUserRoleRequest;
use App\Http\Requests\User\UpdateUserStatusRequest;
use App\Http\Requests\User\UpdateUserUnitRequest;
use App\Http\Resources\User\UserResource;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Users\UserUnitResolverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function __construct(
        private readonly AuditService $auditService,
        private readonly UserUnitResolverService $userUnitResolverService,
    ) {}

    public function index(IndexUserRequest $request): JsonResponse
    {
        $query = User::query()
            ->with(['role', 'unit'])
            ->orderBy('id_usuario');

        if ($request->filled('search')) {
            $search = Str::lower(trim($request->string('search')->toString()));
            $query->where(function ($query) use ($search): void {
                $query->whereRaw('LOWER(TRIM(funcionario)) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(TRIM(username)) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(TRIM(ci)) LIKE ?', ["%{$search}%"])
                    ->orWhereHas('role', function ($query) use ($search): void {
                        $query->whereRaw('LOWER(TRIM(nombre_rol)) LIKE ?', ["%{$search}%"]);
                    })
                    ->orWhereHas('unit', function ($query) use ($search): void {
                        $query->whereRaw('LOWER(TRIM(descripcion)) LIKE ?', ["%{$search}%"]);
                    });
            });
        }

        if ($request->filled('name')) {
            $name = Str::lower(trim($request->string('name')->toString()));
            $query->whereRaw('LOWER(TRIM(funcionario)) LIKE ?', ["%{$name}%"]);
        }

        if ($request->filled('ci')) {
            $ci = trim($request->string('ci')->toString());
            $query->whereRaw('TRIM(ci) = ?', [$ci]);
        }

        if ($request->filled('username')) {
            $username = Str::lower(trim($request->string('username')->toString()));
            $query->whereRaw('LOWER(TRIM(username)) LIKE ?', ["%{$username}%"]);
        }

        if ($request->filled('status')) {
            $query->where('estado', strtoupper($request->string('status')->toString()));
        }

        if ($request->filled('role_id')) {
            $query->where('rol', (int) $request->integer('role_id'));
        }

        if ($request->filled('unit_id')) {
            $query->where('id_unidad', (int) $request->integer('unit_id'));
        }

        $perPage = $request->integer('per_page', 15);
        $users = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'success' => true,
            'message' => 'Usuarios obtenidos correctamente.',
            'data' => [
                'items' => UserResource::collection($users->getCollection())->resolve(),
                'meta' => [
                    'current_page' => $users->currentPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                ],
            ],
        ]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $resolvedUnitId = $this->userUnitResolverService->resolveId(
            $request->filled('unit_id') ? (int) $request->integer('unit_id') : null,
            $request->filled('unit_description') ? $request->string('unit_description')->toString() : null,
        );

        $user = User::query()->create([
            'funcionario' => trim($request->string('funcionario')->toString()),
            'ci' => trim($request->string('ci')->toString()),
            'username' => strtoupper(trim($request->string('username')->toString())),
            'clave' => Hash::make($request->string('password')->toString()),
            'estado' => strtoupper($request->string('estado')->toString()),
            'id_unidad' => $resolvedUnitId,
            'rol' => (int) $request->integer('role_id'),
            'item' => $request->filled('item') ? (int) $request->integer('item') : null,
            'fecha' => now()->toDateString(),
            'subalcaldia' => $request->filled('subalcaldia') ? (int) $request->integer('subalcaldia') : null,
        ]);

        $user->load(['role', 'unit']);
        $this->registerAudit($request->user(), $request->ip(), 'Creacion de usuario '.$user->username);

        return response()->json([
            'success' => true,
            'message' => 'Usuario creado correctamente.',
            'data' => [
                'user' => UserResource::make($user)->resolve(),
            ],
        ], 201);
    }

    public function show(User $user): JsonResponse
    {
        $user->load(['role', 'unit']);

        return response()->json([
            'success' => true,
            'message' => 'Usuario obtenido correctamente.',
            'data' => [
                'user' => UserResource::make($user)->resolve(),
            ],
        ]);
    }

    public function displayName(User $user): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Nombre visible del usuario obtenido correctamente.',
            'data' => [
                'id_usuario' => $user->id_usuario,
                'funcionario' => $user->funcionario,
                'username' => $user->username,
                'estado' => $user->estado,
            ],
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $resolvedUnitId = $this->userUnitResolverService->resolveId(
            $request->filled('unit_id') ? (int) $request->integer('unit_id') : null,
            $request->filled('unit_description') ? $request->string('unit_description')->toString() : null,
        );

        $user->update([
            'funcionario' => trim($request->string('funcionario')->toString()),
            'ci' => trim($request->string('ci')->toString()),
            'username' => strtoupper(trim($request->string('username')->toString())),
            'estado' => strtoupper($request->string('estado')->toString()),
            'id_unidad' => $resolvedUnitId,
            'rol' => (int) $request->integer('role_id'),
            'item' => $request->filled('item') ? (int) $request->integer('item') : null,
            'subalcaldia' => $request->filled('subalcaldia') ? (int) $request->integer('subalcaldia') : null,
        ]);

        $user->refresh()->load(['role', 'unit']);
        $this->registerAudit($request->user(), $request->ip(), 'Actualizacion de usuario '.$user->username);

        return response()->json([
            'success' => true,
            'message' => 'Usuario actualizado correctamente.',
            'data' => [
                'user' => UserResource::make($user)->resolve(),
            ],
        ]);
    }

    public function updateStatus(UpdateUserStatusRequest $request, User $user): JsonResponse
    {
        $user->update([
            'estado' => strtoupper($request->string('estado')->toString()),
        ]);

        $user->refresh()->load(['role', 'unit']);
        $this->registerAudit($request->user(), $request->ip(), 'Cambio de estado de usuario '.$user->username);

        return response()->json([
            'success' => true,
            'message' => 'Estado del usuario actualizado correctamente.',
            'data' => [
                'user' => UserResource::make($user)->resolve(),
            ],
        ]);
    }

    public function updateRole(UpdateUserRoleRequest $request, User $user): JsonResponse
    {
        $user->update([
            'rol' => (int) $request->integer('role_id'),
        ]);

        $user->refresh()->load(['role', 'unit']);
        $this->registerAudit($request->user(), $request->ip(), 'Cambio de rol de usuario '.$user->username);

        return response()->json([
            'success' => true,
            'message' => 'Rol del usuario actualizado correctamente.',
            'data' => [
                'user' => UserResource::make($user)->resolve(),
            ],
        ]);
    }

    public function updateUnit(UpdateUserUnitRequest $request, User $user): JsonResponse
    {
        $resolvedUnitId = $this->userUnitResolverService->resolveId(
            $request->filled('unit_id') ? (int) $request->integer('unit_id') : null,
            $request->filled('unit_description') ? $request->string('unit_description')->toString() : null,
        );

        $user->update([
            'id_unidad' => $resolvedUnitId,
        ]);

        $user->refresh()->load(['role', 'unit']);
        $this->registerAudit($request->user(), $request->ip(), 'Cambio de unidad de usuario '.$user->username);

        return response()->json([
            'success' => true,
            'message' => 'Unidad del usuario actualizada correctamente.',
            'data' => [
                'user' => UserResource::make($user)->resolve(),
            ],
        ]);
    }

    private function registerAudit(?User $actor, ?string $ip, string $process): void
    {
        $this->auditService->record($actor, $ip, $process);
    }
}

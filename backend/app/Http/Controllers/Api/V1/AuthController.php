<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\Auth\AuthenticatedUserResource;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Auth\LegacyPasswordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function __construct(
        private readonly LegacyPasswordService $legacyPasswordService,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $normalizedUsername = Str::lower(trim($request->string('username')->toString()));

        $user = User::query()
            ->with(['role', 'unit'])
            ->whereRaw('LOWER(TRIM(username)) = ?', [$normalizedUsername])
            ->first();

        if (! $user || ! $this->legacyPasswordService->validateAndMigrate($user, $request->string('clave')->toString())) {
            $this->registerAudit(null, $request, 'Inicio de sesion fallido');

            return response()->json([
                'success' => false,
                'message' => 'Credenciales invalidas.',
                'errors' => [
                    'username' => ['Las credenciales proporcionadas no son validas.'],
                ],
            ], 422);
        }

        if (! $user->isActive() || ! $user->role?->isActive()) {
            $this->registerAudit($user, $request, 'Inicio de sesion rechazado por estado inactivo');

            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene acceso habilitado.',
                'errors' => [
                    'username' => ['El usuario o su rol no se encuentran activos.'],
                ],
            ], 403);
        }

        $permissions = $user->activePermissions()->get();
        $user->setRelation('permissions', $permissions);

        $token = $user->createToken($this->resolveDeviceName($request))->plainTextToken;

        $this->registerAudit($user, $request, 'Inicio de sesion exitoso');

        return response()->json([
            'success' => true,
            'message' => 'Inicio de sesion realizado correctamente.',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => new AuthenticatedUserResource($user),
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing(['role', 'unit']);
        $user->setRelation('permissions', $user->activePermissions()->get());

        return response()->json([
            'success' => true,
            'message' => 'Perfil autenticado obtenido correctamente.',
            'data' => [
                'user' => new AuthenticatedUserResource($user),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->currentAccessToken()?->delete();

        $this->registerAudit($user, $request, 'Cierre de sesion');

        return response()->json([
            'success' => true,
            'message' => 'Sesion cerrada correctamente.',
            'data' => null,
        ]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $this->legacyPasswordService->validateAndMigrate($user, $request->string('current_password')->toString())) {
            return response()->json([
                'success' => false,
                'message' => 'La contrasena actual no es valida.',
                'errors' => [
                    'current_password' => ['La contrasena actual proporcionada no es valida.'],
                ],
            ], 422);
        }

        $newPassword = $request->string('password')->toString();

        if (Hash::check($newPassword, (string) $user->clave)) {
            return response()->json([
                'success' => false,
                'message' => 'La nueva contrasena debe ser diferente a la actual.',
                'errors' => [
                    'password' => ['La nueva contrasena debe ser diferente a la actual.'],
                ],
            ], 422);
        }

        $user->forceFill([
            'clave' => Hash::make($newPassword),
        ])->save();

        $this->registerAudit($user, $request, 'Cambio de contrasena');

        return response()->json([
            'success' => true,
            'message' => 'Contrasena actualizada correctamente.',
            'data' => null,
        ]);
    }

    private function resolveDeviceName(Request $request): string
    {
        $deviceName = $request->input('device_name');

        if (is_string($deviceName) && $deviceName !== '') {
            return $deviceName;
        }

        return (string) ($request->userAgent() ?: 'api-client');
    }

    private function registerAudit(?User $user, Request $request, string $process): void
    {
        try {
            AuditLog::query()->create([
                'nombre_completo' => $user?->funcionario,
                'fecha_hora' => now(),
                'ip' => $request->ip(),
                'proceso' => $process,
            ]);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}

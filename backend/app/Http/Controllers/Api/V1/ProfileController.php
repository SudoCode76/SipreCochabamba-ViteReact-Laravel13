<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Resources\Auth\AuthenticatedUserResource;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Auth\LegacyPasswordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function __construct(
        private readonly LegacyPasswordService $legacyPasswordService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing(['role', 'unit']);
        $user->setRelation('permissions', $user->activePermissions()->get());

        return response()->json([
            'success' => true,
            'message' => 'Perfil obtenido correctamente.',
            'data' => [
                'user' => new AuthenticatedUserResource($user),
            ],
        ]);
    }

    public function updatePassword(ChangePasswordRequest $request): JsonResponse
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

        $this->registerAudit($user, $request, 'Cambio de contrasena desde perfil');

        return response()->json([
            'success' => true,
            'message' => 'Contrasena actualizada correctamente.',
            'data' => null,
        ]);
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

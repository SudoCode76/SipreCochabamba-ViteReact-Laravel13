<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Resources\Auth\AuthenticatedUserResource;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Auth\LegacyPasswordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function __construct(
        private readonly LegacyPasswordService $legacyPasswordService,
        private readonly AuditService $auditService,
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

    public function uploadSignatureImage(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validate([
            'signature_image' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        if ($user->firma_imagen_path) {
            Storage::disk('public')->delete($user->firma_imagen_path);
        }

        $path = $validated['signature_image']->store(
            'archivos/firmas_usuarios/'.$user->id_usuario,
            'public'
        );

        $user->forceFill(['firma_imagen_path' => $path])->save();
        $this->registerAudit($user, $request, 'Carga de imagen de firma fisica');
        $user->loadMissing(['role', 'unit']);
        $user->setRelation('permissions', $user->activePermissions()->get());

        return response()->json([
            'success' => true,
            'message' => 'Firma cargada correctamente.',
            'data' => [
                'user' => new AuthenticatedUserResource($user),
            ],
        ]);
    }

    public function deleteSignatureImage(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->firma_imagen_path) {
            Storage::disk('public')->delete($user->firma_imagen_path);
        }

        $user->forceFill(['firma_imagen_path' => null])->save();
        $this->registerAudit($user, $request, 'Eliminacion de imagen de firma fisica');
        $user->loadMissing(['role', 'unit']);
        $user->setRelation('permissions', $user->activePermissions()->get());

        return response()->json([
            'success' => true,
            'message' => 'Firma eliminada correctamente.',
            'data' => [
                'user' => new AuthenticatedUserResource($user),
            ],
        ]);
    }

    private function registerAudit(?User $user, Request $request, string $process): void
    {
        $this->auditService->record($user, $request->ip(), $process);
    }
}

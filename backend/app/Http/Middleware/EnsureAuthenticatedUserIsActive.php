<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAuthenticatedUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $user->loadMissing('role');

        if (! $user->isActive() || ! $user->role?->isActive()) {
            $request->user()?->currentAccessToken()?->delete();

            return ApiResponse::error(
                'El usuario no tiene acceso habilitado.',
                [
                    'authorization' => ['El usuario o su rol no se encuentran activos.'],
                ],
                403,
            );
        }

        return $next($request);
    }
}

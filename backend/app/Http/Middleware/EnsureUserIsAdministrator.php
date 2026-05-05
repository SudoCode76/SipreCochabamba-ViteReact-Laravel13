<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdministrator
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            return ApiResponse::error('No autenticado.', null, 401);
        }

        $user->loadMissing('role');

        if (! $user->isAdministrator()) {
            return ApiResponse::error('No tiene permisos para acceder a este recurso.', [
                'authorization' => ['Solo un administrador puede realizar esta accion.'],
            ], 403);
        }

        return $next($request);
    }
}

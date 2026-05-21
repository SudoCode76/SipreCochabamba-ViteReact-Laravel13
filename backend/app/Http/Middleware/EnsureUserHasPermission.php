<?php

namespace App\Http\Middleware;

use App\Modules\Security\Services\PermissionResolverService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    public function __construct(private readonly PermissionResolverService $permissions) {}

    public function handle(Request $request, Closure $next, string $className, string $functionNames): Response
    {
        $allowedFunctions = array_filter(explode('|', $functionNames));

        if (! $this->permissions->allows($request->user(), $className, $allowedFunctions)) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene permisos para realizar esta acción.',
            ], 403);
        }

        return $next($request);
    }
}

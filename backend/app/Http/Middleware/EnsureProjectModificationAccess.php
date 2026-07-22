<?php

namespace App\Http\Middleware;

use App\Models\Project;
use App\Modules\Projects\Services\ProjectSignatureAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProjectModificationAccess
{
    public function __construct(private readonly ProjectSignatureAccessService $accessService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $project = $request->route('project');

        if ($project instanceof Project && ! $this->accessService->canModify($project, $request->user())) {
            return response()->json([
                'success' => false,
                'message' => 'El proyecto es de solo lectura para este usuario.',
                'errors' => [
                    'authorization' => ['No está incluido entre los usuarios autorizados para modificar este proyecto.'],
                ],
            ], 403);
        }

        return $next($request);
    }
}

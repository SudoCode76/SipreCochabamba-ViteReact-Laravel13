<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Item\IndexSubgroupRequest;
use App\Http\Resources\Item\SubgroupResource;
use App\Models\SubgroupCatalog;
use App\Services\Items\Fndr\FndrPermissionService;
use Illuminate\Http\JsonResponse;

class SubgroupController extends Controller
{
    public function __construct(
        private readonly FndrPermissionService $fndrPermissionService,
    ) {}

    public function index(IndexSubgroupRequest $request): JsonResponse
    {
        $permissions = $this->fndrPermissionService->resolve($request->user());

        if (! $permissions['can_view']) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene permisos para consultar subgrupos de FNDR.',
                'errors' => null,
            ], 403);
        }

        $subgroups = SubgroupCatalog::query()
            ->active()
            ->where('id_grupo', (int) $request->integer('group_id'))
            ->orderBy('descripcion')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Subgrupos obtenidos correctamente.',
            'data' => [
                'items' => SubgroupResource::collection($subgroups)->resolve(),
            ],
        ]);
    }
}

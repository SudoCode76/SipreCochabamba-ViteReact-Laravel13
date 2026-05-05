<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Item\DeleteSubgroupRequest;
use App\Http\Requests\Item\IndexSubgroupRequest;
use App\Http\Requests\Item\StoreSubgroupDeleteAuthorizationRequest;
use App\Http\Requests\Item\StoreSubgroupRequest;
use App\Http\Requests\Item\UpdateSubgroupRequest;
use App\Http\Resources\Item\SubgroupManagementResource;
use App\Http\Resources\Item\SubgroupResource;
use App\Models\GroupCatalog;
use App\Models\SubgroupCatalog;
use App\Models\User;
use App\Services\Items\SubgroupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubgroupController extends Controller
{
    public function __construct(
        private readonly SubgroupService $subgroupService,
    ) {}

    public function index(IndexSubgroupRequest $request): JsonResponse
    {
        if ($request->filled('group_id')) {
            $subgroups = $this->subgroupService->listByGroup($request->integer('group_id'));

            return response()->json([
                'success' => true,
                'message' => 'Subgrupos obtenidos correctamente.',
                'data' => [
                    'items' => SubgroupResource::collection($subgroups)->resolve(),
                ],
            ]);
        }

        if ($response = $this->ensureAdministrator($request->user())) {
            return $response;
        }

        $subgroups = $this->subgroupService->listForManagement();

        return response()->json([
            'success' => true,
            'message' => 'Subgrupos obtenidos correctamente.',
            'data' => [
                'items' => SubgroupManagementResource::collection($subgroups)->resolve(),
            ],
        ]);
    }

    public function context(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Contexto de subgrupos obtenido correctamente.',
            'data' => $this->subgroupService->context($request->user()),
        ]);
    }

    public function byGroup(GroupCatalog $group): JsonResponse
    {
        $subgroups = $this->subgroupService->listByGroup($group->id_grupo);

        return response()->json([
            'success' => true,
            'message' => 'Subgrupos obtenidos correctamente.',
            'data' => [
                'items' => SubgroupResource::collection($subgroups)->resolve(),
            ],
        ]);
    }

    public function store(StoreSubgroupRequest $request): JsonResponse
    {
        $subgroup = $this->subgroupService->create($request, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Subgrupo creado correctamente.',
            'data' => [
                'subgroup' => SubgroupManagementResource::make($subgroup)->resolve(),
            ],
        ], 201);
    }

    public function show(SubgroupCatalog $subgroup): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Subgrupo obtenido correctamente.',
            'data' => [
                'subgroup' => SubgroupManagementResource::make($this->subgroupService->show($subgroup))->resolve(),
            ],
        ]);
    }

    public function update(UpdateSubgroupRequest $request, SubgroupCatalog $subgroup): JsonResponse
    {
        $subgroup = $this->subgroupService->update($request, $subgroup, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Subgrupo actualizado correctamente.',
            'data' => [
                'subgroup' => SubgroupManagementResource::make($subgroup)->resolve(),
            ],
        ]);
    }

    public function destroy(DeleteSubgroupRequest $request, SubgroupCatalog $subgroup): JsonResponse
    {
        $subgroup = $this->subgroupService->delete($subgroup, $request, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Subgrupo eliminado logicamente correctamente.',
            'data' => [
                'subgroup' => [
                    'id_subgrupo' => $subgroup->id_subgrupo,
                    'estado' => $subgroup->estado,
                ],
            ],
        ]);
    }

    public function requestDeleteAuthorization(StoreSubgroupDeleteAuthorizationRequest $request, SubgroupCatalog $subgroup): JsonResponse
    {
        $authorization = $this->subgroupService->requestAuthorization($subgroup, $request, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Solicitud de autorizacion creada correctamente.',
            'data' => [
                'authorization' => [
                    'id_autorizacion' => $authorization->id_autorizacion,
                    'id_elemento' => $authorization->id_elemento,
                    'elemento' => $authorization->elemento,
                    'tipo_elemento' => $authorization->tipo_elemento,
                    'tabla' => $authorization->tabla,
                    'solicitante' => $authorization->solicitante,
                    'estado' => $authorization->estado,
                    'nro_autorizacion' => $authorization->nro_autorizacion,
                ],
            ],
        ], 201);
    }

    public function deleteAuthorizationStatus(SubgroupCatalog $subgroup): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Estado de autorizacion obtenido correctamente.',
            'data' => $this->subgroupService->authorizationStatus($subgroup),
        ]);
    }

    private function ensureAdministrator(?User $user): ?JsonResponse
    {
        if (! $user?->isAdministrator()) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene permisos para acceder a este recurso.',
                'errors' => [
                    'authorization' => ['Solo un administrador puede realizar esta accion.'],
                ],
            ], 403);
        }

        return null;
    }
}

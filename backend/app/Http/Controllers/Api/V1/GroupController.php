<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Item\DeleteGroupRequest;
use App\Http\Requests\Item\StoreGroupDeleteAuthorizationRequest;
use App\Http\Requests\Item\StoreGroupRequest;
use App\Http\Requests\Item\UpdateGroupRequest;
use App\Http\Resources\Item\GroupResource;
use App\Models\GroupCatalog;
use App\Modules\Items\Services\GroupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    public function __construct(
        private readonly GroupService $groupService,
    ) {}

    public function index(): JsonResponse
    {
        $items = GroupCatalog::query()
            ->where('estado', '!=', 'DP')
            ->orderBy('id_grupo')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Grupos obtenidos correctamente.',
            'data' => [
                'items' => GroupResource::collection($items)->resolve(),
            ],
        ]);
    }

    public function context(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Contexto de grupos obtenido correctamente.',
            'data' => $this->groupService->context($request->user()),
        ]);
    }

    public function store(StoreGroupRequest $request): JsonResponse
    {
        $group = $this->groupService->create($request, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Grupo creado correctamente.',
            'data' => [
                'group' => GroupResource::make($group)->resolve(),
            ],
        ], 201);
    }

    public function show(GroupCatalog $group): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Grupo obtenido correctamente.',
            'data' => [
                'group' => GroupResource::make($group)->resolve(),
            ],
        ]);
    }

    public function update(UpdateGroupRequest $request, GroupCatalog $group): JsonResponse
    {
        $group = $this->groupService->update($request, $group, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Grupo actualizado correctamente.',
            'data' => [
                'group' => GroupResource::make($group)->resolve(),
            ],
        ]);
    }

    public function destroy(DeleteGroupRequest $request, GroupCatalog $group): JsonResponse
    {
        $group = $this->groupService->delete($group, $request, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Grupo eliminado logicamente correctamente.',
            'data' => [
                'group' => [
                    'id_grupo' => $group->id_grupo,
                    'estado' => $group->estado,
                ],
            ],
        ]);
    }

    public function requestDeleteAuthorization(StoreGroupDeleteAuthorizationRequest $request, GroupCatalog $group): JsonResponse
    {
        $authorization = $this->groupService->requestAuthorization($group, $request, $request->user());

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

    public function deleteAuthorizationStatus(GroupCatalog $group): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Estado de autorizacion obtenido correctamente.',
            'data' => $this->groupService->authorizationStatus($group),
        ]);
    }
}

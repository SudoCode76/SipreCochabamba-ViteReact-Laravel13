<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\InputRequest\IndexInputSolicitationRequest;
use App\Http\Requests\InputRequest\ManageInputSolicitationRequest;
use App\Http\Requests\InputRequest\RevertInputSolicitationRequest;
use App\Http\Requests\InputRequest\StoreInputSolicitationRequest;
use App\Http\Requests\InputRequest\UpdateInputSolicitationRequest;
use App\Http\Resources\InputRequest\InputRequestQuoteResource;
use App\Http\Resources\InputRequest\InputRequestResource;
use App\Models\InputRequest;
use App\Services\InputRequests\InputRequestContextService;
use App\Services\InputRequests\InputRequestCrudService;
use App\Services\InputRequests\InputRequestListService;
use App\Services\InputRequests\InputRequestManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InputRequestController extends Controller
{
    public function __construct(
        private readonly InputRequestListService $inputRequestListService,
        private readonly InputRequestContextService $inputRequestContextService,
        private readonly InputRequestCrudService $inputRequestCrudService,
        private readonly InputRequestManagementService $inputRequestManagementService,
    ) {}

    public function index(IndexInputSolicitationRequest $request): JsonResponse
    {
        $items = $this->inputRequestListService->execute($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Solicitudes de insumo obtenidas correctamente.',
            'data' => [
                'items' => InputRequestResource::collection($items->getCollection())->resolve(),
                'meta' => [
                    'current_page' => $items->currentPage(),
                    'per_page' => $items->perPage(),
                    'total' => $items->total(),
                ],
            ],
        ]);
    }

    public function context(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Contexto de solicitudes de insumo obtenido correctamente.',
            'data' => $this->inputRequestContextService->execute($request->user()),
        ]);
    }

    public function store(StoreInputSolicitationRequest $request): JsonResponse
    {
        $item = $this->inputRequestCrudService->create($request, $request->user());
        $item->load(['type', 'unitMeasure', 'requester']);

        return response()->json([
            'success' => true,
            'message' => 'Solicitud de insumo creada correctamente.',
            'data' => [
                'request' => InputRequestResource::make($item)->resolve(),
            ],
        ], 201);
    }

    public function show(InputRequest $inputRequest): JsonResponse
    {
        $inputRequest->load(['type', 'unitMeasure', 'requester']);

        return response()->json([
            'success' => true,
            'message' => 'Solicitud de insumo obtenida correctamente.',
            'data' => [
                'request' => InputRequestResource::make($inputRequest)->resolve(),
            ],
        ]);
    }

    public function update(UpdateInputSolicitationRequest $request, InputRequest $inputRequest): JsonResponse
    {
        $inputRequest = $this->inputRequestCrudService->update($request, $inputRequest, $request->user());
        $inputRequest->load(['type', 'unitMeasure', 'requester']);

        return response()->json([
            'success' => true,
            'message' => 'Solicitud de insumo actualizada correctamente.',
            'data' => [
                'request' => InputRequestResource::make($inputRequest)->resolve(),
            ],
        ]);
    }

    public function quotesHistory(InputRequest $inputRequest): JsonResponse
    {
        $quotes = $inputRequest->quotes()
            ->orderByDesc('fecha')
            ->orderByDesc('condicion')
            ->orderByDesc('id_cotizacion')
            ->orderBy('id_log_insumo')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Historico de cotizaciones de la solicitud obtenido correctamente.',
            'data' => [
                'items' => InputRequestQuoteResource::collection($quotes)->resolve(),
            ],
        ]);
    }

    public function quoteSummary(InputRequest $inputRequest): JsonResponse
    {
        $lastQuote = $inputRequest->quotes()
            ->orderByDesc('fecha')
            ->orderByDesc('id_cotizacion')
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'Resumen de cotizacion de la solicitud obtenido correctamente.',
            'data' => [
                'id_solicitud' => $inputRequest->id_solicitud,
                'descripcion' => $inputRequest->descripcion,
                'archivo' => $inputRequest->archivo,
                'archivo1' => $inputRequest->archivo1,
                'archivo2' => $inputRequest->archivo2,
                'id_log' => $lastQuote?->id_log_insumo,
                'fecha' => $inputRequest->fecha?->toDateString(),
            ],
        ]);
    }

    public function managementIndex(IndexInputSolicitationRequest $request): JsonResponse
    {
        $items = $this->inputRequestListService->executeManagement($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Solicitudes de insumo para gestion obtenidas correctamente.',
            'data' => [
                'items' => InputRequestResource::collection($items->getCollection())->resolve(),
                'meta' => [
                    'current_page' => $items->currentPage(),
                    'per_page' => $items->perPage(),
                    'total' => $items->total(),
                    'from' => $items->firstItem(),
                    'to' => $items->lastItem(),
                    'last_page' => $items->lastPage(),
                    'has_more_pages' => $items->hasMorePages(),
                ],
            ],
        ]);
    }

    public function managementShow(InputRequest $inputRequest): JsonResponse
    {
        return $this->show($inputRequest);
    }

    public function manage(ManageInputSolicitationRequest $request, InputRequest $inputRequest): JsonResponse
    {
        $inputRequest = $this->inputRequestManagementService->manage($request, $inputRequest, $request->user());
        $inputRequest->load(['type', 'unitMeasure', 'requester']);

        return response()->json([
            'success' => true,
            'message' => strtoupper((string) $inputRequest->estado_aprobacion) === 'AP'
                ? 'Solicitud aprobada correctamente.'
                : 'Solicitud rechazada correctamente.',
            'data' => [
                'request' => InputRequestResource::make($inputRequest)->resolve(),
            ],
        ]);
    }

    public function revert(RevertInputSolicitationRequest $request, InputRequest $inputRequest): JsonResponse
    {
        $inputRequest = $this->inputRequestManagementService->revert($request, $inputRequest, $request->user());
        $inputRequest->load(['type', 'unitMeasure', 'requester']);

        return response()->json([
            'success' => true,
            'message' => 'Solicitud revertida correctamente.',
            'data' => [
                'request' => InputRequestResource::make($inputRequest)->resolve(),
            ],
        ]);
    }
}

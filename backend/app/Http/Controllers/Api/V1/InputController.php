<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Input\DeleteInputRequest;
use App\Http\Requests\Input\IndexInputRequest;
use App\Http\Requests\Input\StoreInputDeleteAuthorizationRequest;
use App\Http\Requests\Input\StoreInputQuoteRequest;
use App\Http\Requests\Input\StoreInputRequest;
use App\Http\Requests\Input\UpdateInputRequest;
use App\Http\Requests\Input\UpdateInputStatusRequest;
use App\Http\Resources\Input\InputHistoryResource;
use App\Http\Resources\Input\InputLogResource;
use App\Http\Resources\Input\InputQuoteResource;
use App\Http\Resources\Input\InputResource;
use App\Models\Input;
use App\Models\InputLog;
use App\Services\Inputs\InputContextService;
use App\Services\Inputs\InputCrudService;
use App\Services\Inputs\InputDeletionService;
use App\Services\Inputs\InputListService;
use App\Services\Inputs\InputQuoteService;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InputController extends Controller
{
    public function __construct(
        private readonly InputListService $inputListService,
        private readonly InputContextService $inputContextService,
        private readonly InputCrudService $inputCrudService,
        private readonly InputDeletionService $inputDeletionService,
        private readonly InputQuoteService $inputQuoteService,
        private readonly AuditService $auditService,
    ) {}

    public function index(IndexInputRequest $request): JsonResponse
    {
        $inputs = $this->inputListService->execute($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Insumos obtenidos correctamente.',
            'data' => [
                'items' => InputResource::collection($inputs->getCollection())->resolve(),
                'meta' => [
                    'current_page' => $inputs->currentPage(),
                    'per_page' => $inputs->perPage(),
                    'total' => $inputs->total(),
                ],
            ],
        ]);
    }

    public function context(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Contexto de insumos obtenido correctamente.',
            'data' => $this->inputContextService->execute($request->user()),
        ]);
    }

    public function store(StoreInputRequest $request): JsonResponse
    {
        $input = $this->inputCrudService->create($request, $request->user());
        $input->load(['type', 'unitMeasure', 'creator']);

        return response()->json([
            'success' => true,
            'message' => 'Insumo creado correctamente.',
            'data' => [
                'input' => InputResource::make($input)->resolve(),
            ],
        ], 201);
    }

    public function show(Input $input): JsonResponse
    {
        $input->load(['type', 'unitMeasure', 'creator']);

        return response()->json([
            'success' => true,
            'message' => 'Insumo obtenido correctamente.',
            'data' => [
                'input' => InputResource::make($input)->resolve(),
            ],
        ]);
    }

    public function name(Input $input): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Nombre del insumo obtenido correctamente.',
            'data' => [
                'id_insumo' => $input->id_insumo,
                'descripcion' => $input->descripcion,
            ],
        ]);
    }

    public function update(UpdateInputRequest $request, Input $input): JsonResponse
    {
        $input = $this->inputCrudService->update($request, $input, $request->user());
        $input->load(['type', 'unitMeasure', 'creator']);

        return response()->json([
            'success' => true,
            'message' => 'Insumo actualizado correctamente.',
            'data' => [
                'input' => InputResource::make($input)->resolve(),
            ],
        ]);
    }

    public function updateStatus(UpdateInputStatusRequest $request, Input $input): JsonResponse
    {
        $input = $this->inputCrudService->updateStatus(
            $input,
            $request->user(),
            $request->string('status')->toString(),
            $request->ip(),
        );

        $input->load(['type', 'unitMeasure', 'creator']);

        return response()->json([
            'success' => true,
            'message' => 'Estado del insumo actualizado correctamente.',
            'data' => [
                'input' => InputResource::make($input)->resolve(),
            ],
        ]);
    }

    public function destroy(DeleteInputRequest $request, Input $input): JsonResponse
    {
        $input = $this->inputDeletionService->delete($input, $request, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Insumo eliminado logicamente correctamente.',
            'data' => [
                'input' => [
                    'id_insumo' => $input->id_insumo,
                    'estado' => $input->estado,
                ],
            ],
        ]);
    }

    public function requestDeleteAuthorization(StoreInputDeleteAuthorizationRequest $request, Input $input): JsonResponse
    {
        $authorization = $this->inputDeletionService->requestAuthorization($input, $request, $request->user());

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

    public function deleteAuthorizationStatus(Input $input): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Estado de autorizacion obtenido correctamente.',
            'data' => $this->inputDeletionService->authorizationStatus($input),
        ]);
    }

    public function deleteImpact(Input $input): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Impacto de eliminacion obtenido correctamente.',
            'data' => $this->inputDeletionService->deleteImpact($input),
        ]);
    }

    public function history(Input $input): JsonResponse
    {
        $history = $input->histories()
            ->with(['user', 'type', 'unitMeasure'])
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Historico del insumo obtenido correctamente.',
            'data' => [
                'input_id' => $input->id_insumo,
                'items' => InputHistoryResource::collection($history)->resolve(),
            ],
        ]);
    }

    public function logs(Input $input): JsonResponse
    {
        $logs = $input->logs()
            ->with(['user', 'type', 'unitMeasure'])
            ->orderByDesc('id_log')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Logs del insumo obtenidos correctamente.',
            'data' => [
                'input_id' => $input->id_insumo,
                'items' => InputLogResource::collection($logs)->resolve(),
            ],
        ]);
    }

    public function quotes(Input $input): JsonResponse
    {
        $quotes = $this->inputQuoteService->history($input);

        return response()->json([
            'success' => true,
            'message' => 'Cotizaciones del insumo obtenidas correctamente.',
            'data' => [
                'input_id' => $input->id_insumo,
                'items' => InputQuoteResource::collection($quotes)->resolve(),
            ],
        ]);
    }

    public function storeQuote(StoreInputQuoteRequest $request, Input $input): JsonResponse
    {
        $quote = $this->inputQuoteService->create($input, $request);
        $quote->load(['input', 'log']);
        $this->auditService->record($request->user(), $request->ip(), 'INSUMOS: se registro una cotizacion para '.$input->descripcion);

        return response()->json([
            'success' => true,
            'message' => 'Cotizacion registrada correctamente.',
            'data' => [
                'quote' => InputQuoteResource::make($quote)->resolve(),
            ],
        ], 201);
    }

    public function currentQuote(Input $input): JsonResponse
    {
        $quote = $this->inputQuoteService->current($input);

        return response()->json([
            'success' => true,
            'message' => 'Cotizacion vigente del insumo obtenida correctamente.',
            'data' => [
                'quote' => $quote ? InputQuoteResource::make($quote)->resolve() : null,
            ],
        ]);
    }

    public function quoteHistory(Input $input): JsonResponse
    {
        $quotes = $this->inputQuoteService->history($input);

        return response()->json([
            'success' => true,
            'message' => 'Historico completo de cotizaciones obtenido correctamente.',
            'data' => [
                'items' => InputQuoteResource::collection($quotes)->resolve(),
            ],
        ]);
    }

    public function quoteLogHistory(Input $input): JsonResponse
    {
        $quotes = $this->inputQuoteService->logHistory($input);

        return response()->json([
            'success' => true,
            'message' => 'Historico de cotizaciones por log obtenido correctamente.',
            'data' => [
                'items' => InputQuoteResource::collection($quotes)->resolve(),
            ],
        ]);
    }

    public function logFiles(InputLog $log): JsonResponse
    {
        $quotes = $this->inputQuoteService->filesByLog($log->id_log);

        return response()->json([
            'success' => true,
            'message' => 'Archivos de cotizacion obtenidos correctamente.',
            'data' => [
                'id_log' => $log->id_log,
                'items' => InputQuoteResource::collection($quotes)->resolve(),
            ],
        ]);
    }
}

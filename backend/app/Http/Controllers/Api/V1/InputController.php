<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Input\IndexInputRequest;
use App\Http\Requests\Input\StoreInputQuoteRequest;
use App\Http\Requests\Input\StoreInputRequest;
use App\Http\Requests\Input\UpdateInputRequest;
use App\Http\Requests\Input\UpdateInputStatusRequest;
use App\Http\Resources\Input\InputHistoryResource;
use App\Http\Resources\Input\InputLogResource;
use App\Http\Resources\Input\InputQuoteResource;
use App\Http\Resources\Input\InputResource;
use App\Models\Input;
use App\Models\InputHistory;
use App\Models\InputLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InputController extends Controller
{
    public function index(IndexInputRequest $request): JsonResponse
    {
        $query = Input::query()
            ->with(['type', 'unitMeasure', 'creator'])
            ->orderByDesc('id_insumo');

        if ($request->filled('description')) {
            $description = Str::lower(trim($request->string('description')->toString()));
            $query->whereRaw('LOWER(TRIM(descripcion)) LIKE ?', ["%{$description}%"]);
        }

        if ($request->filled('type_id')) {
            $query->where('tipo', (int) $request->integer('type_id'));
        }

        if ($request->filled('unit_measure_id')) {
            $query->where('unidad_medida', (int) $request->integer('unit_measure_id'));
        }

        if ($request->filled('status')) {
            $query->where('estado', strtoupper($request->string('status')->toString()));
        }

        if ($request->filled('quote_date')) {
            $query->whereDate('fecha_cotiz', $request->date('quote_date'));
        }

        $inputs = $query->paginate($request->integer('per_page', 15))->withQueryString();

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

    public function store(StoreInputRequest $request): JsonResponse
    {
        $input = DB::transaction(function () use ($request): Input {
            $input = Input::query()->create($this->inputPayload($request, $request->user()));

            $this->registerLog($input, $request->user(), 'RG');

            return $input;
        });

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

    public function update(UpdateInputRequest $request, Input $input): JsonResponse
    {
        DB::transaction(function () use ($request, $input): void {
            $input->update($this->inputPayload($request, $request->user(), true));

            $this->registerLog($input, $request->user(), 'MD');
            $this->registerHistory($input, $request->user(), $request->ip(), 'MODIFICADO');
        });

        $input->refresh()->load(['type', 'unitMeasure', 'creator']);

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
        DB::transaction(function () use ($request, $input): void {
            $input->update([
                'estado' => strtoupper($request->string('status')->toString()),
            ]);

            $this->registerLog($input, $request->user(), 'MD');
            $this->registerHistory($input, $request->user(), $request->ip(), 'MODIFICADO');
        });

        $input->refresh()->load(['type', 'unitMeasure', 'creator']);

        return response()->json([
            'success' => true,
            'message' => 'Estado del insumo actualizado correctamente.',
            'data' => [
                'input' => InputResource::make($input)->resolve(),
            ],
        ]);
    }

    public function history(Input $input): JsonResponse
    {
        $history = $input->histories()
            ->with('user')
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
            ->with('user')
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
        $quotes = $input->quotes()
            ->orderByDesc('id_cotizacion')
            ->get();

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
        $quote = $input->quotes()->create([
            'condicion' => $request->filled('condition') ? trim($request->string('condition')->toString()) : null,
            'estado' => $request->filled('status') ? strtoupper($request->string('status')->toString()) : 'AC',
            'id_log_insumo' => $request->filled('log_id')
                ? (int) $request->integer('log_id')
                : $input->logs()->latest('id_log')->value('id_log'),
            'archivo' => $request->filled('file') ? trim($request->string('file')->toString()) : null,
            'fecha' => $request->filled('date') ? $request->date('date')->toDateString() : now()->toDateString(),
            'archivo1' => $request->filled('file_1') ? trim($request->string('file_1')->toString()) : null,
            'archivo2' => $request->filled('file_2') ? trim($request->string('file_2')->toString()) : null,
            'id_solicitud' => $request->filled('request_id') ? (int) $request->integer('request_id') : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Cotizacion registrada correctamente.',
            'data' => [
                'quote' => InputQuoteResource::make($quote)->resolve(),
            ],
        ], 201);
    }

    private function inputPayload(StoreInputRequest|UpdateInputRequest $request, ?User $user, bool $includeRequiredStatus = false): array
    {
        $status = $request->filled('status')
            ? strtoupper($request->string('status')->toString())
            : 'AC';

        return [
            'descripcion' => trim($request->string('description')->toString()),
            'unidad_medida' => (int) $request->integer('unit_measure_id'),
            'precio' => $request->input('price'),
            'tipo' => (int) $request->integer('type_id'),
            'estado' => $includeRequiredStatus ? strtoupper($request->string('status')->toString()) : $status,
            'usuario' => $user?->id_usuario,
            'fecha' => now()->toDateString(),
            'solicitud' => $request->filled('request_id') ? (int) $request->integer('request_id') : null,
            'cod' => $request->filled('code') ? trim($request->string('code')->toString()) : null,
            'fecha_cotiz' => $request->filled('quote_date') ? $request->date('quote_date')->toDateString() : null,
            'observacion' => $request->filled('observation') ? trim($request->string('observation')->toString()) : null,
        ];
    }

    private function registerLog(Input $input, ?User $user, string $action): void
    {
        InputLog::query()->create([
            'descripcion' => $input->descripcion,
            'id_insumo' => $input->id_insumo,
            'precio' => $input->precio,
            'tipo' => $input->tipo,
            'unidad_medida' => $input->unidad_medida,
            'accion' => $action,
            'usuario' => $user?->id_usuario,
            'fecha' => now()->toDateString(),
            'estado' => $input->estado,
        ]);
    }

    private function registerHistory(Input $input, ?User $user, ?string $ip, string $action): void
    {
        InputHistory::query()->create([
            'descripcion' => $input->descripcion,
            'id_insumo' => $input->id_insumo,
            'precio' => $input->precio,
            'tipo' => $input->tipo,
            'unidad_medida' => $input->unidad_medida,
            'accion' => $action,
            'usuario' => $user?->id_usuario,
            'fecha' => now(),
            'estado' => $input->estado,
            'ip' => $ip,
            'nombre_usuario' => $user?->funcionario,
        ]);
    }
}

<?php

namespace App\Services\Inputs;

use App\Http\Requests\Input\StoreInputRequest;
use App\Http\Requests\Input\UpdateInputRequest;
use App\Http\Requests\Input\UpdateInputPriceRequest;
use App\Models\Input;
use App\Models\InputHistory;
use App\Models\InputLog;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InputCrudService
{
    public function __construct(
        private readonly InputQuoteService $inputQuoteService,
    ) {}

    public function create(StoreInputRequest $request, User $user): Input
    {
        return DB::transaction(function () use ($request, $user): Input {
            $description = trim($request->string('description')->toString());
            $this->ensureDescriptionIsUnique($description);

            $input = Input::query()->create($this->inputPayload($request, $user));

            $log = $this->registerLog($input, $user, 'RG');
            $this->registerHistory($input, $user, $request->ip(), 'REGISTRADOR', $log);
            $this->registerAudit($user, $request->ip(), 'Registro de insumo '.$input->descripcion);

            return $input;
        });
    }

    public function update(UpdateInputRequest $request, Input $input, User $user): Input
    {
        return DB::transaction(function () use ($request, $input, $user): Input {
            if ($this->priceChanged($input, $request->input('price'))) {
                throw ValidationException::withMessages([
                    'precio' => ['Para cambiar el precio debe adjuntar o seleccionar cotizaciones de respaldo.'],
                ]);
            }

            $description = trim($request->string('description')->toString());

            if (strcasecmp(trim((string) $input->descripcion), $description) !== 0) {
                $this->ensureDescriptionIsUnique($description, $input->id_insumo);
            }

            $input->update($this->inputPayload($request, $user, true));

            $log = $this->registerLog($input, $user, 'MD');
            $this->registerHistory($input, $user, $request->ip(), 'MODIFICADO', $log);
            $this->registerAudit($user, $request->ip(), 'Actualizacion de insumo '.$input->descripcion);

            return $input->refresh();
        });
    }

    public function updatePrice(UpdateInputPriceRequest $request, Input $input, User $user): Input
    {
        return DB::transaction(function () use ($request, $input, $user): Input {
            if (! $this->priceChanged($input, $request->input('price'))) {
                return $this->update($request, $input, $user);
            }

            $quoteIds = $request->input('quote_ids', []);
            $hasSelectedQuotes = is_array($quoteIds) && count(array_filter($quoteIds)) > 0;
            $hasUploadedQuotes = $this->inputQuoteService->hasUploadedFiles($request);

            if (! $hasSelectedQuotes && ! $hasUploadedQuotes) {
                throw ValidationException::withMessages([
                    'quotes' => ['Selecciona una cotizacion libre o adjunta al menos un archivo PDF para justificar el cambio de precio.'],
                ]);
            }

            $description = trim($request->string('description')->toString());

            if (strcasecmp(trim((string) $input->descripcion), $description) !== 0) {
                $this->ensureDescriptionIsUnique($description, $input->id_insumo);
            }

            $input->update($this->inputPayload($request, $user, true));

            $log = $this->registerLog($input, $user, 'MD');
            $this->inputQuoteService->attachUnassignedToLog($input, is_array($quoteIds) ? $quoteIds : [], $log->id_log);
            $this->inputQuoteService->createForLog($input, $request, $log->id_log);
            $this->registerHistory($input, $user, $request->ip(), 'MODIFICADO', $log);
            $this->registerAudit($user, $request->ip(), 'Actualizacion de precio de insumo '.$input->descripcion);

            return $input->refresh();
        });
    }

    public function updateStatus(Input $input, User $user, string $status, ?string $ip): Input
    {
        return DB::transaction(function () use ($input, $user, $status, $ip): Input {
            $input->update([
                'estado' => strtoupper($status),
            ]);

            $log = $this->registerLog($input, $user, 'MD');
            $this->registerHistory($input, $user, $ip, 'MODIFICADO', $log);
            $this->registerAudit($user, $ip, 'Cambio de estado de insumo '.$input->descripcion);

            return $input->refresh();
        });
    }

    private function inputPayload(StoreInputRequest|UpdateInputRequest $request, User $user, bool $statusIsRequired = false): array
    {
        return [
            'descripcion' => trim($request->string('description')->toString()),
            'unidad_medida' => (int) $request->integer('unit_measure_id'),
            'precio' => $request->input('price'),
            'tipo' => (int) $request->integer('type_id'),
            'id_categoria' => $request->filled('category_id') ? (int) $request->integer('category_id') : null,
            'estado' => $statusIsRequired ? strtoupper($request->string('status')->toString()) : strtoupper((string) $request->input('status', 'AC')),
            'usuario' => $user->id_usuario,
            'fecha' => $request->filled('date') ? $request->date('date')->toDateString() : now()->toDateString(),
            'solicitud' => $request->filled('request_id') ? (int) $request->integer('request_id') : null,
            'cod' => $request->filled('code') ? trim($request->string('code')->toString()) : null,
            'fecha_cotiz' => $request->date('quote_date')->toDateString(),
            'observacion' => $request->filled('observation') ? trim($request->string('observation')->toString()) : null,
        ];
    }

    private function ensureDescriptionIsUnique(string $description, ?int $ignoredId = null): void
    {
        $query = Input::query()
            ->where('estado', '!=', 'DP')
            ->whereRaw('UPPER(TRIM(descripcion)) = ?', [strtoupper(trim($description))]);

        if ($ignoredId !== null) {
            $query->where('id_insumo', '!=', $ignoredId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'descripcion' => ['Ya existe otro insumo con la misma descripcion.'],
            ]);
        }
    }

    private function priceChanged(Input $input, mixed $price): bool
    {
        return round((float) $input->precio, 2) !== round((float) $price, 2);
    }

    private function registerLog(Input $input, User $user, string $action): InputLog
    {
        return InputLog::query()->create([
            'descripcion' => $input->descripcion,
            'id_insumo' => $input->id_insumo,
            'precio' => $input->precio,
            'tipo' => $input->tipo,
            'id_categoria' => $input->id_categoria,
            'unidad_medida' => $input->unidad_medida,
            'accion' => $action,
            'usuario' => $user->id_usuario,
            'fecha' => $input->fecha?->toDateString() ?? now()->toDateString(),
            'estado' => $input->estado,
        ]);
    }

    private function registerHistory(Input $input, User $user, ?string $ip, string $action, ?InputLog $log = null): InputHistory
    {
        return InputHistory::query()->create([
            'descripcion' => $input->descripcion,
            'id_insumo' => $input->id_insumo,
            'id_log_insumo' => $log?->id_log,
            'precio' => $input->precio,
            'tipo' => $input->tipo,
            'id_categoria' => $input->id_categoria,
            'unidad_medida' => $input->unidad_medida,
            'accion' => $action,
            'usuario' => $user->id_usuario,
            'fecha' => now(),
            'estado' => $input->estado,
            'ip' => $ip,
            'nombre_usuario' => $user->funcionario,
        ]);
    }

    private function registerAudit(User $user, ?string $ip, string $process): void
    {
        app(AuditService::class)->record($user, $ip, $process);
    }
}

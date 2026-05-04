<?php

namespace App\Services\Inputs;

use App\Http\Requests\Input\StoreInputRequest;
use App\Http\Requests\Input\UpdateInputRequest;
use App\Models\AuditLog;
use App\Models\Input;
use App\Models\InputHistory;
use App\Models\InputLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InputCrudService
{
    public function create(StoreInputRequest $request, User $user): Input
    {
        return DB::transaction(function () use ($request, $user): Input {
            $description = trim($request->string('description')->toString());
            $this->ensureDescriptionIsUnique($description);

            $input = Input::query()->create($this->inputPayload($request, $user));

            $this->registerLog($input, $user, 'RG');
            $this->registerHistory($input, $user, $request->ip(), 'REGISTRADOR');
            $this->registerAudit($user, $request->ip(), 'Registro de insumo '.$input->descripcion);

            return $input;
        });
    }

    public function update(UpdateInputRequest $request, Input $input, User $user): Input
    {
        return DB::transaction(function () use ($request, $input, $user): Input {
            $description = trim($request->string('description')->toString());

            if (strcasecmp(trim((string) $input->descripcion), $description) !== 0) {
                $this->ensureDescriptionIsUnique($description, $input->id_insumo);
            }

            $input->update($this->inputPayload($request, $user, true));

            $this->registerLog($input, $user, 'MD');
            $this->registerHistory($input, $user, $request->ip(), 'MODIFICADO');
            $this->registerAudit($user, $request->ip(), 'Actualizacion de insumo '.$input->descripcion);

            return $input->refresh();
        });
    }

    public function updateStatus(Input $input, User $user, string $status, ?string $ip): Input
    {
        return DB::transaction(function () use ($input, $user, $status, $ip): Input {
            $input->update([
                'estado' => strtoupper($status),
            ]);

            $this->registerLog($input, $user, 'MD');
            $this->registerHistory($input, $user, $ip, 'MODIFICADO');
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

    private function registerLog(Input $input, User $user, string $action): InputLog
    {
        return InputLog::query()->create([
            'descripcion' => $input->descripcion,
            'id_insumo' => $input->id_insumo,
            'precio' => $input->precio,
            'tipo' => $input->tipo,
            'unidad_medida' => $input->unidad_medida,
            'accion' => $action,
            'usuario' => $user->id_usuario,
            'fecha' => $input->fecha?->toDateString() ?? now()->toDateString(),
            'estado' => $input->estado,
        ]);
    }

    private function registerHistory(Input $input, User $user, ?string $ip, string $action): InputHistory
    {
        return InputHistory::query()->create([
            'descripcion' => $input->descripcion,
            'id_insumo' => $input->id_insumo,
            'precio' => $input->precio,
            'tipo' => $input->tipo,
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
        try {
            AuditLog::query()->create([
                'nombre_completo' => $user->funcionario,
                'fecha_hora' => now(),
                'ip' => $ip,
                'proceso' => $process,
            ]);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}

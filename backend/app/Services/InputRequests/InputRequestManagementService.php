<?php

namespace App\Services\InputRequests;

use App\Http\Requests\InputRequest\ManageInputSolicitationRequest;
use App\Http\Requests\InputRequest\RevertInputSolicitationRequest;
use App\Models\Input;
use App\Models\InputLog;
use App\Models\InputRequest;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class InputRequestManagementService
{
    public function manage(ManageInputSolicitationRequest $request, InputRequest $inputRequest, User $user): InputRequest
    {
        return DB::transaction(function () use ($request, $inputRequest, $user): InputRequest {
            if (strtoupper((string) $inputRequest->estado_aprobacion) !== 'PD') {
                throw ValidationException::withMessages([
                    'request' => ['Solo se pueden gestionar solicitudes pendientes.'],
                ]);
            }

            $approvalStatus = strtoupper($request->string('approval_status')->toString());
            $approvalDate = $request->date('approval_date')->toDateString();
            $approvalUserId = (int) $request->integer('approval_user_id');

            $this->applyManagementPayload($inputRequest, [
                'precio' => $request->input('price'),
                'unidad_medida' => (int) $request->integer('unit_measure_id'),
                'ubicacion' => $request->filled('location') ? strtoupper(trim($request->string('location')->toString())) : $inputRequest->ubicacion,
                'justificacion' => strtoupper(trim($request->string('justification')->toString())),
                'notificacion' => trim($request->string('notification')->toString()),
                'estado_aprobacion' => $approvalStatus,
                'usuario_aprobacion' => $approvalUserId,
                'fecha_aprobacion' => $approvalDate,
                ...$this->modificationTimestampPayload(),
            ]);

            if ($approvalStatus === 'AP') {
                $this->ensureNoActiveInputWithSameDescription((string) $inputRequest->descripcion);

                $input = Input::query()->create([
                    'descripcion' => trim((string) $inputRequest->descripcion),
                    'precio' => $request->input('price'),
                    'unidad_medida' => (int) $request->integer('unit_measure_id'),
                    'tipo' => $inputRequest->tipo,
                    'estado' => 'AC',
                    'fecha' => $approvalDate,
                    'fecha_cotiz' => $approvalDate,
                    'usuario' => $approvalUserId,
                    'solicitud' => $inputRequest->id_solicitud,
                    'observacion' => trim($request->string('notification')->toString()),
                ]);

                InputLog::query()->create([
                    'descripcion' => $input->descripcion,
                    'id_insumo' => $input->id_insumo,
                    'precio' => $input->precio,
                    'tipo' => $input->tipo,
                    'unidad_medida' => $input->unidad_medida,
                    'accion' => 'RG',
                    'usuario' => $approvalUserId,
                    'fecha' => $approvalDate,
                    'estado' => 'AC',
                ]);
            }

            $this->registerAudit($user, $request->ip(), 'Gestion de solicitud de insumo '.$inputRequest->descripcion);

            return $inputRequest->refresh();
        });
    }

    public function revert(RevertInputSolicitationRequest $request, InputRequest $inputRequest, User $user): InputRequest
    {
        return DB::transaction(function () use ($request, $inputRequest, $user): InputRequest {
            $currentStatus = strtoupper((string) $inputRequest->estado_aprobacion);

            if (! in_array($currentStatus, ['AP', 'RC'], true)) {
                throw ValidationException::withMessages([
                    'request' => ['Solo se pueden revertir solicitudes aprobadas o rechazadas.'],
                ]);
            }

            $revertDate = $request->date('revert_date')->toDateString();
            $revertUserId = (int) $request->integer('revert_user_id');
            $observation = trim($request->string('observation')->toString());

            if ($currentStatus === 'AP') {
                $input = Input::query()
                    ->where('solicitud', $inputRequest->id_solicitud)
                    ->where('estado', 'AC')
                    ->first();

                if ($input) {
                    $input->update([
                        'estado' => 'DC',
                    ]);

                    InputLog::query()->create([
                        'descripcion' => $input->descripcion,
                        'id_insumo' => $input->id_insumo,
                        'precio' => $input->precio,
                        'tipo' => $input->tipo,
                        'unidad_medida' => $input->unidad_medida,
                        'accion' => 'RV',
                        'usuario' => $revertUserId,
                        'fecha' => $revertDate,
                        'estado' => 'DC',
                    ]);
                }
            }

            $payload = [
                'estado_aprobacion' => 'PD',
                ...$this->modificationTimestampPayload(),
            ];

            if (Schema::hasColumn($inputRequest->getTable(), 'observacion')) {
                $payload['observacion'] = $observation;
            }

            if ($currentStatus === 'AP') {
                $payload['notificacion'] = '';
            }

            $inputRequest->update($payload);

            $this->registerAudit($user, $request->ip(), 'Reversion de solicitud de insumo '.$inputRequest->descripcion);

            return $inputRequest->refresh();
        });
    }

    private function ensureNoActiveInputWithSameDescription(string $description): void
    {
        $exists = Input::query()
            ->where('estado', 'AC')
            ->whereRaw('UPPER(TRIM(descripcion)) = ?', [strtoupper(trim($description))])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'descripcion' => ['Ya existe un insumo activo con la misma descripcion.'],
            ]);
        }
    }

    private function applyManagementPayload(InputRequest $inputRequest, array $payload): void
    {
        if (! Schema::hasColumn($inputRequest->getTable(), 'usuario_aprobacion')) {
            unset($payload['usuario_aprobacion']);
        }

        if (! Schema::hasColumn($inputRequest->getTable(), 'fecha_aprobacion')) {
            unset($payload['fecha_aprobacion']);
        }

        $inputRequest->update($payload);
    }

    private function registerAudit(User $user, ?string $ip, string $process): void
    {
        app(AuditService::class)->record($user, $ip, $process);
    }

    private function modificationTimestampPayload(): array
    {
        $column = Schema::hasColumn('solicitud_insumo', 'ultima_modificacion')
            ? 'ultima_modificacion'
            : 'fecha_modificacion';

        return [
            $column => now(),
        ];
    }
}

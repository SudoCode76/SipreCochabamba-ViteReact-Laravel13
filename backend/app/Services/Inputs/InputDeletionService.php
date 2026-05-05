<?php

namespace App\Services\Inputs;

use App\Http\Requests\Input\DeleteInputRequest;
use App\Http\Requests\Input\StoreInputDeleteAuthorizationRequest;
use App\Models\Authorization;
use App\Models\Input;
use App\Models\InputLog;
use App\Models\ItemInput;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InputDeletionService
{
    public function requestAuthorization(Input $input, StoreInputDeleteAuthorizationRequest $request, User $user): Authorization
    {
        return Authorization::query()->create([
            'id_elemento' => $input->id_insumo,
            'elemento' => $input->descripcion,
            'tipo_elemento' => 'insumo',
            'tabla' => 'insumo',
            'solicitante' => $user->id_usuario,
            'estado' => 'PE',
            'nro_autorizacion' => $request->filled('nro_autorizacion') ? trim($request->string('nro_autorizacion')->toString()) : null,
            'fecha' => now(),
        ]);
    }

    public function authorizationStatus(Input $input): array
    {
        $authorization = Authorization::query()
            ->where('tabla', 'insumo')
            ->where('id_elemento', $input->id_insumo)
            ->orderByDesc('id_autorizacion')
            ->first();

        if (! $authorization) {
            return [
                'status' => 'not_found',
                'usable' => false,
                'authorization' => null,
            ];
        }

        return [
            'status' => match ($authorization->estado) {
                'AP' => 'approved',
                'PE' => 'pending',
                default => 'not_usable',
            },
            'usable' => $authorization->estado === 'AP',
            'authorization' => [
                'id_autorizacion' => $authorization->id_autorizacion,
                'nro_autorizacion' => $authorization->nro_autorizacion,
                'estado' => $authorization->estado,
            ],
        ];
    }

    public function delete(Input $input, DeleteInputRequest $request, User $user): Input
    {
        return DB::transaction(function () use ($input, $request, $user): Input {
            $this->ensureInputCanBeDeleted($input, $request->string('autorizacion')->toString());

            $input->update([
                'estado' => 'DP',
            ]);

            InputLog::query()->create([
                'descripcion' => $input->descripcion,
                'id_insumo' => $input->id_insumo,
                'precio' => $input->precio,
                'tipo' => $input->tipo,
                'unidad_medida' => $input->unidad_medida,
                'accion' => 'MD',
                'usuario' => $user->id_usuario,
                'fecha' => now()->toDateString(),
                'estado' => 'DP',
            ]);

            $this->registerAudit($user, $request->ip(), 'Eliminacion logica de insumo '.$input->descripcion);

            return $input->refresh();
        });
    }

    private function ensureInputCanBeDeleted(Input $input, string $authorizationCode): void
    {
        $hasActiveItemUsage = ItemInput::query()
            ->where('id_insumo', $input->id_insumo)
            ->where('estado', 'AC')
            ->exists();

        if ($hasActiveItemUsage) {
            throw ValidationException::withMessages([
                'input' => ['El insumo no puede eliminarse porque esta asociado a items activos.'],
            ]);
        }

        $authorizationExists = Authorization::query()
            ->where('id_elemento', $input->id_insumo)
            ->where('nro_autorizacion', trim($authorizationCode))
            ->where('tabla', 'insumo')
            ->where('estado', 'AP')
            ->exists();

        if (! $authorizationExists) {
            throw ValidationException::withMessages([
                'autorizacion' => ['No existe una autorizacion aprobada valida para eliminar este insumo.'],
            ]);
        }
    }

    private function registerAudit(User $user, ?string $ip, string $process): void
    {
        app(AuditService::class)->record($user, $ip, $process);
    }
}

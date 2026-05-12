<?php

namespace App\Services\InputRequests;

use App\Http\Requests\InputRequest\StoreInputSolicitationRequest;
use App\Http\Requests\InputRequest\UpdateInputSolicitationRequest;
use App\Models\InputQuote;
use App\Models\InputRequest;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InputRequestCrudService
{
    public function __construct(
        private readonly InputRequestFileService $fileService,
    ) {}

    public function create(StoreInputSolicitationRequest $request, User $user): InputRequest
    {
        return DB::transaction(function () use ($request, $user): InputRequest {
            $files = $this->storeFiles($request);

            $inputRequest = InputRequest::query()->create([
                'descripcion' => strtoupper(trim($request->string('description')->toString())),
                'precio' => $request->input('price'),
                'unidad_medida' => (int) $request->integer('unit_measure_id'),
                'tipo' => (int) $request->integer('type_id'),
                'ubicacion' => strtoupper(trim($request->string('location')->toString())),
                'justificacion' => strtoupper(trim($request->string('justification')->toString())),
                'usuario_solicitante' => (int) $request->integer('requester_id'),
                'estado_aprobacion' => strtoupper((string) $request->input('approval_status', 'PD')),
                'notificacion' => $request->filled('notification') ? trim($request->string('notification')->toString()) : null,
                'archivo' => $files['archivo'],
                'archivo1' => $files['archivo1'],
                'archivo2' => $files['archivo2'],
                'fecha' => $request->filled('date') ? $request->date('date')->toDateString() : now()->toDateString(),
                ...$this->modificationTimestampPayload(),
            ]);

            $this->registerQuoteHistory($inputRequest, $files);
            $this->registerAudit($user, $request->ip(), 'Registro de solicitud de insumo '.$inputRequest->descripcion);

            return $inputRequest;
        });
    }

    public function update(UpdateInputSolicitationRequest $request, InputRequest $inputRequest, User $user): InputRequest
    {
        return DB::transaction(function () use ($request, $inputRequest, $user): InputRequest {
            $files = $this->shouldReplaceFiles($request)
                ? $this->storeFiles($request, $inputRequest)
                : [
                    'archivo' => $inputRequest->archivo,
                    'archivo1' => $inputRequest->archivo1,
                    'archivo2' => $inputRequest->archivo2,
                ];

            $inputRequest->update([
                'descripcion' => strtoupper(trim($request->string('description')->toString())),
                'precio' => $request->input('price'),
                'unidad_medida' => (int) $request->integer('unit_measure_id'),
                'tipo' => (int) $request->integer('type_id'),
                'ubicacion' => strtoupper(trim($request->string('location')->toString())),
                'justificacion' => strtoupper(trim($request->string('justification')->toString())),
                'usuario_solicitante' => (int) $request->integer('requester_id'),
                'estado_aprobacion' => strtoupper($request->string('approval_status')->toString()),
                'notificacion' => $request->filled('notification') ? trim($request->string('notification')->toString()) : null,
                'archivo' => $files['archivo'],
                'archivo1' => $files['archivo1'],
                'archivo2' => $files['archivo2'],
                'fecha' => $request->filled('date') ? $request->date('date')->toDateString() : $inputRequest->fecha?->toDateString(),
                ...$this->modificationTimestampPayload(),
            ]);

            if ($this->shouldReplaceFiles($request)) {
                $this->registerQuoteHistory($inputRequest->refresh(), $files);
            }

            $this->registerAudit($user, $request->ip(), 'Actualizacion de solicitud de insumo '.$inputRequest->descripcion);

            return $inputRequest->refresh();
        });
    }

    private function storeFiles(StoreInputSolicitationRequest|UpdateInputSolicitationRequest $request, ?InputRequest $current = null): array
    {
        return [
            'archivo' => $this->fileService->store($request->file('valido'), 'valido') ?? $current?->archivo,
            'archivo1' => $this->fileService->store($request->file('propuesto_1'), 'propuesto_1') ?? $current?->archivo1,
            'archivo2' => $this->fileService->store($request->file('propuesto_2'), 'propuesto_2') ?? $current?->archivo2,
        ];
    }

    private function shouldReplaceFiles(UpdateInputSolicitationRequest $request): bool
    {
        return strtoupper((string) $request->input('adj', 'NO')) === 'SI'
            || $request->hasFile('valido')
            || $request->hasFile('propuesto_1')
            || $request->hasFile('propuesto_2');
    }

    private function registerQuoteHistory(InputRequest $inputRequest, array $files): void
    {
        InputQuote::query()->create([
            'id_insumo' => null,
            'condicion' => 'VALIDO',
            'estado' => 'AC',
            'id_log_insumo' => null,
            'archivo' => $files['archivo'],
            'fecha' => now()->toDateString(),
            'archivo1' => $files['archivo1'],
            'archivo2' => $files['archivo2'],
            'id_solicitud' => $inputRequest->id_solicitud,
        ]);
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

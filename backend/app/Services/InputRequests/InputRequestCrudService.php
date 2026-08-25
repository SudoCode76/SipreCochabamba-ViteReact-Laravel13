<?php

namespace App\Services\InputRequests;

use App\Http\Requests\InputRequest\StoreInputSolicitationRequest;
use App\Http\Requests\InputRequest\UpdateInputSolicitationRequest;
use App\Models\InputQuote;
use App\Models\InputRequest;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Repository\RepositoryFileService;
use App\Services\Repository\RepositoryUploadResult;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InputRequestCrudService
{
    public function __construct(
        private readonly InputRequestFileService $fileService,
        private readonly RepositoryFileService $repositoryFiles,
    ) {}

    public function create(StoreInputSolicitationRequest $request, User $user): InputRequest
    {
        $uploads = $this->persistUploads($this->uploads($request));

        return DB::transaction(function () use ($request, $user, $uploads): InputRequest {
            $files = $this->fileUrls($uploads);

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
                ...$this->locationPayload($request),
                ...$this->modificationTimestampPayload(),
            ]);

            $quote = $this->registerQuoteHistory($inputRequest, $files);
            $this->linkUploads($uploads, $inputRequest, $quote);
            $this->registerAudit($user, $request->ip(), 'Registro de solicitud de insumo '.$inputRequest->descripcion);

            return $inputRequest;
        });
    }

    public function update(UpdateInputSolicitationRequest $request, InputRequest $inputRequest, User $user): InputRequest
    {
        $uploads = $this->shouldReplaceFiles($request)
            ? $this->persistUploads($this->uploads($request))
            : [];

        return DB::transaction(function () use ($request, $inputRequest, $user, $uploads): InputRequest {
            $files = $this->shouldReplaceFiles($request)
                ? $this->fileUrls($uploads, $inputRequest)
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
                $quote = $this->registerQuoteHistory($inputRequest->refresh(), $files);
                $this->linkUploads($uploads, $inputRequest, $quote);
            }

            $this->registerAudit($user, $request->ip(), 'Actualizacion de solicitud de insumo '.$inputRequest->descripcion);

            return $inputRequest->refresh();
        });
    }

    /** @return array<string, array{source: UploadedFile, upload: RepositoryUploadResult}> */
    private function uploads(StoreInputSolicitationRequest|UpdateInputSolicitationRequest $request): array
    {
        return array_filter([
            'archivo' => $this->fileService->upload($request->file('valido'), 'valido'),
            'archivo1' => $this->fileService->upload($request->file('propuesto_1'), 'propuesto_1'),
            'archivo2' => $this->fileService->upload($request->file('propuesto_2'), 'propuesto_2'),
        ]);
    }

    private function fileUrls(array $uploads, ?InputRequest $current = null): array
    {
        return [
            'archivo' => $uploads['archivo']['upload']->fileUrl ?? $current?->archivo,
            'archivo1' => $uploads['archivo1']['upload']->fileUrl ?? $current?->archivo1,
            'archivo2' => $uploads['archivo2']['upload']->fileUrl ?? $current?->archivo2,
        ];
    }

    private function shouldReplaceFiles(UpdateInputSolicitationRequest $request): bool
    {
        return strtoupper((string) $request->input('adj', 'NO')) === 'SI'
            || $request->hasFile('valido')
            || $request->hasFile('propuesto_1')
            || $request->hasFile('propuesto_2');
    }

    private function registerQuoteHistory(InputRequest $inputRequest, array $files): InputQuote
    {
        return InputQuote::query()->create([
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

    /** @param array<string, array{source: UploadedFile, upload: RepositoryUploadResult, repository_file: \App\Models\RepositoryFile}> $uploads */
    private function linkUploads(array $uploads, InputRequest $inputRequest, InputQuote $quote): void
    {
        foreach ($uploads as $field => $data) {
            $this->repositoryFiles->link($data['repository_file'], $inputRequest, $field);
            $this->repositoryFiles->link($data['repository_file'], $quote, $field);
        }
    }

    /** @param array<string, array{source: UploadedFile, upload: RepositoryUploadResult}> $uploads */
    private function persistUploads(array $uploads): array
    {
        foreach ($uploads as $field => $data) {
            $uploads[$field]['repository_file'] = $this->repositoryFiles->persist($data['upload'], $data['source']);
        }

        return $uploads;
    }

    private function registerAudit(User $user, ?string $ip, string $process): void
    {
        app(AuditService::class)->record($user, $ip, $process);
    }

    private function locationPayload(StoreInputSolicitationRequest|UpdateInputSolicitationRequest $request): array
    {
        $payload = [
            'latitud' => $request->input('latitude'),
            'longitud' => $request->input('longitude'),
            'distrito' => $request->filled('district') ? strtoupper(trim($request->string('district')->toString())) : null,
            'zona' => $request->filled('zone') ? strtoupper(trim($request->string('zone')->toString())) : null,
            'otb' => $request->filled('otb') ? strtoupper(trim($request->string('otb')->toString())) : null,
        ];

        return array_filter(
            $payload,
            fn (mixed $value, string $column): bool => Schema::hasColumn('solicitud_insumo', $column) && $value !== null && $value !== '',
            ARRAY_FILTER_USE_BOTH
        );
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

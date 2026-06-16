<?php

namespace App\Services\Inputs;

use App\Http\Requests\Input\StoreInputQuoteRequest;
use App\Http\Requests\Input\UpdateInputPriceRequest;
use App\Models\Input;
use App\Models\InputQuote;
use App\Services\Files\PublicFileService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class InputQuoteService
{
    public function __construct(
        private readonly PublicFileService $publicFileService,
    ) {}

    public function create(Input $input, StoreInputQuoteRequest $request): InputQuote
    {
        return $input->quotes()->create($this->quotePayload($request, $request->filled('log_id')
            ? (int) $request->integer('log_id')
            : null));
    }

    private function quotePayload(StoreInputQuoteRequest|UpdateInputPriceRequest $request, ?int $logId): array
    {
        $payload = [
            'condicion' => $request->filled('condition') ? trim($request->string('condition')->toString()) : 'VG',
            'estado' => $request->filled('status') ? strtoupper($request->string('status')->toString()) : 'AC',
            'id_log_insumo' => $logId,
            'archivo' => $this->resolveFilePath($request->file('valido'), $request->input('file'), 'valido'),
            'fecha' => $request->filled('date') ? $request->date('date')->toDateString() : now()->toDateString(),
            'archivo1' => $this->resolveFilePath($request->file('propuesto_1'), $request->input('file_1'), 'propuesto_1'),
            'archivo2' => $this->resolveFilePath($request->file('propuesto_2'), $request->input('file_2'), 'propuesto_2'),
            'id_solicitud' => $request->filled('request_id') ? (int) $request->integer('request_id') : null,
        ];

        if (Schema::hasColumn('cotizaciones', 'archivo3')) {
            $payload['archivo3'] = $this->resolveFilePath($request->file('propuesto_3'), $request->input('file_3'), 'propuesto_3');
        }

        return $payload;
    }

    public function createForLog(Input $input, StoreInputQuoteRequest|UpdateInputPriceRequest $request, int $logId): ?InputQuote
    {
        if (! $this->hasUploadedFiles($request)) {
            return null;
        }

        return $input->quotes()->create($this->quotePayload($request, $logId));
    }

    public function unassigned(Input $input): Collection
    {
        return $input->quotes()
            ->with(['input', 'log'])
            ->whereNull('id_log_insumo')
            ->where('estado', 'AC')
            ->orderByDesc('fecha')
            ->orderByDesc('id_cotizacion')
            ->get();
    }

    public function attachUnassignedToLog(Input $input, array $quoteIds, int $logId): int
    {
        $ids = collect($quoteIds)
            ->filter(fn ($id): bool => is_numeric($id))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return 0;
        }

        $validCount = $input->quotes()
            ->whereIn('id_cotizacion', $ids)
            ->whereNull('id_log_insumo')
            ->count();

        if ($validCount !== $ids->count()) {
            throw ValidationException::withMessages([
                'quote_ids' => ['Solo se pueden seleccionar cotizaciones libres del mismo insumo.'],
            ]);
        }

        return $input->quotes()
            ->whereIn('id_cotizacion', $ids)
            ->whereNull('id_log_insumo')
            ->update(['id_log_insumo' => $logId]);
    }

    public function current(Input $input): ?InputQuote
    {
        return $input->quotes()
            ->with(['input', 'log'])
            ->where('estado', 'AC')
            ->orderByDesc('fecha')
            ->orderByDesc('condicion')
            ->orderByDesc('id_cotizacion')
            ->orderBy('id_log_insumo')
            ->first();
    }

    public function history(Input $input)
    {
        return $input->quotes()
            ->with(['input', 'log'])
            ->orderByDesc('fecha')
            ->orderByDesc('condicion')
            ->orderByDesc('id_cotizacion')
            ->orderBy('id_log_insumo')
            ->get();
    }

    public function logHistory(Input $input)
    {
        return $input->quotes()
            ->with(['input', 'log'])
            ->whereNotNull('id_log_insumo')
            ->orderByDesc('fecha')
            ->orderBy('id_log_insumo')
            ->orderBy('id_cotizacion')
            ->get();
    }

    public function filesByLog(int $logId)
    {
        return InputQuote::query()
            ->where('id_log_insumo', $logId)
            ->orderByDesc('fecha')
            ->orderByDesc('id_cotizacion')
            ->get();
    }

    public function hasUploadedFiles(StoreInputQuoteRequest|UpdateInputPriceRequest $request): bool
    {
        foreach (['valido', 'propuesto_1', 'propuesto_2', 'propuesto_3'] as $field) {
            $file = $request->file($field);

            if ($file instanceof UploadedFile) {
                return true;
            }
        }

        return false;
    }

    private function resolveFilePath(?UploadedFile $uploadedFile, mixed $fallbackPath, string $prefix): ?string
    {
        if ($uploadedFile instanceof UploadedFile) {
            return $this->publicFileService->storeQuote($uploadedFile, $prefix);
        }

        return is_string($fallbackPath) && trim($fallbackPath) !== ''
            ? trim($fallbackPath)
            : null;
    }
}

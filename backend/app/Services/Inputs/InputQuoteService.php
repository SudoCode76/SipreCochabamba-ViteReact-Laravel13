<?php

namespace App\Services\Inputs;

use App\Http\Requests\Input\StoreInputQuoteRequest;
use App\Models\Input;
use App\Models\InputQuote;
use App\Services\Files\PublicFileService;
use Illuminate\Http\UploadedFile;

class InputQuoteService
{
    public function __construct(
        private readonly PublicFileService $publicFileService,
    ) {}

    public function create(Input $input, StoreInputQuoteRequest $request): InputQuote
    {
        return $input->quotes()->create([
            'condicion' => $request->filled('condition') ? trim($request->string('condition')->toString()) : 'VG',
            'estado' => $request->filled('status') ? strtoupper($request->string('status')->toString()) : 'AC',
            'id_log_insumo' => $request->filled('log_id')
                ? (int) $request->integer('log_id')
                : $input->logs()->latest('id_log')->value('id_log'),
            'archivo' => $this->resolveFilePath($request->file('valido'), $request->input('file'), 'valido'),
            'fecha' => $request->filled('date') ? $request->date('date')->toDateString() : now()->toDateString(),
            'archivo1' => $this->resolveFilePath($request->file('propuesto_1'), $request->input('file_1'), 'propuesto_1'),
            'archivo2' => $this->resolveFilePath($request->file('propuesto_2'), $request->input('file_2'), 'propuesto_2'),
            'id_solicitud' => $request->filled('request_id') ? (int) $request->integer('request_id') : null,
        ]);
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

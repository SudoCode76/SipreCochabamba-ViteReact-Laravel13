<?php

namespace App\Modules\Projects\Services;

use App\Models\Project;
use App\Models\ProjectItem;
use App\Services\Files\PublicFileService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use setasign\Fpdi\Tcpdf\Fpdi;
use Throwable;

class ProjectSpecificationsPdfMergeService
{
    public function __construct(
        private readonly PublicFileService $publicFileService,
    ) {}

    public function stream(Project $project): Response
    {
        $entries = $this->validatedEntries($project);

        try {
            $pdf = new Fpdi('P', 'mm', 'A4', true, 'UTF-8', false);
            $pdf->SetPrintHeader(false);
            $pdf->SetPrintFooter(false);
            $pdf->SetAutoPageBreak(false);
            $pdf->SetMargins(0, 0, 0);

            foreach ($entries as $index => $entry) {
                $projectItem = $entry['project_item'];
                $filePath = $entry['path'];

                try {
                    $pdf->setSourceFile($filePath);
                } catch (Throwable) {
                    $this->failWithItems([
                        $this->itemError($projectItem, 'La especificación técnica no es un PDF legible o está dañada.', 'specification', 'invalid_pdf'),
                    ]);
                }

                for ($pageNumber = 1; $pageNumber <= $entry['page_count']; $pageNumber++) {
                    $templateId = $pdf->importPage($pageNumber);
                    $size = $pdf->getTemplateSize($templateId);
                    $orientation = ($size['width'] ?? 0) > ($size['height'] ?? 0) ? 'L' : 'P';

                    $pdf->AddPage($orientation, [$size['width'], $size['height']]);
                    $pdf->useTemplate($templateId);
                    $pdf->SetFont('helvetica', 'B', 8);
                    $pdf->SetTextColor(0, 0, 0);
                    $pdf->SetXY(8, 8);
                    $pdf->Cell(0, 5, 'ITEM Nro.- '.($index + 1), 0, 0, 'L', false);
                }
            }

            try {
                $contents = $pdf->Output('especificaciones_proyecto.pdf', 'S');
            } catch (Throwable $exception) {
                report($exception);

                throw ValidationException::withMessages([
                    'specifications' => ['No se pudo generar el consolidado porque una especificación técnica está dañada o no es un PDF legible.'],
                ]);
            }

            return response($contents, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="especificaciones_proyecto.pdf"',
            ]);
        } finally {
            $this->deleteTemporaryFiles($entries);
        }
    }

    public function validate(Project $project): void
    {
        $entries = $this->validatedEntries($project);

        $this->deleteTemporaryFiles($entries);
    }

    public function manifest(Project $project): array
    {
        $entries = $this->validatedEntries($project);

        try {
            $modules = collect($entries)
                ->groupBy(fn (array $entry): string => (string) ($entry['project_item']->id_modulo ?? 'none'))
                ->map(function ($moduleEntries): array {
                    $first = $moduleEntries->first()['project_item'];
                    $items = $moduleEntries->map(function (array $entry): array {
                        $projectItem = $entry['project_item'];

                        return [
                            'project_item_id' => (int) $projectItem->id_proyecto_item,
                            'item_id' => (int) $projectItem->id_item,
                            'name' => (string) ($projectItem->item?->item ?? 'Ítem sin nombre'),
                            'start_page' => $entry['start_page'],
                            'end_page' => $entry['end_page'],
                            'page_count' => $entry['page_count'],
                            'pages' => range($entry['start_page'], $entry['end_page']),
                        ];
                    })->values();

                    return [
                        'module_id' => $first->id_modulo ? (int) $first->id_modulo : null,
                        'name' => (string) ($first->module?->nombre_modulo ?? 'Sin módulo'),
                        'pages' => $items->pluck('pages')->flatten()->unique()->sort()->values()->all(),
                        'items' => $items->all(),
                    ];
                })->values()->all();

            return [
                'total_pages' => collect($entries)->sum('page_count'),
                'modules' => $modules,
                'fingerprint' => hash('sha256', json_encode(collect($entries)->map(fn (array $entry): array => [
                    'project_item_id' => (int) $entry['project_item']->id_proyecto_item,
                    'item_id' => (int) $entry['project_item']->id_item,
                    'module_id' => $entry['project_item']->id_modulo ? (int) $entry['project_item']->id_modulo : null,
                    'page_count' => $entry['page_count'],
                    'file_hash' => $entry['file_hash'],
                ])->values()->all(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            ];
        } finally {
            $this->deleteTemporaryFiles($entries);
        }
    }

    private function validatedEntries(Project $project): array
    {
        $projectItems = ProjectItem::query()
            ->with(['item', 'module'])
            ->where('id_proyecto', $project->id_proyecto)
            ->where('estado', 'AC')
            ->whereNotNull('id_item')
            ->whereHas('item')
            ->orderBy('prioridad')
            ->orderBy('id_item')
            ->orderBy('id_proyecto_item')
            ->get();

        if ($projectItems->isEmpty()) {
            throw ValidationException::withMessages([
                'specifications' => ['El proyecto no tiene ítems activos para imprimir especificaciones.'],
            ]);
        }

        $pdf = new Fpdi('P', 'mm', 'A4', true, 'UTF-8', false);
        $errors = [];
        $entries = [];
        $nextPage = 1;

        foreach ($projectItems as $projectItem) {
            $item = $projectItem->item;
            $resolvedFile = $this->resolveSpecificationFile($item?->especificacion);

            if ($resolvedFile['path'] === null) {
                $errors[] = $this->itemError(
                    $projectItem,
                    $resolvedFile['reason'],
                    'specification',
                    $resolvedFile['status'],
                );

                continue;
            }

            $compatibleFile = $this->openFpdiCompatiblePdf($pdf, $resolvedFile['path']);

            if ($compatibleFile === null) {
                if ($resolvedFile['temporary']) {
                    @unlink($resolvedFile['path']);
                }
                $errors[] = $this->itemError($projectItem, 'La especificación técnica no es un PDF legible o está dañada.', 'specification', 'invalid_pdf');

                continue;
            }

            if ($resolvedFile['temporary'] && $compatibleFile['path'] !== $resolvedFile['path']) {
                @unlink($resolvedFile['path']);
            }

            try {
                $fileHash = hash_file('sha256', $compatibleFile['path']);
                if ($fileHash === false) {
                    throw new \RuntimeException('No se pudo calcular la huella del PDF.');
                }
            } catch (Throwable) {
                $this->deleteTemporaryFiles([$compatibleFile]);
                $errors[] = $this->itemError($projectItem, 'La especificación técnica no es un PDF legible o está dañada.', 'specification', 'invalid_pdf');

                continue;
            }

            $entries[] = [
                'project_item' => $projectItem,
                'path' => $compatibleFile['path'],
                'temporary' => $resolvedFile['temporary'] || $compatibleFile['temporary'],
                'page_count' => $compatibleFile['page_count'],
                'start_page' => $nextPage,
                'end_page' => $nextPage + $compatibleFile['page_count'] - 1,
                'file_hash' => $fileHash,
            ];
            $nextPage += $compatibleFile['page_count'];
        }

        if ($errors !== []) {
            $this->deleteTemporaryFiles($entries);
            $this->failWithItems($errors);
        }

        return $entries;
    }

    /** @return array{path: string, temporary: bool, page_count: int}|null */
    private function openFpdiCompatiblePdf(Fpdi $pdf, string $sourcePath): ?array
    {
        try {
            return [
                'path' => $sourcePath,
                'temporary' => false,
                'page_count' => $pdf->setSourceFile($sourcePath),
            ];
        } catch (Throwable $exception) {
            $compatiblePath = $this->normalizeForFpdi($sourcePath);

            if ($compatiblePath === null) {
                Log::warning('No se pudo normalizar una especificación para FPDI.', [
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);

                return null;
            }

            try {
                return [
                    'path' => $compatiblePath,
                    'temporary' => true,
                    'page_count' => $pdf->setSourceFile($compatiblePath),
                ];
            } catch (Throwable $normalizationException) {
                Log::warning('La especificación normalizada sigue sin ser compatible con FPDI.', [
                    'exception' => $normalizationException::class,
                    'message' => $normalizationException->getMessage(),
                ]);
                @unlink($compatiblePath);

                return null;
            }
        }
    }

    private function normalizeForFpdi(string $sourcePath): ?string
    {
        $ghostscript = $this->ghostscriptBinary();
        if ($ghostscript === null) {
            return null;
        }

        $targetPath = tempnam(sys_get_temp_dir(), 'sipre_pdf14_');
        if ($targetPath === false) {
            return null;
        }
        @chmod($targetPath, 0600);

        $command = sprintf(
            '%s -q -dSAFER -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/prepress -sOutputFile=%s %s 2>&1',
            escapeshellarg($ghostscript),
            escapeshellarg($targetPath),
            escapeshellarg($sourcePath),
        );
        $output = [];
        $exitCode = 1;
        exec($command, $output, $exitCode);

        if ($exitCode === 0
            && is_readable($targetPath)
            && str_starts_with((string) file_get_contents($targetPath, false, null, 0, 4), '%PDF')) {
            return $targetPath;
        }

        Log::warning('Ghostscript no pudo normalizar una especificación.', [
            'exit_code' => $exitCode,
            'output' => implode("\n", array_slice($output, -5)),
        ]);
        @unlink($targetPath);

        return null;
    }

    private function ghostscriptBinary(): ?string
    {
        foreach (['/usr/bin/gs', '/usr/local/bin/gs'] as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        return null;
    }

    /** @return array{path: string|null, temporary: bool, status: string, reason: string} */
    private function resolveSpecificationFile(?string $value): array
    {
        if (blank($value)) {
            return [
                'path' => null,
                'temporary' => false,
                'status' => 'missing',
                'reason' => 'La especificación técnica no está cargada.',
            ];
        }

        if ($this->publicFileService->isHttpsUrl($value)) {
            try {
                return [
                    'path' => $this->publicFileService->materializeRemotePdf((string) $value),
                    'temporary' => true,
                    'status' => 'available',
                    'reason' => '',
                ];
            } catch (Throwable $exception) {
                report($exception);

                return [
                    'path' => null,
                    'temporary' => false,
                    'status' => 'remote_unavailable',
                    'reason' => 'La especificación técnica está registrada, pero no se puede descargar desde el repositorio externo.',
                ];
            }
        }

        $path = $this->publicFileService->absolutePath($value);

        if ($path === null) {
            return [
                'path' => null,
                'temporary' => false,
                'status' => 'missing',
                'reason' => 'La especificación técnica no existe o no se encuentra en la carpeta.',
            ];
        }

        return [
            'path' => $path,
            'temporary' => false,
            'status' => 'available',
            'reason' => '',
        ];
    }

    /** @param array<int, array{path: string, temporary?: bool}> $entries */
    private function deleteTemporaryFiles(array $entries): void
    {
        foreach ($entries as $entry) {
            if (($entry['temporary'] ?? false) === true) {
                @unlink($entry['path']);
            }
        }
    }

    private function missingMessage(string $itemName): string
    {
        return "No se pudo generar el consolidado porque la especificación técnica del ítem {$itemName} no existe, no es PDF legible o no se encuentra en la carpeta.";
    }

    private function itemError(ProjectItem $projectItem, string $reason, string $missing, string $status = 'missing'): array
    {
        $itemName = trim((string) ($projectItem->item?->item ?? 'sin nombre'));

        return [
            'id_item' => $projectItem->id_item,
            'name' => $itemName,
            'reason' => $reason,
            'missing' => $missing,
            'status' => $status,
        ];
    }

    private function failWithItems(array $items): void
    {
        $messages = collect($items)
            ->map(fn (array $item): string => $this->missingMessage((string) ($item['name'] ?? 'sin nombre')))
            ->values()
            ->all();

        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'No se pudo generar el reporte porque uno o más ítems tienen especificaciones pendientes.',
            'errors' => [
                'report' => $messages,
                'specifications' => $messages,
            ],
            'items' => $items,
        ], 422));
    }
}

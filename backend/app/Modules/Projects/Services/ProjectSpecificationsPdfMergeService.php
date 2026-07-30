<?php

namespace App\Modules\Projects\Services;

use App\Models\Project;
use App\Models\ProjectItem;
use App\Services\Files\PublicFileService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;
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

        $pdf = new Fpdi('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetPrintHeader(false);
        $pdf->SetPrintFooter(false);
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(0, 0, 0);

        foreach ($entries as $index => $entry) {
            $projectItem = $entry['project_item'];
            $item = $projectItem->item;
            $filePath = $entry['path'];

            try {
                $pdf->setSourceFile($filePath);
            } catch (Throwable) {
                $this->failWithItems([
                    $this->itemError($projectItem, 'La especificación técnica no es un PDF legible o está dañada.', 'specification'),
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
    }

    public function validate(Project $project): void
    {
        $this->validatedEntries($project);
    }

    public function manifest(Project $project): array
    {
        $entries = $this->validatedEntries($project);
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
            $filePath = $this->resolveSpecificationPath($item?->especificacion);

            if ($filePath === null) {
                $errors[] = $this->itemError($projectItem, 'La especificación técnica no existe o no se encuentra en la carpeta.', 'specification');
                continue;
            }

            try {
                $pageCount = $pdf->setSourceFile($filePath);
            } catch (Throwable) {
                $errors[] = $this->itemError($projectItem, 'La especificación técnica no es un PDF legible o está dañada.', 'specification');
                continue;
            }

            $entries[] = [
                'project_item' => $projectItem,
                'path' => $filePath,
                'page_count' => $pageCount,
                'start_page' => $nextPage,
                'end_page' => $nextPage + $pageCount - 1,
                'file_hash' => hash_file('sha256', $filePath),
            ];
            $nextPage += $pageCount;
        }

        if ($errors !== []) {
            $this->failWithItems($errors);
        }

        return $entries;
    }

    private function resolveSpecificationPath(?string $value): ?string
    {
        return $this->publicFileService->absolutePath($value);
    }

    private function missingMessage(string $itemName): string
    {
        return "No se pudo generar el consolidado porque la especificación técnica del ítem {$itemName} no existe, no es PDF legible o no se encuentra en la carpeta.";
    }

    private function itemError(ProjectItem $projectItem, string $reason, string $missing): array
    {
        $itemName = trim((string) ($projectItem->item?->item ?? 'sin nombre'));

        return [
            'id_item' => $projectItem->id_item,
            'name' => $itemName,
            'reason' => $reason,
            'missing' => $missing,
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

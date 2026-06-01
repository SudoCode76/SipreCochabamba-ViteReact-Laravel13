<?php

namespace App\Modules\Projects\Services;

use App\Models\Project;
use App\Models\ProjectItem;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use setasign\Fpdi\Tcpdf\Fpdi;
use Throwable;

class ProjectSpecificationsPdfMergeService
{
    public function stream(Project $project): Response
    {
        $projectItems = ProjectItem::query()
            ->with('item')
            ->where('id_proyecto', $project->id_proyecto)
            ->where('estado', 'AC')
            ->whereNotNull('id_item')
            ->whereHas('item')
            ->orderBy('prioridad')
            ->orderBy('id_item')
            ->get();

        if ($projectItems->isEmpty()) {
            throw ValidationException::withMessages([
                'specifications' => ['El proyecto no tiene ítems activos para imprimir especificaciones.'],
            ]);
        }

        $pdf = new Fpdi('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetPrintHeader(false);
        $pdf->SetPrintFooter(false);
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(0, 0, 0);

        foreach ($projectItems as $index => $projectItem) {
            $item = $projectItem->item;
            $filePath = $this->resolveSpecificationPath($item?->especificacion);

            if ($filePath === null) {
                throw ValidationException::withMessages([
                    'specifications' => [$this->missingMessage((string) ($item?->item ?? 'sin nombre'))],
                ]);
            }

            try {
                $pageCount = $pdf->setSourceFile($filePath);
            } catch (Throwable) {
                throw ValidationException::withMessages([
                    'specifications' => [$this->missingMessage((string) ($item?->item ?? 'sin nombre'))],
                ]);
            }

            for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
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

        return response($pdf->Output('especificaciones_proyecto.pdf', 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="especificaciones_proyecto.pdf"',
        ]);
    }

    private function resolveSpecificationPath(?string $value): ?string
    {
        $rawPath = trim((string) $value);

        if ($rawPath === '') {
            return null;
        }

        if (is_file($rawPath) && is_readable($rawPath)) {
            return $rawPath;
        }

        $path = preg_replace('#^https?://[^/]+/(storage|public)/#', '', $rawPath);
        $path = preg_replace('#^/?(storage|public)/#', '', (string) $path);
        $path = ltrim(str_replace('\\', '/', (string) $path), '/');

        if ($path === '' || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $absolutePath = Storage::disk('public')->path($path);

        return is_readable($absolutePath) ? $absolutePath : null;
    }

    private function missingMessage(string $itemName): string
    {
        return "No se pudo generar el consolidado porque la especificación técnica del ítem {$itemName} no existe, no es PDF legible o no se encuentra en la carpeta.";
    }
}

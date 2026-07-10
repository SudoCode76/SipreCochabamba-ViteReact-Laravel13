<?php

namespace App\Modules\Items\Services;

use App\Models\Item;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ItemReportPdfResolver
{
    public function __construct(
        private readonly LegacyUnitPriceAnalysisPdfService $legacyUnitPriceAnalysisPdfService,
        private readonly MaterialBreakdownPdfService $materialBreakdownPdfService,
        private readonly LaborBreakdownPdfService $laborBreakdownPdfService,
        private readonly MachineryBreakdownPdfService $machineryBreakdownPdfService,
        private readonly HistoricalBreakdownPdfService $historicalBreakdownPdfService,
    ) {}

    public function resolve(Item $item, string $reportKey, array $parameters = []): array
    {
        $response = match ($reportKey) {
            'item_unit_price_analysis' => $this->legacyUnitPriceAnalysisPdfService->stream($item, $this->mode($parameters)),
            'item_price_recalculation' => $this->priceRecalculation($item, $parameters),
            'item_material_breakdown' => $this->materialBreakdownPdfService->stream($item),
            'item_labor_breakdown' => $this->laborBreakdownPdfService->stream($item),
            'item_machinery_breakdown' => $this->machineryBreakdownPdfService->stream($item),
            'item_breakdown_recalculation' => $this->historicalBreakdown($item, $parameters),
            default => throw ValidationException::withMessages([
                'report_key' => ['El reporte seleccionado no es firmable.'],
            ]),
        };

        return [
            'content' => $response->getContent(),
            'filename' => $this->filename($reportKey),
            'content_type' => $response->headers->get('Content-Type', 'application/pdf'),
        ];
    }

    private function priceRecalculation(Item $item, array $parameters): Response
    {
        $fecha = $parameters['fecha'] ?? null;

        if (! $fecha) {
            throw ValidationException::withMessages([
                'fecha' => ['La fecha es obligatoria para firmar el recálculo de precio.'],
            ]);
        }

        return $this->legacyUnitPriceAnalysisPdfService->streamRecalculated($item, Carbon::parse($fecha), $this->mode($parameters));
    }

    private function historicalBreakdown(Item $item, array $parameters): Response
    {
        $fecha = $parameters['fecha'] ?? null;

        if (! $fecha) {
            throw ValidationException::withMessages([
                'fecha' => ['La fecha es obligatoria para firmar el desglose recalculado.'],
            ]);
        }

        return $this->historicalBreakdownPdfService->stream($item, $parameters['type'] ?? $parameters['tipo_desglose'] ?? 1, Carbon::parse($fecha));
    }

    private function mode(array $parameters): string
    {
        return (string) ($parameters['mode'] ?? 'general');
    }

    private function filename(string $reportKey): string
    {
        return match ($reportKey) {
            'item_unit_price_analysis' => 'analisis_precio_unitario_item.pdf',
            'item_price_recalculation' => 'recalculo_precio_item.pdf',
            'item_material_breakdown' => 'desglose_materiales_item.pdf',
            'item_labor_breakdown' => 'desglose_mano_obra_item.pdf',
            'item_machinery_breakdown' => 'desglose_maquinaria_item.pdf',
            'item_breakdown_recalculation' => 'desglose_recalculado_item.pdf',
            default => 'reporte_item.pdf',
        };
    }
}

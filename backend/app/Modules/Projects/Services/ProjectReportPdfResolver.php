<?php

namespace App\Modules\Projects\Services;

use App\Models\Project;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ProjectReportPdfResolver
{
    public function __construct(
        private readonly ProjectBudgetService $projectBudgetService,
        private readonly ProjectBudgetByGroupPdfService $projectBudgetByGroupPdfService,
        private readonly ProjectIncidenceSummaryPdfService $projectIncidenceSummaryPdfService,
        private readonly ProjectGeneralBudgetPdfService $projectGeneralBudgetPdfService,
        private readonly ProjectInputBreakdownPdfService $projectInputBreakdownPdfService,
        private readonly ProjectInputsReportPdfService $projectInputsReportPdfService,
        private readonly ProjectInputsGroupedReportPdfService $projectInputsGroupedReportPdfService,
        private readonly ProjectUnitPricesPdfService $projectUnitPricesPdfService,
        private readonly ProjectSpecificationsPdfMergeService $projectSpecificationsPdfMergeService,
    ) {}

    public function resolve(Project $project, string $reportKey, array $parameters = []): array
    {
        $response = match ($reportKey) {
            'budget_by_group' => $this->projectBudgetByGroupPdfService->stream($project),
            'budget_recalculation' => $this->budgetRecalculation($project, $parameters),
            'incidence_summary' => $this->projectIncidenceSummaryPdfService->stream($project, $this->format($parameters)),
            'general_budget' => $this->projectGeneralBudgetPdfService->stream($project, $this->format($parameters)),
            'input_breakdown' => $this->projectInputBreakdownPdfService->stream($project, $this->type($parameters)),
            'inputs_report' => $this->projectInputsReportPdfService->stream($project),
            'grouped_inputs_report' => $this->projectInputsGroupedReportPdfService->stream($project),
            'unit_prices' => $this->projectUnitPricesPdfService->stream($project, $this->format($parameters)),
            'specifications' => $this->projectSpecificationsPdfMergeService->stream($project),
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

    private function budgetRecalculation(Project $project, array $parameters): Response
    {
        $fecha = $parameters['fecha'] ?? null;

        if (! $fecha) {
            throw ValidationException::withMessages([
                'fecha' => ['La fecha es obligatoria para firmar el presupuesto recalculado.'],
            ]);
        }

        $budget = $this->projectBudgetService->budgetRecalculation($project, Carbon::parse($fecha));

        return $this->projectBudgetByGroupPdfService->streamHistorical($project, $budget);
    }

    private function format(array $parameters): string
    {
        $format = strtoupper((string) ($parameters['format'] ?? 'PCA'));
        $allowed = ['PCA', 'PC_FPS', 'PC_UPRE', 'PC_FNDR', 'PC_OBRAS'];

        return in_array($format, $allowed, true) ? $format : 'PCA';
    }

    private function type(array $parameters): int
    {
        $type = (int) ($parameters['type'] ?? 1);

        return in_array($type, [1, 2, 3], true) ? $type : 1;
    }

    private function filename(string $reportKey): string
    {
        return match ($reportKey) {
            'budget_by_group' => 'presupuesto_por_rubros.pdf',
            'budget_recalculation' => 'presupuesto_recalculado.pdf',
            'incidence_summary' => 'resumen_incidencia.pdf',
            'general_budget' => 'presupuesto_general.pdf',
            'input_breakdown' => 'desglose_insumos.pdf',
            'inputs_report' => 'reporte_insumos.pdf',
            'grouped_inputs_report' => 'proyecto_agrupado_insumos.pdf',
            'unit_prices' => 'analisis_precios_unitarios.pdf',
            'specifications' => 'especificaciones_proyecto.pdf',
            default => 'reporte.pdf',
        };
    }
}

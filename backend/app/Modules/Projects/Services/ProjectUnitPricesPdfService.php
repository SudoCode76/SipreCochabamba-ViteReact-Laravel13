<?php

namespace App\Modules\Projects\Services;

use App\Models\Project;
use App\Modules\Items\Services\LegacyUnitPriceAnalysisPdfService;
use App\Support\Pdf\MunicipalReportPdfFactory;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use TCPDF;
use Throwable;

class ProjectUnitPricesPdfService
{
    public function __construct(
        private readonly ProjectBudgetService $projectBudgetService,
        private readonly LegacyUnitPriceAnalysisPdfService $legacyUnitPriceAnalysisPdfService,
    ) {}

    public function stream(Project $project, string $format, ?array $signatureLayout = null): Response
    {
        $items = $this->validatedItems($project, $format);

        $pdf = new LegacyProjectUnitPricesPdf('L', defined('PDF_UNIT') ? PDF_UNIT : 'mm', 'A4', true, 'UTF-8', false);
        $this->configurePdf($pdf);
        $pdf->AddPage('A4');
        $pdf->SetFont('dejavusans', '', 7, '', true);
        $pdf->Ln();

        MunicipalReportPdfFactory::render($pdf, function () use ($items, $pdf): void {
            if ($items === []) {
                $pdf->writeHTML('<p>No existen ítems activos para imprimir.</p>', true, false, true, false, '');

                return;
            }

            foreach ($items as $index => $row) {
                if ($index > 0) {
                    $pdf->AddPage();
                }

                try {
                    $analysis = $row['analysis'];
                    [$html, $missingParametersHtml] = $this->legacyUnitPriceAnalysisPdfService->buildLegacyHtml($analysis, true);
                } catch (Throwable $exception) {
                    report($exception);

                    $itemName = $row['analysis']['item']['name'] ?? 'sin nombre';

                    $this->fail(
                        "No se pudo generar precios unitarios: el ítem \"{$itemName}\" tiene datos incompletos o inválidos.",
                        $row['analysis']['item'] ?? null,
                        'invalid_data',
                    );
                }

                $pdf->Ln();
                $pdf->SetFont('dejavusans', '', 8, '', true);
                $pdf->writeHTML($missingParametersHtml ?? $html, true, false, true, false, '');
            }
        }, $signatureLayout);

        return $this->inlineResponse($pdf);
    }

    public function validate(Project $project, string $format): void
    {
        $this->validatedItems($project, $format);
    }

    private function validatedItems(Project $project, string $format): array
    {
        try {
            $items = $this->projectBudgetService->unitPricesForLegacyProjectPdf($project, $format);
            $this->validateReportItems($items);

            return $items;
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'report' => ['No se pudo generar precios unitarios: uno de los parámetros de porcentaje del proyecto no está configurado adecuadamente.'],
            ]);
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'report' => ['No se pudo generar precios unitarios porque el proyecto tiene datos incompletos en sus ítems o insumos.'],
            ]);
        }
    }

    private function validateReportItems(array $items): void
    {
        foreach ($items as $row) {
            $analysis = $row['analysis'] ?? [];
            $item = $analysis['item'] ?? [];
            $itemName = trim((string) ($item['name'] ?? ''));
            $unit = trim((string) data_get($item, 'unit_measure.abbreviation'));

            if ($itemName === '' || $itemName === 'Item no disponible') {
                $this->fail('No se pudo generar precios unitarios: existe un ítem del proyecto sin nombre configurado.');
            }

            if ($unit === '') {
                $this->fail("No se pudo generar precios unitarios: el ítem \"{$itemName}\" no tiene unidad de medida configurada.", $item, 'unit_measure');
            }

            $components = collect([
                ...($analysis['materials'] ?? []),
                ...($analysis['labor'] ?? []),
                ...($analysis['tools'] ?? []),
            ]);

            if ($components->isEmpty()) {
                $this->fail("No se pudo generar precios unitarios: el ítem \"{$itemName}\" no tiene insumos configurados.", $item, 'inputs');
            }

            foreach ($components as $component) {
                $description = trim((string) ($component['description'] ?? ''));

                if ($description === '') {
                    $this->fail("No se pudo generar precios unitarios: el ítem \"{$itemName}\" tiene un insumo sin descripción.", $item, 'input_description');
                }

                if (trim((string) data_get($component, 'unit_measure.abbreviation')) === '') {
                    $this->fail("No se pudo generar precios unitarios: el insumo \"{$description}\" del ítem \"{$itemName}\" no tiene unidad de medida.", $item, 'input_unit_measure');
                }

                if (! array_key_exists('unit_price', $component) || ! is_numeric($component['unit_price'])) {
                    $this->fail("No se pudo generar precios unitarios: el insumo \"{$description}\" del ítem \"{$itemName}\" no tiene precio configurado.", $item, 'input_price');
                }

                if (! array_key_exists('quantity', $component) || ! is_numeric($component['quantity']) || (float) $component['quantity'] <= 0) {
                    $this->fail("No se pudo generar precios unitarios: el insumo \"{$description}\" del ítem \"{$itemName}\" no tiene cantidad válida.", $item, 'input_quantity');
                }
            }
        }
    }

    private function fail(string $message, ?array $item = null, ?string $missing = null): void
    {
        if ($item !== null) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => $message,
                'errors' => [
                    'report' => [$message],
                ],
                'items' => [[
                    'id_item' => $item['id_item'] ?? null,
                    'name' => $item['name'] ?? 'sin nombre',
                    'reason' => $message,
                    'missing' => $missing,
                ]],
            ], 422));
        }

        throw ValidationException::withMessages([
            'report' => [$message],
        ]);
    }

    private function configurePdf(LegacyProjectUnitPricesPdf $pdf): void
    {
        $pdf->SetHeaderData(
            defined('PDF_HEADER_LOGO') ? PDF_HEADER_LOGO : '',
            defined('PDF_HEADER_LOGO_WIDTH') ? PDF_HEADER_LOGO_WIDTH : 0,
            (defined('PDF_HEADER_TITLE') ? PDF_HEADER_TITLE : '').' 001',
            defined('PDF_HEADER_STRING') ? PDF_HEADER_STRING : '',
            [0, 64, 25],
            [0, 64, 128],
        );
        $pdf->setFooterFont([
            defined('PDF_FONT_NAME_DATA') ? PDF_FONT_NAME_DATA : 'helvetica',
            '',
            defined('PDF_FONT_SIZE_DATA') ? PDF_FONT_SIZE_DATA : 8,
        ]);
        $pdf->setFooterData([0, 64, 0], [0, 64, 128]);
        $pdf->SetDefaultMonospacedFont(defined('PDF_FONT_MONOSPACED') ? PDF_FONT_MONOSPACED : 'courier');
        $pdf->SetMargins(
            defined('PDF_MARGIN_LEFT') ? PDF_MARGIN_LEFT : 15,
            35,
            defined('PDF_MARGIN_RIGHT') ? PDF_MARGIN_RIGHT : 15,
        );
        $pdf->SetHeaderMargin(defined('PDF_MARGIN_HEADER') ? PDF_MARGIN_HEADER : 5);
        $pdf->SetFooterMargin(defined('PDF_MARGIN_FOOTER') ? PDF_MARGIN_FOOTER : 10);
        $pdf->SetAutoPageBreak(true, defined('PDF_MARGIN_BOTTOM') ? PDF_MARGIN_BOTTOM : 25);
        $pdf->setImageScale(defined('PDF_IMAGE_SCALE_RATIO') ? PDF_IMAGE_SCALE_RATIO : 1.25);
        $pdf->setFontSubsetting(true);
        $pdf->SetDisplayMode('real');
        $pdf->SetAutoPageBreak(true, 10);
    }

    private function inlineResponse(LegacyProjectUnitPricesPdf $pdf): Response
    {
        $filename = 'analisis_de_precios_unitarios_print.pdf';

        return response($pdf->Output($filename, 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}

class LegacyProjectUnitPricesPdf extends TCPDF
{
    public function Header(): void
    {
        date_default_timezone_set('America/La_Paz');
        setlocale(LC_TIME, 'es_RB');

        $this->SetY(10);
        $this->SetFont('helvetica', 'B', 9, '', true);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(120, 4, 'GOBIERNO AUTONOMO MUNICIPAL DE COCHABAMBA', 0, 0, 'L', 0, '', 3);
        $this->Cell(30, 4, 'FECHA IMPRESION:', 0, 0, 'L', 0, '', 3);
        $this->Cell(30, 4, date('d/m/Y H:i:s'), 0, 1, 'C', 0, '', 0, false, 'T', 'M');
        $this->Cell(180, 4, 'COCHABAMBA-BOLIVIA', 0, 1, 'L', 0, '', 3);
        $this->SetFont('helvetica', 'B', 11, '', true);
        $this->top_margin = $this->GetY() + 100;
        $this->writeHTML('', true, false, true, false, '');
    }

    public function Footer(): void
    {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'N', 6);
        $this->Cell(0, 10, 'Pagina '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

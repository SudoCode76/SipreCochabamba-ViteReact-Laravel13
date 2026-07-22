<?php

namespace Tests\Unit\Support\Pdf;

use App\Support\Pdf\MunicipalReportPdfFactory;
use PHPUnit\Framework\TestCase;

class MunicipalReportPdfFactoryTest extends TestCase
{
    public function test_signature_reserve_reflows_content_without_an_empty_last_page(): void
    {
        $pdf = MunicipalReportPdfFactory::make('PRUEBA DE PAGINACION');
        $rows = implode('', array_map(
            fn (int $row): string => "<tr><td>Fila {$row}</td><td>Contenido del reporte</td></tr>",
            range(1, 80)
        ));

        MunicipalReportPdfFactory::render(
            $pdf,
            fn () => $pdf->writeHTML("<table cellpadding=\"6\" border=\"1\">{$rows}</table>", true, false, true, false, ''),
            ['page_scope' => 'all', 'reserved_height' => 44]
        );

        $cutoff = $pdf->getPageHeight() - 10 - 44;

        $this->assertGreaterThan(1, $pdf->getNumPages());
        $this->assertLessThanOrEqual($cutoff + 0.1, $pdf->GetY());
        $this->assertGreaterThan($pdf->getMargins()['top'], $pdf->GetY());
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Report\Writers;

use Barryvdh\DomPDF\Facade\Pdf;

class PdfReportWriter
{
    /**
     * Generate a PDF report from grouped data using a Blade template.
     */
    public function write(array $groupedData, array $templateJson, array $options, string $outputPath): void
    {
        $orientation = $options['page_orientation'] ?? 'portrait';
        $pageSize = $options['page_size'] ?? 'A4';

        $pdf = Pdf::loadView('reports.report', [
            'groups' => $groupedData,
            'template' => $templateJson,
            'options' => $options,
            'lang' => $options['language'] ?? 'de',
        ]);

        $pdf->setPaper($pageSize, $orientation);

        file_put_contents($outputPath, $pdf->output());
    }
}

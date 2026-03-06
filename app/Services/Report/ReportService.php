<?php

declare(strict_types=1);

namespace App\Services\Report;

use App\Models\ReportJob;
use App\Models\ReportTemplate;
use App\Models\SearchProfile;
use App\Services\Report\Writers\DocxReportWriter;
use App\Services\Report\Writers\PdfReportWriter;
use Illuminate\Support\Facades\Log;

class ReportService
{
    public function __construct(
        private readonly ReportDataCollector $dataCollector,
        private readonly DocxReportWriter $docxWriter,
        private readonly PdfReportWriter $pdfWriter,
    ) {}

    /**
     * Execute a report generation.
     *
     * @return array{path: string, format: string, size: int, duration: float}
     */
    public function execute(ReportTemplate $template, ?SearchProfile $searchProfile = null, ?string $outputDir = null, ?string $format = null): array
    {
        $startTime = microtime(true);
        $outputDir = $outputDir ?? storage_path('app/reports');

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $searchProfile = $searchProfile ?? $template->searchProfile;
        $format = $format ?? $template->format ?? 'pdf';

        Log::channel('export')->info("Report-Generierung gestartet: {$template->name}", [
            'template_id' => $template->id,
            'format' => $format,
        ]);

        // Collect product data
        $data = $this->dataCollector->collect($template, $searchProfile);

        $templateJson = $template->template_json;
        $options = [
            'page_orientation' => $template->page_orientation ?? 'portrait',
            'page_size' => $template->page_size ?? 'A4',
            'language' => $template->language ?? 'de',
            'title' => $templateJson['title'] ?? $template->name,
        ];

        // Generate document
        $slug = preg_replace('/[^a-zA-Z0-9_-]/', '_', $template->name);
        $date = now()->format('Y-m-d_His');
        $extension = $format === 'docx' ? 'docx' : 'pdf';
        $fileName = "{$slug}_{$date}.{$extension}";
        $outputPath = "{$outputDir}/{$fileName}";

        match ($format) {
            'docx' => $this->docxWriter->write($data['grouped'], $templateJson, $options, $outputPath),
            default => $this->pdfWriter->write($data['grouped'], $templateJson, $options, $outputPath),
        };

        $duration = round(microtime(true) - $startTime, 2);

        Log::channel('export')->info("Report-Generierung abgeschlossen: {$template->name}", [
            'template_id' => $template->id,
            'path' => $outputPath,
            'products' => $data['total'],
            'duration' => $duration,
        ]);

        return [
            'path' => $outputPath,
            'format' => $format,
            'size' => (int) filesize($outputPath),
            'duration' => $duration,
            'product_count' => $data['total'],
        ];
    }

    /**
     * Execute via a ReportJob (for async queue processing).
     */
    public function executeJob(ReportJob $job): array
    {
        $template = $job->reportTemplate;
        $searchProfile = $job->searchProfile ?? $template->searchProfile;

        $job->update([
            'last_status' => 'running',
            'last_run_at' => now(),
            'last_error' => null,
        ]);

        try {
            $format = $job->format ?: null;

            $result = $this->execute($template, $searchProfile, format: $format);

            $job->update([
                'last_status' => 'completed',
                'last_duration_seconds' => $result['duration'],
                'last_output_path' => $result['path'],
                'last_result' => [
                    'format' => $result['format'],
                    'size_bytes' => $result['size'],
                    'product_count' => $result['product_count'],
                    'duration_seconds' => $result['duration'],
                ],
            ]);

            return $result;
        } catch (\Throwable $e) {
            $job->update([
                'last_status' => 'failed',
                'last_error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Generate a preview with limited products (always synchronous).
     */
    public function preview(ReportTemplate $template, ?SearchProfile $searchProfile = null, int $limit = 5, ?string $format = null): array
    {
        $searchProfile = $searchProfile ?? $template->searchProfile;
        $data = $this->dataCollector->collect($template, $searchProfile, $limit);

        $templateJson = $template->template_json;
        $options = [
            'page_orientation' => $template->page_orientation ?? 'portrait',
            'page_size' => $template->page_size ?? 'A4',
            'language' => $template->language ?? 'de',
            'title' => ($templateJson['title'] ?? $template->name) . ' (Vorschau)',
        ];

        $outputDir = storage_path('app/reports/preview');
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $format = $format ?? $template->format ?? 'pdf';
        $extension = $format === 'docx' ? 'docx' : 'pdf';
        $outputPath = "{$outputDir}/preview_" . uniqid() . ".{$extension}";

        match ($format) {
            'docx' => $this->docxWriter->write($data['grouped'], $templateJson, $options, $outputPath),
            default => $this->pdfWriter->write($data['grouped'], $templateJson, $options, $outputPath),
        };

        return [
            'path' => $outputPath,
            'format' => $format,
            'size' => (int) filesize($outputPath),
            'product_count' => $data['total'],
        ];
    }
}

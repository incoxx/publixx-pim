<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Models\ExportJob;
use App\Models\ExportJobLog;
use App\Models\ExportProfile;
use App\Models\PdfTemplate;
use App\Models\Product;
use App\Models\ReportTemplate;
use App\Models\SearchProfile;
use App\Services\PdfTemplate\PdfTemplateService;
use App\Services\Report\ReportService;
use Cron\CronExpression;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Steuert Export-Jobs: Ausführung, Formatwahl, Filterung.
 *
 * Ein ExportJob kombiniert:
 * - Filter (was exportiert wird) — direkt oder via SearchProfile
 * - Format (json, excel, csv, xml)
 * - Sektionen (welche Entitäts-Typen)
 * - Optional: Zeitplan (cron_expression für spätere Automatisierung)
 */
class ExportJobService
{
    public function __construct(
        private readonly JsonFormatExporter $jsonExporter,
        private readonly ExportProfileService $profileService,
        private readonly ImportFormatExporter $importFormatExporter,
        private readonly ExportDeliveryService $deliveryService,
        private readonly OfflineCatalogExportService $offlineCatalogService,
        private readonly ReportService $reportService,
        private readonly PdfTemplateService $pdfTemplateService,
    ) {}

    /**
     * Führt einen Export-Job aus.
     *
     * @return array{path: string, format: string, size: int, duration: float}
     */
    public function execute(ExportJob $job, ?string $outputDir = null): array
    {
        $startTime = microtime(true);
        $outputDir = $outputDir ?? storage_path('app/exports');

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        Log::channel('export')->info("Export-Job gestartet: {$job->name}", [
            'job_id' => $job->id,
            'format' => $job->format,
            'filters' => $job->filters,
        ]);

        $job->update([
            'last_status' => 'running',
            'last_run_at' => now(),
            'last_error' => null,
        ]);

        // Log-Eintrag erstellen
        $logEntry = ExportJobLog::create([
            'export_job_id' => $job->id,
            'status' => 'running',
            'started_at' => now(),
            'meta' => [
                'format' => $job->format,
                'sections' => $job->sections,
                'filters' => $job->filters,
            ],
        ]);

        try {
            $result = match ($job->format) {
                'json' => $this->executeJsonExport($job, $outputDir),
                'excel' => $this->executeExcelExport($job, $outputDir),
                'offline_catalog' => $this->executeOfflineCatalogExport($job),
                'report' => $this->executeReportExport($job, $outputDir),
                'pdf_template' => $this->executePdfTemplateExport($job, $outputDir),
                default => $this->executeViaProfile($job, $outputDir),
            };

            $duration = round(microtime(true) - $startTime, 2);

            // Delivery ausführen
            $delivery = $this->deliveryService->deliver($job, $result['path']);

            $job->update([
                'last_status' => 'completed',
                'last_duration_seconds' => $duration,
                'last_output_path' => $result['path'],
                'last_result' => [
                    'format' => $result['format'],
                    'size_bytes' => $result['size'],
                    'duration_seconds' => $duration,
                    'delivery_status' => $delivery['status'],
                ],
            ]);

            // Log-Eintrag abschließen
            $logEntry->update([
                'status' => 'completed',
                'finished_at' => now(),
                'duration_seconds' => $duration,
                'output_path' => $result['path'],
                'file_size_bytes' => $result['size'],
                'delivery_status' => $delivery['status'] !== 'skipped' ? $delivery['status'] : null,
                'delivery_error' => $delivery['error'],
            ]);

            // next_run_at berechnen
            $this->updateNextRunAt($job);

            // Log-Retention: max 50 Einträge pro Job
            $this->pruneOldLogs($job);

            Log::channel('export')->info("Export-Job abgeschlossen: {$job->name}", [
                'job_id' => $job->id,
                'path' => $result['path'],
                'size' => $result['size'],
                'duration' => $duration,
                'delivery' => $delivery['status'],
            ]);

            return array_merge($result, ['duration' => $duration, 'delivery' => $delivery]);
        } catch (\Throwable $e) {
            $duration = round(microtime(true) - $startTime, 2);

            $job->update([
                'last_status' => 'failed',
                'last_duration_seconds' => $duration,
                'last_error' => $e->getMessage(),
            ]);

            $logEntry->update([
                'status' => 'failed',
                'finished_at' => now(),
                'duration_seconds' => $duration,
                'error' => $e->getMessage(),
            ]);

            // next_run_at auch bei Fehler berechnen
            $this->updateNextRunAt($job);
            $this->pruneOldLogs($job);

            Log::channel('export')->error("Export-Job fehlgeschlagen: {$job->name}", [
                'job_id' => $job->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Berechnet next_run_at aus der cron_expression.
     */
    public function updateNextRunAt(ExportJob $job): void
    {
        if ($job->cron_expression && $job->is_active) {
            try {
                $cron = new CronExpression($job->cron_expression);
                $job->update(['next_run_at' => $cron->getNextRunDate()]);
            } catch (\Throwable $e) {
                Log::channel('export')->warning("Ungültige Cron-Expression: {$job->cron_expression}", [
                    'job_id' => $job->id,
                ]);
            }
        } else {
            $job->update(['next_run_at' => null]);
        }
    }

    /**
     * Behält max. 50 Log-Einträge pro Job.
     */
    private function pruneOldLogs(ExportJob $job): void
    {
        $idsToKeep = ExportJobLog::where('export_job_id', $job->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->pluck('id');

        ExportJobLog::where('export_job_id', $job->id)
            ->whereNotIn('id', $idsToKeep)
            ->delete();
    }

    /**
     * JSON-Export mit dem JsonFormatExporter.
     */
    private function executeJsonExport(ExportJob $job, string $outputDir): array
    {
        $filters = $this->resolveFilters($job);
        $sections = $job->sections ?? [];

        $fileName = $this->buildFileName($job, 'json');
        $outputPath = "{$outputDir}/{$fileName}";

        $this->jsonExporter->exportToFile($outputPath, $sections, $filters);

        return [
            'path' => $outputPath,
            'format' => 'json',
            'size' => (int) filesize($outputPath),
        ];
    }

    /**
     * Excel-Export im 14-Sheet-Import-Format.
     *
     * Hinweis: Der ImportFormatExporter unterstützt aktuell keine Filter.
     * Es werden immer alle Daten exportiert.
     */
    private function executeExcelExport(ExportJob $job, string $outputDir): array
    {
        $filters = $this->resolveFilters($job);
        if (!empty($filters)) {
            Log::channel('export')->warning("Excel-Export ignoriert Filter (nicht unterstützt)", [
                'job_id' => $job->id,
                'filters' => $filters,
            ]);
        }

        $fileName = $this->buildFileName($job, 'xlsx');
        $outputPath = "{$outputDir}/{$fileName}";

        $this->importFormatExporter->generate($outputPath);

        return [
            'path' => $outputPath,
            'format' => 'excel',
            'size' => (int) filesize($outputPath),
        ];
    }

    /**
     * Export via ExportProfile (csv, xml, etc.).
     */
    private function executeViaProfile(ExportJob $job, string $outputDir): array
    {
        $profile = $job->exportProfile;
        if (!$profile) {
            // Temporäres Profil erstellen
            $profile = new ExportProfile([
                'name' => $job->name,
                'format' => $job->format,
                'include_products' => true,
                'include_attributes' => true,
                'include_prices' => true,
                'include_media' => true,
                'include_relations' => true,
                'include_variants' => true,
                'include_hierarchies' => true,
            ]);

            if ($job->search_profile_id) {
                $profile->search_profile_id = $job->search_profile_id;
            }
        }

        // Override format from job
        $profile->format = $job->format;

        $fileName = $this->buildFileName($job, $this->getExtension($job->format));
        $response = $this->profileService->execute($profile, pathinfo($fileName, PATHINFO_FILENAME));

        $outputPath = "{$outputDir}/{$fileName}";

        // Response-Content in Datei schreiben
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        file_put_contents($outputPath, $content);

        return [
            'path' => $outputPath,
            'format' => $job->format,
            'size' => (int) filesize($outputPath),
        ];
    }

    /**
     * Offline-Katalog-Export (ZIP mit index.html + Daten).
     */
    private function executeOfflineCatalogExport(ExportJob $job): array
    {
        $lang = $job->filters['lang'] ?? 'de';
        $templateId = $job->catalog_template_id;

        $result = $this->offlineCatalogService->generate($lang, $templateId);

        if ($result['cancelled'] || !$result['path']) {
            throw new \RuntimeException($result['cancelled'] ? 'Export abgebrochen.' : 'Keine aktiven Produkte gefunden.');
        }

        return [
            'path' => $result['path'],
            'format' => 'offline_catalog',
            'size' => (int) ($result['file_size'] ?? filesize($result['path'])),
        ];
    }

    /**
     * Bericht-Export via ReportService.
     */
    private function executeReportExport(ExportJob $job, string $outputDir): array
    {
        $template = ReportTemplate::findOrFail($job->report_template_id);
        $searchProfile = $job->searchProfile ?? $template->searchProfile;
        $format = $job->output_format ?: $template->format ?: 'pdf';

        $result = $this->reportService->execute($template, $searchProfile, $outputDir, $format);

        return [
            'path' => $result['path'],
            'format' => 'report',
            'size' => (int) $result['size'],
        ];
    }

    /**
     * PDF-Vorlagen-Export via PdfTemplateService.
     */
    private function executePdfTemplateExport(ExportJob $job, string $outputDir): array
    {
        $template = PdfTemplate::findOrFail($job->pdf_template_id);
        $outputFormat = $job->output_format ?: 'pdf';

        // Load filtered products
        $query = Product::where('status', 'active')->where('product_type_ref', 'product');
        $filters = $this->resolveFilters($job);
        if (!empty($filters['search_text'])) {
            $search = $filters['search_text'];
            $query->where(function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }
        $products = $query->orderBy('sku')->limit(500)->get();

        if ($products->isEmpty()) {
            throw new \RuntimeException('Keine aktiven Produkte gefunden.');
        }

        $fileName = $this->buildFileName($job, match ($outputFormat) {
            'docx' => 'docx',
            'indesign' => 'zip',
            default => 'pdf',
        });
        $outputPath = "{$outputDir}/{$fileName}";

        $result = match ($outputFormat) {
            'docx' => $this->pdfTemplateService->generateDocxForProducts($template, $products, 'combined'),
            'indesign' => $this->pdfTemplateService->generateInDesignForProducts($template, $products),
            default => $this->pdfTemplateService->generateForProducts($template, $products, 'combined'),
        };

        // Move generated file to outputDir
        if (isset($result['path']) && file_exists($result['path'])) {
            rename($result['path'], $outputPath);
        } else {
            throw new \RuntimeException('PDF-Vorlagen-Export fehlgeschlagen.');
        }

        return [
            'path' => $outputPath,
            'format' => 'pdf_template',
            'size' => (int) filesize($outputPath),
        ];
    }

    /**
     * Löst Filter auf: direkte Filter oder via SearchProfile.
     */
    public function resolveFilters(ExportJob $job): array
    {
        $filters = $job->filters ?? [];

        // Wenn ein SearchProfile verknüpft ist, dessen Filter integrieren
        $searchProfile = $job->searchProfile;
        if ($searchProfile) {
            if ($searchProfile->status_filter && !isset($filters['status'])) {
                $filters['status'] = $searchProfile->status_filter;
            }
            if ($searchProfile->search_text && !isset($filters['search_text'])) {
                $filters['search_text'] = $searchProfile->search_text;
            }
            if (!empty($searchProfile->category_ids) && !isset($filters['category_ids'])) {
                $filters['category_ids'] = $searchProfile->category_ids;
            }
        }

        return $filters;
    }

    private function buildFileName(ExportJob $job, string $extension): string
    {
        $slug = preg_replace('/[^a-zA-Z0-9_-]/', '_', $job->name);
        $date = now()->format('Y-m-d_His');
        return "{$slug}_{$date}.{$extension}";
    }

    private function getExtension(string $format): string
    {
        return match ($format) {
            'csv' => 'csv',
            'json' => 'json',
            'xml' => 'xml',
            default => 'xlsx',
        };
    }
}

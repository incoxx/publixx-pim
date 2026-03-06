<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Models\ExportJob;
use App\Models\ExportProfile;
use App\Models\SearchProfile;
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

        try {
            $result = match ($job->format) {
                'json' => $this->executeJsonExport($job, $outputDir),
                'excel' => $this->executeExcelExport($job, $outputDir),
                default => $this->executeViaProfile($job, $outputDir),
            };

            $duration = round(microtime(true) - $startTime, 2);

            $job->update([
                'last_status' => 'completed',
                'last_duration_seconds' => $duration,
                'last_output_path' => $result['path'],
                'last_result' => [
                    'format' => $result['format'],
                    'size_bytes' => $result['size'],
                    'duration_seconds' => $duration,
                ],
            ]);

            Log::channel('export')->info("Export-Job abgeschlossen: {$job->name}", [
                'job_id' => $job->id,
                'path' => $result['path'],
                'size' => $result['size'],
                'duration' => $duration,
            ]);

            return array_merge($result, ['duration' => $duration]);
        } catch (\Throwable $e) {
            $duration = round(microtime(true) - $startTime, 2);

            $job->update([
                'last_status' => 'failed',
                'last_duration_seconds' => $duration,
                'last_error' => $e->getMessage(),
            ]);

            Log::channel('export')->error("Export-Job fehlgeschlagen: {$job->name}", [
                'job_id' => $job->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
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
            'size' => filesize($outputPath),
        ];
    }

    /**
     * Excel-Export im 14-Sheet-Import-Format.
     */
    private function executeExcelExport(ExportJob $job, string $outputDir): array
    {
        $fileName = $this->buildFileName($job, 'xlsx');
        $outputPath = "{$outputDir}/{$fileName}";

        $this->importFormatExporter->generate($outputPath);

        return [
            'path' => $outputPath,
            'format' => 'excel',
            'size' => filesize($outputPath),
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
            'size' => filesize($outputPath),
        ];
    }

    /**
     * Löst Filter auf: direkte Filter oder via SearchProfile.
     */
    private function resolveFilters(ExportJob $job): array
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

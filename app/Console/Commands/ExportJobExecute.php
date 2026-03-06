<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ExportJob;
use App\Services\Export\ExportJobService;
use Illuminate\Console\Command;

/**
 * CLI-Befehl zur Steuerung von Export-Jobs.
 *
 * Nutzung:
 *   php artisan pim:export-job {job-id}                    — Job ausführen
 *   php artisan pim:export-job --list                       — Alle Jobs anzeigen
 *   php artisan pim:export-job --create                     — Neuen Job interaktiv anlegen
 *   php artisan pim:export-job --run-scheduled              — Alle fälligen Jobs ausführen
 *
 * Beispiel:
 *   php artisan pim:export-job --create \
 *     --name="Elektrowerkzeuge Export" \
 *     --format=json \
 *     --filter-status=active \
 *     --filter-product-type=elektrowerkzeug
 */
class ExportJobExecute extends Command
{
    protected $signature = 'pim:export-job
        {job? : ID des Export-Jobs}
        {--list : Alle Export-Jobs anzeigen}
        {--create : Neuen Export-Job anlegen}
        {--run-scheduled : Alle fälligen Jobs ausführen}
        {--name= : Name des neuen Jobs}
        {--format=json : Format (json/excel/csv/xml)}
        {--filter-status= : Produkt-Status filtern}
        {--filter-product-type= : Produkttyp filtern}
        {--filter-search= : Freitextsuche}
        {--sections= : Kommaseparierte Sektionen (nur für JSON)}
        {--output-dir= : Ausgabeverzeichnis}';

    protected $description = 'Export-Jobs verwalten und ausführen';

    public function handle(ExportJobService $jobService): int
    {
        if ($this->option('list')) {
            return $this->listJobs();
        }

        if ($this->option('create')) {
            return $this->createJob();
        }

        if ($this->option('run-scheduled')) {
            return $this->runScheduled($jobService);
        }

        $jobId = $this->argument('job');
        if (!$jobId) {
            $this->error('Bitte eine Job-ID angeben oder --list / --create / --run-scheduled verwenden.');
            return self::FAILURE;
        }

        return $this->executeJob($jobId, $jobService);
    }

    private function listJobs(): int
    {
        $jobs = ExportJob::orderBy('name')->get();

        if ($jobs->isEmpty()) {
            $this->warn('Keine Export-Jobs vorhanden.');
            $this->line('Nutze --create um einen neuen Job anzulegen.');
            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'Format', 'Status', 'Letzte Ausführung', 'Aktiv'],
            $jobs->map(fn ($j) => [
                substr($j->id, 0, 8) . '...',
                $j->name,
                $j->format,
                $j->last_status ?? '-',
                $j->last_run_at?->format('d.m.Y H:i') ?? '-',
                $j->is_active ? 'Ja' : 'Nein',
            ])->toArray(),
        );

        return self::SUCCESS;
    }

    private function createJob(): int
    {
        $name = $this->option('name') ?? $this->ask('Job-Name');
        if (!$name) {
            $this->error('Name ist erforderlich.');
            return self::FAILURE;
        }

        $format = $this->option('format');

        $filters = array_filter([
            'status' => $this->option('filter-status'),
            'product_type' => $this->option('filter-product-type'),
            'search_text' => $this->option('filter-search'),
        ]);

        $sections = null;
        if ($sectionsOpt = $this->option('sections')) {
            $sections = array_map('trim', explode(',', $sectionsOpt));
        }

        $job = ExportJob::create([
            'name' => $name,
            'format' => $format,
            'filters' => !empty($filters) ? $filters : null,
            'sections' => $sections,
            'is_active' => true,
            'is_shared' => true,
        ]);

        $this->info("Export-Job erstellt: {$job->name}");
        $this->line("  ID:     {$job->id}");
        $this->line("  Format: {$job->format}");
        if (!empty($filters)) {
            $this->line("  Filter: " . json_encode($filters, JSON_UNESCAPED_UNICODE));
        }

        if ($this->confirm('Job jetzt ausführen?', true)) {
            return $this->executeJob($job->id, app(ExportJobService::class));
        }

        return self::SUCCESS;
    }

    private function executeJob(string $jobId, ExportJobService $jobService): int
    {
        $job = ExportJob::find($jobId);
        if (!$job) {
            $this->error("Export-Job '{$jobId}' nicht gefunden.");
            return self::FAILURE;
        }

        $this->info("Export-Job wird ausgeführt: {$job->name}");
        $this->line("  Format:  {$job->format}");
        $this->line("  Filter:  " . json_encode($job->filters ?? [], JSON_UNESCAPED_UNICODE));

        $outputDir = $this->option('output-dir') ?? storage_path('app/exports');

        try {
            $result = $jobService->execute($job, $outputDir);

            $this->newLine();
            $this->info('Export abgeschlossen!');
            $this->line("  Datei:  {$result['path']}");
            $this->line("  Größe:  " . $this->formatBytes($result['size']));
            $this->line("  Dauer:  {$result['duration']}s");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Export fehlgeschlagen: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    private function runScheduled(ExportJobService $jobService): int
    {
        $jobs = ExportJob::scheduled()
            ->where(function ($q) {
                $q->whereNull('next_run_at')
                  ->orWhere('next_run_at', '<=', now());
            })
            ->get();

        if ($jobs->isEmpty()) {
            $this->info('Keine fälligen Export-Jobs.');
            return self::SUCCESS;
        }

        $this->info("Führe {$jobs->count()} fällige Export-Jobs aus...");

        $failed = 0;
        foreach ($jobs as $job) {
            $this->line("  → {$job->name} ({$job->format})");
            try {
                $jobService->execute($job);
                $this->info("    ✓ Abgeschlossen");
            } catch (\Throwable $e) {
                $this->error("    ✗ Fehlgeschlagen: {$e->getMessage()}");
                $failed++;
            }
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}

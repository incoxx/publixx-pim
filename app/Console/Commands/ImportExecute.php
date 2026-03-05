<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ImportJob;
use App\Models\ImportProfile;
use App\Models\User;
use App\Services\Import\ImportService;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;

/**
 * CLI-Befehl zum Ausführen eines Imports.
 *
 * Nutzung:
 *   php artisan pim:import /pfad/zur/datei.xlsx [--profile=profil-id] [--user=user-id] [--force]
 *   php artisan pim:import --list-profiles
 *   php artisan pim:import --list-jobs
 *
 * Per CURL/REST:
 *   curl -X POST /api/v1/imports -F "file=@datei.xlsx" -H "Authorization: Bearer {token}"
 *   curl -X POST /api/v1/imports/{id}/execute -H "Authorization: Bearer {token}"
 */
class ImportExecute extends Command
{
    protected $signature = 'pim:import
        {file? : Pfad zur Excel-Datei}
        {--profile= : ID des Import-Profils (für Mapping)}
        {--user= : User-ID für den Import (Standard: erster Admin)}
        {--force : Import trotz Validierungsfehler ausführen}
        {--list-profiles : Alle Import-Profile anzeigen}
        {--list-jobs : Letzte Import-Jobs anzeigen}';

    protected $description = 'Import ausführen (CLI/Cron/Batch)';

    public function handle(ImportService $importService): int
    {
        if ($this->option('list-profiles')) {
            return $this->listProfiles();
        }

        if ($this->option('list-jobs')) {
            return $this->listJobs();
        }

        $filePath = $this->argument('file');
        if (!$filePath) {
            $this->error('Bitte eine Datei angeben oder --list-profiles / --list-jobs verwenden.');
            return self::FAILURE;
        }

        if (!file_exists($filePath)) {
            $this->error("Datei nicht gefunden: {$filePath}");
            return self::FAILURE;
        }

        // User ermitteln
        $userId = $this->option('user');
        if (!$userId) {
            $user = User::first();
            if (!$user) {
                $this->error('Kein Benutzer gefunden. Bitte --user angeben.');
                return self::FAILURE;
            }
            $userId = $user->id;
        }

        $this->info("Import wird gestartet: {$filePath}");

        // Upload simulieren
        $uploadedFile = new UploadedFile(
            $filePath,
            basename($filePath),
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );

        // Phase 1+2: Upload + Validation
        $importJob = $importService->upload($uploadedFile, $userId);

        $this->info("Import-Job erstellt: {$importJob->id}");
        $this->info("Status: {$importJob->status}");
        $this->info("Sheets: " . implode(', ', $importJob->sheets_found ?? []));

        // Fehler anzeigen
        $errors = $importJob->errors()->get();
        if ($errors->isNotEmpty()) {
            $this->warn("{$errors->count()} Validierungsfehler:");
            $this->table(
                ['Sheet', 'Zeile', 'Spalte', 'Fehler', 'Vorschlag'],
                $errors->take(20)->map(fn($e) => [
                    $e->sheet,
                    $e->row,
                    $e->column,
                    mb_substr($e->error, 0, 60),
                    mb_substr($e->suggestion ?? '', 0, 40),
                ])->toArray(),
            );

            if ($errors->count() > 20) {
                $this->warn("... und " . ($errors->count() - 20) . " weitere Fehler.");
            }

            if (!$this->option('force')) {
                $this->error('Import abgebrochen. Verwende --force um trotzdem auszuführen.');
                return self::FAILURE;
            }

            $this->warn('--force aktiv: Import wird trotz Fehler ausgeführt.');
        }

        // Phase 3: Ausführung
        $this->info('Import wird ausgeführt...');
        $importJob = $importService->execute($importJob, (bool) $this->option('force'));

        $this->info("Status: {$importJob->fresh()->status}");

        if ($importJob->fresh()->status === 'completed') {
            $result = $importJob->fresh()->result;
            if (is_array($result)) {
                $this->info('Ergebnis:');
                foreach ($result as $key => $value) {
                    if (is_scalar($value)) {
                        $this->line("  {$key}: {$value}");
                    }
                }
            }
            $this->info('Import erfolgreich abgeschlossen.');
            return self::SUCCESS;
        }

        $this->error('Import fehlgeschlagen.');
        return self::FAILURE;
    }

    private function listProfiles(): int
    {
        $profiles = ImportProfile::orderBy('name')->get();

        if ($profiles->isEmpty()) {
            $this->warn('Keine Import-Profile vorhanden.');
            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'Produkttyp', 'SKU-Spalte', 'Mappings', 'Geteilt'],
            $profiles->map(fn($p) => [
                $p->id,
                $p->name,
                $p->productType?->name_de ?? '-',
                $p->sku_column,
                count($p->column_mappings ?? []),
                $p->is_shared ? 'Ja' : 'Nein',
            ])->toArray(),
        );

        return self::SUCCESS;
    }

    private function listJobs(): int
    {
        $jobs = ImportJob::orderByDesc('created_at')->limit(20)->get();

        if ($jobs->isEmpty()) {
            $this->warn('Keine Import-Jobs vorhanden.');
            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Datei', 'Status', 'Erstellt', 'Abgeschlossen'],
            $jobs->map(fn($j) => [
                mb_substr($j->id, 0, 8) . '...',
                $j->file_name,
                $j->status,
                $j->created_at->format('d.m.Y H:i'),
                $j->completed_at?->format('d.m.Y H:i') ?? '-',
            ])->toArray(),
        );

        return self::SUCCESS;
    }
}

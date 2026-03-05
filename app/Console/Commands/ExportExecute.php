<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ExportProfile;
use App\Services\Export\ExportProfileService;
use Illuminate\Console\Command;

/**
 * CLI-Befehl zum Ausführen eines Export-Profils.
 *
 * Nutzung:
 *   php artisan pim:export {profile-id} [--file-name=mein-export] [--format=csv]
 *   php artisan pim:export --list
 *
 * Per CURL/REST:
 *   curl -X POST /api/v1/export-profiles/{id}/execute -H "Authorization: Bearer {token}"
 */
class ExportExecute extends Command
{
    protected $signature = 'pim:export
        {profile? : ID des Export-Profils}
        {--list : Alle verfügbaren Export-Profile anzeigen}
        {--file-name= : Dateiname für den Export (ohne Endung)}
        {--format= : Format überschreiben (excel, csv, json, xml)}
        {--output-dir= : Ausgabeverzeichnis (Standard: storage/app/exports)}';

    protected $description = 'Export-Profil ausführen (CLI/Cron/Batch)';

    public function handle(ExportProfileService $exportService): int
    {
        if ($this->option('list')) {
            return $this->listProfiles();
        }

        $profileId = $this->argument('profile');
        if (!$profileId) {
            $this->error('Bitte eine Profil-ID angeben oder --list verwenden.');
            return self::FAILURE;
        }

        $profile = ExportProfile::find($profileId);
        if (!$profile) {
            $this->error("Export-Profil '{$profileId}' nicht gefunden.");
            return self::FAILURE;
        }

        // Format überschreiben
        if ($format = $this->option('format')) {
            if (!in_array($format, ['excel', 'csv', 'json', 'xml'])) {
                $this->error("Ungültiges Format: {$format}. Erlaubt: excel, csv, json, xml");
                return self::FAILURE;
            }
            $profile->format = $format;
        }

        $fileName = $this->option('file-name') ?? $profile->file_name_template ?? 'export-' . now()->format('Y-m-d');
        $outputDir = $this->option('output-dir') ?? storage_path('app/exports');

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $this->info("Export wird ausgeführt: {$profile->name} ({$profile->format})");

        $extension = match ($profile->format) {
            'csv' => 'csv',
            'json' => 'json',
            'xml' => 'xml',
            default => 'xlsx',
        };

        // Für CLI: Daten direkt in Datei schreiben statt StreamedResponse
        $products = $this->callPrivateMethod($exportService, $profile);
        $outputPath = "{$outputDir}/{$fileName}.{$extension}";

        $this->info("Schreibe nach: {$outputPath}");

        $response = $exportService->execute($profile, $fileName);

        // Response-Content in Datei umleiten
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        file_put_contents($outputPath, $content);

        $this->info("Export abgeschlossen: {$outputPath}");
        $this->info('Dateigröße: ' . $this->formatBytes(filesize($outputPath)));

        return self::SUCCESS;
    }

    private function listProfiles(): int
    {
        $profiles = ExportProfile::orderBy('name')->get();

        if ($profiles->isEmpty()) {
            $this->warn('Keine Export-Profile vorhanden.');
            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'Format', 'Geteilt', 'Erstellt'],
            $profiles->map(fn($p) => [
                $p->id,
                $p->name,
                $p->format,
                $p->is_shared ? 'Ja' : 'Nein',
                $p->created_at->format('d.m.Y H:i'),
            ])->toArray(),
        );

        return self::SUCCESS;
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

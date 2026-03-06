<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Import\JsonFormatImporter;
use Illuminate\Console\Command;

/**
 * CLI-Befehl für den JSON-Import von PIM-Daten.
 *
 * Nutzung:
 *   php artisan pim:json-import /pfad/zur/datei.json
 *   php artisan pim:json-import /pfad/zur/datei.json --mode=delete_insert
 *   php artisan pim:json-import /pfad/zur/datei.json --validate-only
 *   cat data.json | php artisan pim:json-import --stdin
 */
class JsonImport extends Command
{
    protected $signature = 'pim:json-import
        {file? : Pfad zur JSON-Datei}
        {--mode=update : Import-Modus (update/delete_insert)}
        {--validate-only : Nur validieren, nicht importieren}
        {--stdin : JSON von STDIN lesen}';

    protected $description = 'PIM-Daten aus JSON-Datei importieren';

    public function handle(JsonFormatImporter $importer): int
    {
        // JSON-Quelle bestimmen
        if ($this->option('stdin')) {
            $json = file_get_contents('php://stdin');
        } else {
            $filePath = $this->argument('file');
            if (!$filePath) {
                $this->error('Bitte eine Datei angeben oder --stdin verwenden.');
                return self::FAILURE;
            }

            if (!file_exists($filePath)) {
                $this->error("Datei nicht gefunden: {$filePath}");
                return self::FAILURE;
            }

            $json = file_get_contents($filePath);
        }

        $data = json_decode($json, true);
        if ($data === null) {
            $this->error('Ungültiges JSON: ' . json_last_error_msg());
            return self::FAILURE;
        }

        // Meta-Informationen anzeigen
        if (isset($data['_meta'])) {
            $meta = $data['_meta'];
            $this->info('JSON-Import-Datei:');
            $this->line("  Format:     {$meta['format']}");
            $this->line("  Version:    {$meta['version']}");
            $this->line("  Exportiert: {$meta['exported_at']}");
            $this->line("  Sektionen:  " . implode(', ', $meta['sections'] ?? []));
            $this->newLine();
        }

        // Validierung
        $validation = $importer->validate($data);

        $this->info("Gefundene Sektionen: " . implode(', ', $validation['sections']));

        if (!$validation['valid']) {
            $this->error('Validierungsfehler:');
            foreach ($validation['errors'] as $error) {
                $this->line("  - {$error}");
            }
            return self::FAILURE;
        }

        $this->info('Validierung: OK');

        if ($this->option('validate-only')) {
            return self::SUCCESS;
        }

        // Import ausführen
        $mode = $this->option('mode');
        $importer->setMode($mode);

        $this->info("Import-Modus: {$mode}");
        $this->info('Import wird ausgeführt...');
        $this->newLine();

        $result = $importer->importData($data);

        // Ergebnis anzeigen
        $this->info('Import abgeschlossen!');
        $this->line("  Dauer: {$result->durationSeconds}s");
        $this->newLine();

        $headers = ['Sektion', 'Erstellt', 'Aktualisiert', 'Übersprungen', 'Fehler'];
        $rows = [];
        foreach ($result->stats as $section => $stats) {
            $rows[] = [
                $section,
                $stats['created'] ?? 0,
                $stats['updated'] ?? 0,
                $stats['skipped'] ?? 0,
                $stats['errors'] ?? 0,
            ];
        }
        $this->table($headers, $rows);

        if (!empty($result->skippedDetails)) {
            $this->newLine();
            $this->warn('Übersprungene Zeilen:');
            foreach (array_slice($result->skippedDetails, 0, 20) as $detail) {
                $this->line("  [{$detail['sheet']}] Zeile {$detail['row']}: {$detail['reason']}");
            }
            if (count($result->skippedDetails) > 20) {
                $this->line('  ... und ' . (count($result->skippedDetails) - 20) . ' weitere');
            }
        }

        $this->newLine();
        $this->info("Betroffene Produkte: " . count($result->affectedProductIds));

        return $result->totalErrors() > 0 ? self::FAILURE : self::SUCCESS;
    }
}

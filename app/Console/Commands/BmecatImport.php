<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Import\BmecatFormatImporter;
use Illuminate\Console\Command;

/**
 * CLI-Befehl für den BMEcat-Import von PIM-Daten.
 *
 * Nutzung:
 *   php artisan pim:bmecat-import /pfad/zur/datei.xml
 *   php artisan pim:bmecat-import /pfad/zur/datei.xml --mode=update
 *   php artisan pim:bmecat-import /pfad/zur/datei.xml --product-type=existing_type
 *   php artisan pim:bmecat-import /pfad/zur/datei.xml --validate-only
 */
class BmecatImport extends Command
{
    protected $signature = 'pim:bmecat-import
        {file : Pfad zur BMEcat-XML-Datei}
        {--mode=update : Import-Modus (update/delete_insert)}
        {--product-type= : Produkttyp (technical_name), Standard: bmecat_product}
        {--validate-only : Nur validieren, nicht importieren}';

    protected $description = 'PIM-Daten aus BMEcat-XML-Datei importieren (1.2 und 2005)';

    public function handle(BmecatFormatImporter $importer): int
    {
        $filePath = $this->argument('file');

        if (!file_exists($filePath)) {
            $this->error("Datei nicht gefunden: {$filePath}");
            return self::FAILURE;
        }

        $xml = file_get_contents($filePath);

        // Version anzeigen
        $version = $importer->detectVersion($xml);
        $this->info("BMEcat-Version: {$version}");
        $this->newLine();

        // Validierung
        $validation = $importer->validate($xml);

        $this->info("Version:          {$validation['version']}");
        $this->info("Transaktionstyp:  {$validation['transaction_type']}");
        $this->info("Produkte:         {$validation['product_count']}");
        $this->newLine();

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
        $productType = $this->option('product-type') ?: null;
        $importer->setMode($mode);

        $this->info("Import-Modus:     {$mode}");
        $this->info("Produkttyp:       " . ($productType ?? 'bmecat_product'));
        $this->info('Import wird ausgeführt...');
        $this->newLine();

        try {
            $result = $importer->importFromString($xml, $productType);
        } catch (\Throwable $e) {
            $this->error('Import fehlgeschlagen: ' . $e->getMessage());
            return self::FAILURE;
        }

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

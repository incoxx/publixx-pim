<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Export\JsonFormatExporter;
use Illuminate\Console\Command;

/**
 * CLI-Befehl für den JSON-Export aller PIM-Daten.
 *
 * Nutzung:
 *   php artisan pim:json-export                          — Vollexport
 *   php artisan pim:json-export --sections=products,prices
 *   php artisan pim:json-export --status=active --product-type=elektrowerkzeug
 *   php artisan pim:json-export --output=/tmp/export.json
 *   php artisan pim:json-export --sections-list          — Verfügbare Sektionen anzeigen
 */
class JsonExport extends Command
{
    protected $signature = 'pim:json-export
        {--output= : Ausgabedatei (Standard: storage/app/exports/pim-export-{datum}.json)}
        {--sections= : Kommaseparierte Liste der zu exportierenden Sektionen}
        {--sections-list : Verfügbare Sektionen anzeigen}
        {--status= : Produkt-Status filtern (draft/active/inactive)}
        {--product-type= : Produkttyp filtern}
        {--search= : Freitext-Suche in SKU, Name, EAN}
        {--updated-after= : Nur Produkte aktualisiert nach Datum (Y-m-d)}
        {--compact : Kompaktes JSON ohne Pretty-Print}';

    protected $description = 'PIM-Daten als JSON exportieren (alle Entitäten in Abhängigkeitsreihenfolge)';

    public function handle(JsonFormatExporter $exporter): int
    {
        if ($this->option('sections-list')) {
            $this->info('Verfügbare Sektionen:');
            foreach (JsonFormatExporter::availableSections() as $i => $section) {
                $this->line(sprintf('  %2d. %s', $i + 1, $section));
            }
            return self::SUCCESS;
        }

        // Sektionen parsen
        $sections = [];
        if ($sectionsOpt = $this->option('sections')) {
            $sections = array_map('trim', explode(',', $sectionsOpt));
            $valid = JsonFormatExporter::availableSections();
            $invalid = array_diff($sections, $valid);
            if (!empty($invalid)) {
                $this->error('Unbekannte Sektionen: ' . implode(', ', $invalid));
                $this->line('Nutze --sections-list für verfügbare Sektionen.');
                return self::FAILURE;
            }
        }

        // Filter zusammenbauen
        $filters = array_filter([
            'status' => $this->option('status'),
            'product_type' => $this->option('product-type'),
            'search_text' => $this->option('search'),
            'updated_after' => $this->option('updated-after'),
        ]);

        // Output-Pfad bestimmen
        $outputDir = storage_path('app/exports');
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $outputPath = $this->option('output')
            ?? "{$outputDir}/pim-export-" . now()->format('Y-m-d_His') . '.json';

        $this->info('JSON-Export wird gestartet...');
        if (!empty($sections)) {
            $this->line('  Sektionen: ' . implode(', ', $sections));
        }
        if (!empty($filters)) {
            $this->line('  Filter: ' . json_encode($filters, JSON_UNESCAPED_UNICODE));
        }

        $startTime = microtime(true);

        $exporter->exportToFile($outputPath, $sections, $filters);

        $duration = round(microtime(true) - $startTime, 2);
        $size = $this->formatBytes((int) filesize($outputPath));

        $this->newLine();
        $this->info("Export abgeschlossen!");
        $this->line("  Datei: {$outputPath}");
        $this->line("  Größe: {$size}");
        $this->line("  Dauer: {$duration}s");

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

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Content\ContentConfigService;
use Illuminate\Console\Command;

class ContentConfigImport extends Command
{
    protected $signature = 'pim:content-config-import
        {file : Pfad zur JSON-Bundle-Datei}
        {--sections= : Teilmengen (kommagetrennt): content_types,section_types,product_widgets,navigations}';

    protected $description = 'Content-Konfiguration aus JSON-Bundle importieren (idempotenter Upsert per technical_name)';

    public function handle(ContentConfigService $service): int
    {
        $file = $this->argument('file');
        if (! is_file($file)) {
            $this->error("Datei nicht gefunden: {$file}");

            return self::FAILURE;
        }

        $bundle = json_decode((string) file_get_contents($file), true);
        if (! is_array($bundle)) {
            $this->error('Ungültiges JSON.');

            return self::FAILURE;
        }

        $sections = $this->option('sections')
            ? array_values(array_filter(array_map('trim', explode(',', (string) $this->option('sections')))))
            : [];

        try {
            $summary = $service->import($bundle, $sections);
        } catch (\Throwable $e) {
            $this->error('Import fehlgeschlagen: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info('Content-Konfiguration importiert:');
        foreach ($summary as $entity => $counts) {
            $this->line("  {$entity}: " . json_encode($counts));
        }

        return self::SUCCESS;
    }
}

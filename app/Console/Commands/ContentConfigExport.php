<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Content\ContentConfigService;
use Illuminate\Console\Command;

class ContentConfigExport extends Command
{
    protected $signature = 'pim:content-config-export
        {--sections= : Teilmengen (kommagetrennt): content_types,section_types,product_widgets,navigations}
        {--o|output= : Ausgabedatei (Standard: stdout)}';

    protected $description = 'Content-Konfiguration als JSON-Bundle exportieren';

    public function handle(ContentConfigService $service): int
    {
        $sections = $this->option('sections')
            ? array_values(array_filter(array_map('trim', explode(',', (string) $this->option('sections')))))
            : [];

        $bundle = $service->export($sections);
        $json = json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $output = $this->option('output');
        if ($output) {
            file_put_contents($output, $json);
            $this->info("Export geschrieben: {$output}");
            $this->info('Größe: ' . number_format(strlen($json)) . ' Bytes');
        } else {
            $this->line($json);
        }

        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Export\BmecatFormatExporter;
use Illuminate\Console\Command;

/**
 * CLI-Befehl für den BMEcat-Export von PIM-Daten.
 *
 * Nutzung:
 *   php artisan pim:bmecat-export -o export.xml
 *   php artisan pim:bmecat-export --version=2005 --hierarchy=main -o export.xml
 *   php artisan pim:bmecat-export --version=1.2 -o legacy.xml
 */
class BmecatExport extends Command
{
    protected $signature = 'pim:bmecat-export
        {--version=2005 : BMEcat-Version (1.2 oder 2005)}
        {--hierarchy= : Hierarchie-ID}
        {--product-types= : Produkttyp-IDs (kommagetrennt)}
        {--attributes= : Attribut-IDs (kommagetrennt)}
        {--price-types= : Preistyp-IDs (kommagetrennt)}
        {--relation-types= : Beziehungstyp-IDs (kommagetrennt)}
        {-o|output= : Ausgabedatei (Standard: stdout)}';

    protected $description = 'PIM-Daten als BMEcat-XML exportieren (1.2 und 2005)';

    public function handle(BmecatFormatExporter $exporter): int
    {
        $version = $this->option('version');
        $exporter->setVersion($version);

        $this->info("BMEcat-Version: {$version}");

        if ($this->option('hierarchy')) {
            $exporter->setHierarchyId($this->option('hierarchy'));
            $this->info("Hierarchie: {$this->option('hierarchy')}");
        }

        if ($this->option('product-types')) {
            $ids = array_filter(explode(',', $this->option('product-types')));
            $exporter->setProductTypeIds($ids);
            $this->info('Produkttypen: ' . count($ids));
        }

        if ($this->option('attributes')) {
            $ids = array_filter(explode(',', $this->option('attributes')));
            $exporter->setAttributeIds($ids);
            $this->info('Attribute: ' . count($ids));
        }

        if ($this->option('price-types')) {
            $ids = array_filter(explode(',', $this->option('price-types')));
            $exporter->setPriceTypeIds($ids);
            $this->info('Preistypen: ' . count($ids));
        }

        if ($this->option('relation-types')) {
            $ids = array_filter(explode(',', $this->option('relation-types')));
            $exporter->setRelationTypeIds($ids);
            $this->info('Beziehungstypen: ' . count($ids));
        }

        $this->newLine();
        $this->info('Export wird ausgeführt...');

        try {
            $xml = $exporter->export();
        } catch (\Throwable $e) {
            $this->error('Export fehlgeschlagen: ' . $e->getMessage());
            return self::FAILURE;
        }

        $output = $this->option('output');
        if ($output) {
            file_put_contents($output, $xml);
            $this->info("Export geschrieben: {$output}");
            $this->info('Größe: ' . number_format(strlen($xml)) . ' Bytes');
        } else {
            $this->line($xml);
        }

        return self::SUCCESS;
    }
}

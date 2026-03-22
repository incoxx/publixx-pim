<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Etim\EtimImportService;
use Illuminate\Console\Command;

/**
 * CLI-Befehl für den Import von ETIM-Klassifikationsdaten.
 *
 * Nutzung:
 *   php artisan pim:etim-import 9.0 /pfad/zum/etim-verzeichnis
 */
class EtimImport extends Command
{
    protected $signature = 'pim:etim-import
        {version : ETIM-Versionsnummer (z.B. 9.0)}
        {directory : Pfad zum Verzeichnis mit ETIM-CSV-Dateien}';

    protected $description = 'ETIM-Klassifikationsdaten aus CSV-Dateien importieren';

    public function handle(EtimImportService $service): int
    {
        $version = $this->argument('version');
        $directory = $this->argument('directory');

        if (!is_dir($directory)) {
            $this->error("Verzeichnis nicht gefunden: {$directory}");
            return self::FAILURE;
        }

        $this->info("Importiere ETIM {$version} aus {$directory} ...");

        $result = $service->import($version, $directory);

        $stats = $result['stats'];
        $this->info('Import abgeschlossen:');
        $this->table(
            ['Typ', 'Anzahl'],
            [
                ['Gruppen (EG)', $stats['groups']],
                ['Klassen (EC)', $stats['classes']],
                ['Merkmale (EF)', $stats['features']],
                ['Werte (EV)', $stats['values']],
                ['Einheiten (EU)', $stats['units']],
                ['Klasse-Merkmal-Zuordnungen', $stats['class_features']],
                ['Merkmal-Wert-Zuordnungen', $stats['feature_values']],
            ]
        );

        return self::SUCCESS;
    }
}

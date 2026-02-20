<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Import\DemoTemplateGenerator;
use Illuminate\Console\Command;

class GenerateDemoImportTemplate extends Command
{
    protected $signature = 'import:demo-template
                            {--output= : Ziel-Dateipfad (Standard: storage/app/imports/demo_pim_import.xlsx)}';

    protected $description = 'Erzeugt ein Excel-Import-Template mit realistischen Demodaten für alle 14 Reiter';

    public function handle(DemoTemplateGenerator $generator): int
    {
        $outputPath = $this->option('output')
            ?? storage_path('app/imports/demo_pim_import.xlsx');

        $directory = dirname($outputPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $this->info('Erzeuge Demo-Import-Template...');

        $generator->generate($outputPath);

        $this->info("Demo-Template erfolgreich erzeugt: {$outputPath}");
        $this->newLine();
        $this->table(
            ['Sheet', 'Inhalt'],
            [
                ['01_Produkttypen',          '3 Produkttypen (Elektronik, Zubehör, Software)'],
                ['02_Attributgruppen',       '5 Attributgruppen'],
                ['03_Einheiten',             '12 Einheiten in 5 Gruppen'],
                ['04_Wertelisten',           '5 Wertelisten mit 26 Einträgen'],
                ['05_Attribute',             '19 Attribute (verschiedene Datentypen)'],
                ['06_Hierarchien',           '16 Hierarchieknoten (Master + Output)'],
                ['07_Hierarchie_Attribute',  '26 Attribut-Zuordnungen zu Kategorien'],
                ['08_Produkte',              '11 Produkte'],
                ['09_Produktwerte',          '96 Attributwerte (DE/EN, multipliable)'],
                ['10_Varianten',             '6 Varianten (Farben + Speicher)'],
                ['11_Produkt_Hierarchien',   '18 Hierarchie-Zuordnungen'],
                ['12_Produktbeziehungen',    '10 Beziehungen (Cross-Sell, Zubehör, Up-Sell)'],
                ['13_Preise',                '20 Preise (Listen-, Aktions-, Staffelpreise, EUR/USD)'],
                ['14_Medien',               '10 Medien-Zuordnungen (Bilder, PDFs, Videos)'],
            ],
        );

        return Command::SUCCESS;
    }
}

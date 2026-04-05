<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ErrorClassificationService;
use Illuminate\Console\Command;

class ClassifyErrors extends Command
{
    protected $signature = 'pim:classify-errors
        {--limit=50 : Maximale Anzahl zu verarbeitender Fehler-Gruppen}
        {--dry-run  : Nur analysieren, nichts in die DB schreiben}';

    protected $description = 'Laravel-Logs und fehlgeschlagene Queue-Jobs via KI klassifizieren';

    public function handle(ErrorClassificationService $service): int
    {
        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Dry-Run-Modus: Es werden keine Daten gespeichert.');
        }

        $this->info("Fehlerklassifikation wird gestartet (Limit: {$limit})...");

        try {
            $result = $service->parseAndClassify(limit: $limit, dryRun: $dryRun);
        } catch (\Throwable $e) {
            $this->error('Klassifikation fehlgeschlagen: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->line("  Fehler im Log gefunden : {$result['errors_found']}");
        $this->line("  Eindeutige Gruppen     : {$result['groups']}");
        $this->line("  Verarbeitet            : {$result['processed']}");

        if (! $dryRun) {
            $this->line("  KI-klassifiziert       : {$result['classified']}");
        }

        $this->newLine();
        $this->info($dryRun ? 'Dry-Run abgeschlossen.' : 'Klassifikation abgeschlossen.');

        return self::SUCCESS;
    }
}

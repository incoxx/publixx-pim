<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\IngestToTmsJob;
use Illuminate\Console\Command;

class TmsIngestCommand extends Command
{
    protected $signature = 'tms:ingest
        {--sync : Sofort im Vordergrund ausfuehren statt einzuplanen}';

    protected $description = 'Ingest all PIM metadata entities into the TMS';

    /**
     * Ohne --sync wird nur eingeplant — dann braucht es einen Queue Worker fuer
     * die 'default'-Queue (Horizon), sonst passiert nichts und niemand merkt es.
     * Mit --sync laeuft der Ingest hier und jetzt und meldet sein Ergebnis; das
     * ist der Weg, um "es wird kein Begriff uebernommen" ueberhaupt zu sehen.
     */
    public function handle(): int
    {
        if (!$this->option('sync')) {
            IngestToTmsJob::dispatch();
            $this->info('TMS ingest job dispatched.');
            $this->line('Laeuft ueber die Queue — dafuer muss Horizon die "default"-Queue abarbeiten:');
            $this->line('  sudo supervisorctl status horizon');
            $this->line('Direkt im Vordergrund ausfuehren: php artisan tms:ingest --sync');

            return self::SUCCESS;
        }

        $this->info('TMS ingest laeuft im Vordergrund — das kann bei grossem Bestand dauern...');

        /** @var array{total_sent:int, failed_batches:int, skipped:bool, message:string} $result */
        $result = IngestToTmsJob::dispatchSync();

        if (!empty($result['skipped'])) {
            $this->warn($result['message']);

            return self::FAILURE;
        }

        if (($result['failed_batches'] ?? 0) > 0) {
            $this->error($result['message']);
            $this->line('Ursache steht in storage/logs/laravel.log (Suchbegriff: "TMS ingest").');

            return self::FAILURE;
        }

        $this->info($result['message']);

        // Die Begriffe entstehen im TMS erst, wenn dessen eigener Queue Worker
        // den ProcessIngestBatchJob abarbeitet — der Ingest selbst liefert nur
        // ein 202 zurueck. Ohne diesen Hinweis sucht man den Fehler im PIM.
        $this->newLine();
        $this->line('Das TMS verarbeitet die Batches asynchron. Abdeckung pruefen mit:');
        $this->line('  php artisan tms:status');
        $this->line('Wenn die Zahl bei 0 bleibt, laeuft der TMS-Worker nicht:');
        $this->line('  sudo supervisorctl status anypim-tms-worker:*');

        return self::SUCCESS;
    }
}

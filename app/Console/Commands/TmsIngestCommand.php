<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\IngestToTmsJob;
use Illuminate\Console\Command;

class TmsIngestCommand extends Command
{
    protected $signature = 'tms:ingest';
    protected $description = 'Ingest all PIM metadata entities into the TMS';

    public function handle(): int
    {
        $this->info('Dispatching TMS ingest job...');
        IngestToTmsJob::dispatch();
        $this->info('TMS ingest job dispatched.');

        return self::SUCCESS;
    }
}

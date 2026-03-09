<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SyncTmsTranslationsJob;
use Illuminate\Console\Command;

class TmsSyncCommand extends Command
{
    protected $signature = 'tms:sync';
    protected $description = 'Sync translations from TMS back into PIM database (name_json fields)';

    public function handle(): int
    {
        $this->info('Dispatching TMS sync job...');
        SyncTmsTranslationsJob::dispatch();
        $this->info('TMS sync job dispatched.');

        return self::SUCCESS;
    }
}

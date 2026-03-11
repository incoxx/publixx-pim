<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ScheduledActionService;
use Illuminate\Console\Command;

class ProcessScheduledActions extends Command
{
    protected $signature = 'actions:process-scheduled';

    protected $description = 'Process scheduled actions that are due';

    public function handle(ScheduledActionService $service): int
    {
        $result = $service->executeDueActions();

        if ($result['activated'] === 0 && $result['failed'] === 0) {
            return self::SUCCESS;
        }

        $this->info("Activated: {$result['activated']}, Failed: {$result['failed']}");

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\NavigationNode;
use Illuminate\Console\Command;

class ContentCleanupOrphans extends Command
{
    protected $signature = 'pim:content-cleanup-orphans';

    protected $description = 'Verwaiste Navigationsknoten bereinigen (Ziel gelöscht → tote Referenz)';

    public function handle(): int
    {
        $deleted = NavigationNode::pruneOrphans();

        $this->info("Verwaiste Navigationsknoten bereinigt: {$deleted} gelöscht (Knoten mit Kindern zu Ordnern degradiert).");

        return self::SUCCESS;
    }
}

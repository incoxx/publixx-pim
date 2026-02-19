<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Jobs\WarmupCache;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * WarmupCacheListener – Reagiert auf ImportCompleted Events.
 *
 * Nach einem erfolgreichen Import werden alle importierten Produkte
 * in den Cache geladen, damit spätere Zugriffe sofort schnell sind.
 *
 * Event-Payload: $event->importJobId, $event->productIds
 */
class WarmupCacheListener implements ShouldQueue
{
    public string $queue = 'warmup';

    /**
     * Event verarbeiten.
     */
    public function handle(object $event): void
    {
        $importJobId = $event->importJobId ?? $event->import_job_id ?? 'unknown';
        $productIds = $event->productIds ?? $event->product_ids ?? [];

        if (empty($productIds)) {
            Log::info('WarmupCacheListener: No product IDs in ImportCompleted event', [
                'import_job_id' => $importJobId,
            ]);
            return;
        }

        // WarmupCache-Job dispatchen (asynchron, nach Commit)
        dispatch(new WarmupCache($productIds))->afterCommit();

        Log::info('WarmupCacheListener: Dispatched WarmupCache', [
            'import_job_id' => $importJobId,
            'product_count' => count($productIds),
        ]);
    }
}

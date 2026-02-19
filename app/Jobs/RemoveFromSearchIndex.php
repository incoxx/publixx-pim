<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RemoveFromSearchIndex – Entfernt ein Produkt aus dem denormalisierten Search-Index.
 *
 * Wird dispatcht von:
 * - ProductObserver::deleted()
 * - Event-Listener für ProductDeleted
 */
class RemoveFromSearchIndex implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public array $backoff = [5, 15, 60];

    public function __construct(
        public readonly string $productId,
    ) {
        $this->onQueue('indexing');
    }

    /**
     * Produkt aus Search-Index entfernen.
     */
    public function handle(): void
    {
        $deleted = DB::table('products_search_index')
            ->where('product_id', $this->productId)
            ->delete();

        Log::info('RemoveFromSearchIndex: Removed', [
            'product_id' => $this->productId,
            'rows_deleted' => $deleted,
        ]);
    }
}

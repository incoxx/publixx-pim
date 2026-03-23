<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Export\AttributeMappingSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Async Job: Synchronisiert Attribut-Mappings für Produkte.
 *
 * Verwendung:
 * - Einzel-Produkt: SyncAttributeMappings::dispatch(productId: $id)
 * - Batch: SyncAttributeMappings::dispatch(sourceHierarchyId: $s, targetHierarchyId: $t)
 * - Auswahl: SyncAttributeMappings::dispatch(productIds: [...], sourceHierarchyId: $s, targetHierarchyId: $t)
 */
class SyncAttributeMappings implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ?string $productId = null,
        public ?array $productIds = null,
        public ?string $sourceHierarchyId = null,
        public ?string $targetHierarchyId = null,
    ) {
        $this->onQueue('default');
    }

    public function handle(AttributeMappingSyncService $syncService): void
    {
        // Einzel-Produkt (z.B. Auto-Sync vom Observer)
        if ($this->productId && !$this->productIds) {
            $product = \App\Models\Product::find($this->productId);
            if ($product) {
                $syncService->syncProduct($product, $this->sourceHierarchyId, $this->targetHierarchyId);
            }
            return;
        }

        // Batch: bestimmte Produkte oder alle
        if ($this->sourceHierarchyId && $this->targetHierarchyId) {
            $stats = $syncService->syncAll(
                $this->sourceHierarchyId,
                $this->targetHierarchyId,
                $this->productIds
            );

            Log::info('SyncAttributeMappings Job abgeschlossen', $stats);
        }
    }

    public function tags(): array
    {
        return ['attribute-mapping-sync'];
    }
}

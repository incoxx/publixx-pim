<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Search\MeilisearchClient;
use App\Services\Search\MeilisearchDocumentBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * SyncProductToMeilisearch — hält den Meilisearch-Index inkrementell aktuell.
 *
 * Wird vom UpdateSearchIndex-Job gekettet (nachdem der denormalisierte
 * products_search_index aktualisiert wurde) und synchronisiert genau ein
 * Produkt-Dokument. Embeddings berechnet Meilisearch selbst über den
 * konfigurierten Embedder — und nur dann neu, wenn sich der gerenderte
 * documentTemplate-Text geändert hat. Kein Full-Reindex pro Lauf.
 */
class SyncProductToMeilisearch implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public array $backoff = [5, 15, 60];

    public int $uniqueFor = 60;

    public function __construct(
        public readonly string $productId,
    ) {
        $this->onQueue('indexing');
    }

    public function uniqueId(): string
    {
        return 'sync-meilisearch:' . $this->productId;
    }

    public function handle(MeilisearchDocumentBuilder $builder, MeilisearchClient $client): void
    {
        if (!config('meilisearch.enabled')) {
            return;
        }

        $index = (string) config('meilisearch.index', 'products');
        $document = $builder->build($this->productId);

        if ($document === null) {
            // Produkt gelöscht bzw. nicht mehr im Search-Index
            $client->deleteDocument($index, $this->productId);

            Log::debug('SyncProductToMeilisearch: Dokument entfernt', [
                'product_id' => $this->productId,
            ]);

            return;
        }

        $client->addDocuments($index, [$document]);

        Log::debug('SyncProductToMeilisearch: Dokument synchronisiert', [
            'product_id' => $this->productId,
        ]);
    }
}

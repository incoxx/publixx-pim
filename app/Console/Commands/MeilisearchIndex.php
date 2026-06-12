<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Search\MeilisearchClient;
use App\Services\Search\MeilisearchDocumentBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Synchronisiert Produkte aus dem denormalisierten products_search_index
 * nach Meilisearch — standardmäßig inkrementell (nur seit dem letzten
 * Lauf geänderte Produkte). Der Echtzeit-Pfad läuft über den
 * SyncProductToMeilisearch-Job; dieses Kommando dient dem Erstaufbau
 * und als Catch-up (z. B. nach Ausfällen).
 */
class MeilisearchIndex extends Command
{
    private const LAST_RUN_CACHE_KEY = 'meilisearch:last-index-run';

    protected $signature = 'pim:meili-index
        {--all : Alle Produkte synchronisieren (statt inkrementell)}
        {--since= : Nur Produkte mit Änderungen seit diesem Zeitpunkt (z. B. "2026-06-01 00:00")}
        {--chunk=500 : Chunk-Größe pro Batch}';

    protected $description = 'Produkte inkrementell in den Meilisearch-Index synchronisieren';

    public function handle(MeilisearchClient $client, MeilisearchDocumentBuilder $builder): int
    {
        if (!config('meilisearch.enabled')) {
            $this->error('Meilisearch ist deaktiviert (MEILISEARCH_ENABLED=false).');

            return self::FAILURE;
        }

        if (!$client->isHealthy()) {
            $this->error('Meilisearch nicht erreichbar: ' . config('meilisearch.host'));

            return self::FAILURE;
        }

        $since = $this->resolveSince();
        $runStart = now();

        $query = DB::table('products_search_index')
            ->select('product_id')
            ->orderBy('product_id');

        if ($since !== null) {
            $query->where('updated_at', '>', $since);
            $this->info('Inkrementeller Lauf: Änderungen seit ' . $since->toDateTimeString());
        } else {
            $this->info('Voller Lauf: alle Produkte werden synchronisiert.');
        }

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('Keine Änderungen — nichts zu tun.');
            Cache::forever(self::LAST_RUN_CACHE_KEY, $runStart->toIso8601String());

            return self::SUCCESS;
        }

        $index = (string) config('meilisearch.index', 'products');
        $chunkSize = max(1, (int) $this->option('chunk'));
        $synced = 0;
        $bar = $this->output->createProgressBar($total);

        $query->chunkById($chunkSize, function ($rows) use ($builder, $client, $index, &$synced, $bar): void {
            $documents = [];

            foreach ($rows as $row) {
                $document = $builder->build($row->product_id);
                if ($document !== null) {
                    $documents[] = $document;
                }
            }

            if ($documents !== []) {
                $client->addDocuments($index, $documents);
                $synced += count($documents);
            }

            $bar->advance(count($rows));
        }, 'product_id');

        $bar->finish();
        $this->newLine(2);

        Cache::forever(self::LAST_RUN_CACHE_KEY, $runStart->toIso8601String());

        $this->info("Synchronisiert: {$synced} von {$total} Produkten.");
        $this->line('Embeddings berechnet Meilisearch asynchron — nur für geänderte Dokumente.');

        return self::SUCCESS;
    }

    /**
     * Untergrenze für den inkrementellen Lauf ermitteln:
     * --all → keine, --since → expliziter Zeitpunkt, sonst letzter Lauf.
     */
    private function resolveSince(): ?Carbon
    {
        if ($this->option('all')) {
            return null;
        }

        if ($since = $this->option('since')) {
            return Carbon::parse($since);
        }

        $lastRun = Cache::get(self::LAST_RUN_CACHE_KEY);

        return $lastRun ? Carbon::parse($lastRun) : null;
    }
}

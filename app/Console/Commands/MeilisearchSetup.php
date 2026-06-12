<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Search\MeilisearchClient;
use App\Services\Search\SearchSchemaService;
use Illuminate\Console\Command;

/**
 * Wendet die schema-getriebene Indexkonfiguration auf Meilisearch an:
 * Searchable/Filterable/Sortable-Attribute, Synonyme und Embedder werden
 * aus dem PIM-Schema (Attribute + Einheiten) abgeleitet.
 */
class MeilisearchSetup extends Command
{
    protected $signature = 'pim:meili-setup
        {--no-embedder : Embedder-Konfiguration nicht anwenden (nur Keyword/Filter)}
        {--fresh-schema : Schema-Cache vor dem Anwenden invalidieren}';

    protected $description = 'Meilisearch-Index für die semantische Produktsuche konfigurieren';

    public function handle(MeilisearchClient $client, SearchSchemaService $schemaService): int
    {
        if (!config('meilisearch.enabled')) {
            $this->error('Meilisearch ist deaktiviert (MEILISEARCH_ENABLED=false).');

            return self::FAILURE;
        }

        if (!$client->isHealthy()) {
            $this->error('Meilisearch nicht erreichbar: ' . config('meilisearch.host'));

            return self::FAILURE;
        }

        if ($this->option('fresh-schema')) {
            $schemaService->flush();
            $this->info('Schema-Cache invalidiert.');
        }

        $index = (string) config('meilisearch.index', 'products');
        $this->info("Index '{$index}' wird vorbereitet...");
        $client->ensureIndex($index, 'id');

        $settings = $schemaService->meilisearchSettings();
        if ($this->option('no-embedder')) {
            unset($settings['embedders']);
        }

        $this->info('Index-Settings werden angewendet...');
        $taskUid = $client->updateSettings($index, $settings);

        try {
            $client->waitForTask($taskUid, 120);
        } catch (\Throwable $e) {
            $this->error('Settings-Task fehlgeschlagen: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->table(['Einstellung', 'Wert'], [
            ['Filterbare Felder', (string) count($settings['filterableAttributes'])],
            ['Sortierbare Felder', (string) count($settings['sortableAttributes'])],
            ['Synonym-Einträge', (string) count($settings['synonyms'])],
            ['Embedder', isset($settings['embedders'])
                ? config('meilisearch.embedder.source') . ' / ' . config('meilisearch.embedder.model')
                : '— (deaktiviert)'],
        ]);
        $this->info('Meilisearch-Setup abgeschlossen.');

        return self::SUCCESS;
    }
}

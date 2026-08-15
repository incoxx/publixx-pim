<?php

declare(strict_types=1);

namespace App\Services\Tms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TmsClient
{
    private string $baseUrl;
    private string $apiKey;
    private int $timeout;
    private bool $enabled;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('tms.base_url', 'http://localhost:8001/api'), '/');
        $this->apiKey = config('tms.api_key', '');
        $this->timeout = (int) config('tms.timeout', 2);
        $this->enabled = (bool) config('tms.enabled', false);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Resolve translations by text hashes.
     *
     * @param  string[]  $hashes
     * @param  string[]  $targetLangs
     * @return array<string, array<string, string|null>>
     */
    public function resolve(array $hashes, array $targetLangs): array
    {
        if (!$this->enabled || empty($hashes)) {
            return [];
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withToken($this->apiKey)
                ->get("{$this->baseUrl}/resolve", [
                    'hashes' => implode(',', $hashes),
                    'langs' => implode(',', $targetLangs),
                ]);

            if ($response->successful()) {
                return $response->json() ?? [];
            }

            Log::warning('TMS resolve failed', ['status' => $response->status()]);
            return [];
        } catch (\Throwable $e) {
            Log::warning('TMS resolve error', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Send entities to TMS for ingestion.
     *
     * Die Zielsprachen gehen im Payload mit. Das PIM ist die Quelle der
     * Wahrheit (Tabelle `languages`); das TMS soll dafür nicht seine eigene
     * TMS_TARGET_LANGUAGES lesen müssen — sonst laufen beide Seiten
     * auseinander, sobald jemand nur eine der beiden .env pflegt.
     *
     * @param  array  $entities
     * @param  string[]  $targetLanguages  leer = das TMS nimmt seine Konfiguration
     */
    public function ingest(array $entities, array $targetLanguages = []): void
    {
        if (!$this->enabled || empty($entities)) {
            return;
        }

        try {
            $payload = ['entities' => $entities];

            if (!empty($targetLanguages)) {
                $payload['target_languages'] = array_values($targetLanguages);
            }

            $response = Http::timeout(30)
                ->withToken($this->apiKey)
                ->post("{$this->baseUrl}/ingest", $payload);

            if (!$response->successful()) {
                Log::warning('TMS ingest failed', ['status' => $response->status()]);
            }
        } catch (\Throwable $e) {
            Log::warning('TMS ingest error', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get translation units (paginated).
     */
    public function getUnits(array $params = []): array
    {
        return $this->get('/units', $params);
    }

    /**
     * Get single translation unit with translations and usages.
     */
    public function getUnit(string $id): array
    {
        return $this->get("/units/{$id}");
    }

    /**
     * Update a manual translation.
     */
    public function updateTranslation(string $unitId, string $lang, array $data): array
    {
        return $this->put("/units/{$unitId}/translations/{$lang}", $data);
    }

    /**
     * Get translation coverage stats.
     *
     * @param  string[]  $targetLanguages  leer = das TMS nimmt seine Konfiguration
     */
    public function getStats(array $targetLanguages = []): array
    {
        return $this->get('/stats', empty($targetLanguages)
            ? []
            : ['langs' => implode(',', $targetLanguages)]);
    }

    /**
     * Get missing translations.
     */
    public function getMissing(array $params = []): array
    {
        return $this->get('/missing', $params);
    }

    /**
     * Trigger machine re-translation.
     */
    public function retranslate(array $data): array
    {
        return $this->post('/retranslate', $data);
    }

    /**
     * Plant alle Begriffe ohne Übersetzung in einer Zielsprache zur
     * maschinellen Übersetzung ein — der Weg, eine neue Sprache auszurollen.
     *
     * Gedeckelt durch $limit; die Antwort enthält `remaining`, damit der
     * Aufrufer bis zur Vollständigkeit schleifen kann.
     *
     * @return array{lang?: string, dispatched?: int, remaining?: int, message?: string}
     */
    public function translateMissing(string $lang, int $limit = 1000): array
    {
        return $this->post('/translate-missing', [
            'lang' => $lang,
            'limit' => $limit,
        ]);
    }

    /**
     * Eine Seite des Glossars holen (Begriffe samt Übersetzungen und
     * Verwendungsstellen) — Grundlage für den Datei-Export.
     *
     * @param  string[]  $langs
     * @return array{data?: array, current_page?: int, last_page?: int, total?: int}
     */
    public function glossaryPage(array $langs, array $params = []): array
    {
        return $this->get('/glossary', array_merge($params, [
            'langs' => implode(',', $langs),
        ]));
    }

    /**
     * Übersetzungen aus einer externen Datei zurückspielen.
     *
     * @param  array<int, array{hash: string, lang: string, translation: string}>  $items
     */
    public function glossaryImport(array $items): array
    {
        if (!$this->enabled || empty($items)) {
            return [];
        }

        $total = ['updated' => 0, 'unchanged' => 0, 'unknown' => 0];

        foreach (array_chunk($items, 1000) as $batch) {
            $result = $this->post('/glossary', ['items' => $batch]);

            foreach (['updated', 'unchanged', 'unknown'] as $key) {
                $total[$key] += (int) ($result[$key] ?? 0);
            }
        }

        return $total;
    }

    /**
     * Batch-import pre-translated items into TMS.
     */
    public function importTranslations(array $items): array
    {
        if (!$this->enabled || empty($items)) {
            return [];
        }

        $results = [];
        foreach (array_chunk($items, 200) as $batch) {
            $result = $this->post('/import-translations', ['items' => $batch]);
            $results[] = $result;
        }

        return $results;
    }

    /**
     * Delete all translations (optionally filtered by language).
     */
    public function deleteAllTranslations(?string $targetLang = null): array
    {
        return $this->delete('/translations', $targetLang ? ['target_lang' => $targetLang] : []);
    }

    /**
     * Purge all units (source terms + translations).
     */
    public function purgeAllUnits(): array
    {
        return $this->delete('/units');
    }

    // ─── HTTP Helpers ────────────────────────────────────────

    private function get(string $path, array $params = []): array
    {
        if (!$this->enabled) {
            return [];
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withToken($this->apiKey)
                ->get("{$this->baseUrl}{$path}", $params);

            return $response->successful() ? ($response->json() ?? []) : [];
        } catch (\Throwable $e) {
            Log::warning("TMS GET {$path} error", ['error' => $e->getMessage()]);
            return [];
        }
    }

    private function post(string $path, array $data = []): array
    {
        if (!$this->enabled) {
            return [];
        }

        try {
            $response = Http::timeout(30)
                ->withToken($this->apiKey)
                ->post("{$this->baseUrl}{$path}", $data);

            return $response->successful() ? ($response->json() ?? []) : [];
        } catch (\Throwable $e) {
            Log::warning("TMS POST {$path} error", ['error' => $e->getMessage()]);
            return [];
        }
    }

    private function delete(string $path, array $params = []): array
    {
        if (!$this->enabled) {
            return [];
        }

        try {
            $response = Http::timeout(30)
                ->withToken($this->apiKey)
                ->delete("{$this->baseUrl}{$path}", $params);

            return $response->successful() ? ($response->json() ?? []) : [];
        } catch (\Throwable $e) {
            Log::warning("TMS DELETE {$path} error", ['error' => $e->getMessage()]);
            return [];
        }
    }

    private function put(string $path, array $data = []): array
    {
        if (!$this->enabled) {
            return [];
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withToken($this->apiKey)
                ->put("{$this->baseUrl}{$path}", $data);

            return $response->successful() ? ($response->json() ?? []) : [];
        } catch (\Throwable $e) {
            Log::warning("TMS PUT {$path} error", ['error' => $e->getMessage()]);
            return [];
        }
    }
}

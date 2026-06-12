<?php

declare(strict_types=1);

namespace App\Services\Search;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Dünner REST-Client für Meilisearch (ohne zusätzliche Composer-Abhängigkeit).
 *
 * Deckt nur die für die Produktsuche benötigten Endpunkte ab:
 * Index-Verwaltung, Settings (inkl. Embedder), Dokumente und Suche.
 */
class MeilisearchClient
{
    private string $host;
    private ?string $apiKey;
    private int $timeout;

    public function __construct(?string $host = null, ?string $apiKey = null, ?int $timeout = null)
    {
        $this->host = rtrim($host ?? (string) config('meilisearch.host'), '/');
        $this->apiKey = $apiKey ?? config('meilisearch.api_key');
        $this->timeout = $timeout ?? (int) config('meilisearch.timeout', 5);
    }

    /**
     * Erreichbarkeit prüfen.
     */
    public function isHealthy(): bool
    {
        try {
            return $this->http()->get($this->host . '/health')->json('status') === 'available';
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Index anlegen, falls nicht vorhanden.
     */
    public function ensureIndex(string $uid, string $primaryKey = 'id'): void
    {
        $response = $this->http()->get($this->host . '/indexes/' . $uid);

        if ($response->status() === 404) {
            $task = $this->throwOnError(
                $this->http()->post($this->host . '/indexes', [
                    'uid' => $uid,
                    'primaryKey' => $primaryKey,
                ]),
            );
            $this->waitForTask((int) $task->json('taskUid'));

            return;
        }

        $this->throwOnError($response);
    }

    /**
     * Index-Settings (filterable, sortable, synonyms, embedders, ...) setzen.
     *
     * @return int Task-UID
     */
    public function updateSettings(string $uid, array $settings): int
    {
        $response = $this->throwOnError(
            $this->http()->patch($this->host . '/indexes/' . $uid . '/settings', $settings),
        );

        return (int) $response->json('taskUid');
    }

    /**
     * Dokumente hinzufügen oder ersetzen (Upsert über primaryKey).
     *
     * @return int Task-UID
     */
    public function addDocuments(string $uid, array $documents): int
    {
        $response = $this->throwOnError(
            $this->http()->put($this->host . '/indexes/' . $uid . '/documents', $documents),
        );

        return (int) $response->json('taskUid');
    }

    /**
     * Einzelnes Dokument löschen.
     */
    public function deleteDocument(string $uid, string $documentId): int
    {
        $response = $this->throwOnError(
            $this->http()->delete($this->host . '/indexes/' . $uid . '/documents/' . $documentId),
        );

        return (int) $response->json('taskUid');
    }

    /**
     * Suche ausführen (Keyword, Hybrid oder reiner Filter-Betrieb).
     */
    public function search(string $uid, array $params): array
    {
        $response = $this->throwOnError(
            $this->http()->post($this->host . '/indexes/' . $uid . '/search', $params),
        );

        return $response->json() ?? [];
    }

    /**
     * Index-Statistiken (Dokumentanzahl etc.).
     */
    public function stats(string $uid): array
    {
        return $this->throwOnError(
            $this->http()->get($this->host . '/indexes/' . $uid . '/stats'),
        )->json() ?? [];
    }

    /**
     * Auf Abschluss eines asynchronen Tasks warten.
     */
    public function waitForTask(int $taskUid, int $timeoutSeconds = 60): array
    {
        $deadline = microtime(true) + $timeoutSeconds;

        do {
            $task = $this->throwOnError(
                $this->http()->get($this->host . '/tasks/' . $taskUid),
            )->json();

            if (in_array($task['status'] ?? '', ['succeeded', 'failed', 'canceled'], true)) {
                if (($task['status'] ?? '') !== 'succeeded') {
                    throw new RuntimeException(
                        'Meilisearch-Task fehlgeschlagen: ' . json_encode($task['error'] ?? $task),
                    );
                }

                return $task;
            }

            usleep(200_000);
        } while (microtime(true) < $deadline);

        throw new RuntimeException("Meilisearch-Task {$taskUid} nicht innerhalb von {$timeoutSeconds}s abgeschlossen");
    }

    private function http(): PendingRequest
    {
        $request = Http::timeout($this->timeout)->acceptJson()->asJson();

        if ($this->apiKey) {
            $request = $request->withToken($this->apiKey);
        }

        return $request;
    }

    private function throwOnError(Response $response): Response
    {
        if ($response->failed()) {
            throw new RuntimeException(
                'Meilisearch-Fehler (' . $response->status() . '): ' . $response->body(),
            );
        }

        return $response;
    }
}

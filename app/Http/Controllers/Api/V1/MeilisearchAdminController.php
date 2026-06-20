<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\RunMeilisearchMaintenance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Meilisearch-Wartung (Administration > Einstellungen > Meilisearch).
 *
 * Liefert eine vollständige Diagnose der semantischen Suche (Meilisearch,
 * Ollama, Index, Embedder, Vektoren) und startet Wartungsaktionen als
 * Hintergrund-Jobs (Modell laden, Setup, Neu-Indizierung).
 */
class MeilisearchAdminController extends Controller
{
    /**
     * GET /api/v1/admin/meilisearch/status
     */
    public function status(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermissionTo('meilisearch-admin.view'), 403);

        $enabled = (bool) config('meilisearch.enabled');

        return response()->json([
            'data' => [
                'enabled' => $enabled,
                'config' => [
                    'host' => config('meilisearch.host'),
                    'index' => config('meilisearch.index', 'products'),
                    'embedder_enabled' => (bool) config('meilisearch.embedder.enabled'),
                    'embedder_source' => config('meilisearch.embedder.source'),
                    'embedder_model' => config('meilisearch.embedder.model'),
                ],
                'checks' => $enabled ? $this->runDiagnostics() : [],
                'actions' => $this->actionStates(),
            ],
        ]);
    }

    /**
     * POST /api/v1/admin/meilisearch/run
     *
     * Startet eine Wartungsaktion als Hintergrund-Job (Queue: indexing).
     */
    public function run(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermissionTo('meilisearch-admin.manage'), 403);

        $validated = $request->validate([
            'action' => 'required|string|in:' . implode(',', RunMeilisearchMaintenance::ACTIONS),
        ]);

        if (!config('meilisearch.enabled')) {
            return response()->json([
                'message' => 'Meilisearch ist deaktiviert (MEILISEARCH_ENABLED=false).',
            ], 422);
        }

        $action = $validated['action'];
        $state = Cache::get(RunMeilisearchMaintenance::stateKey($action));

        if (($state['status'] ?? null) === 'running') {
            return response()->json([
                'message' => 'Diese Aktion läuft bereits.',
                'state' => $state,
            ], 409);
        }

        // Status sofort auf "queued" setzen, damit das Frontend direkt
        // nach dem Dispatch einen konsistenten Zustand sieht.
        Cache::put(RunMeilisearchMaintenance::stateKey($action), [
            'action' => $action,
            'status' => 'queued',
            'message' => 'In Warteschlange (Queue: indexing)...',
            'output' => '',
            'started_at' => null,
            'finished_at' => null,
        ], 86400);

        RunMeilisearchMaintenance::dispatch($action);

        return response()->json([
            'message' => 'Aktion gestartet.',
            'action' => $action,
        ], 202);
    }

    /**
     * Zustände der drei Wartungsaktionen aus dem Cache lesen.
     */
    private function actionStates(): array
    {
        $states = [];
        foreach (RunMeilisearchMaintenance::ACTIONS as $action) {
            $states[$action] = Cache::get(RunMeilisearchMaintenance::stateKey($action));
        }

        return $states;
    }

    /**
     * Diagnose-Checks: jede Prüfung liefert ok | warn | error plus
     * Detail-Text und konkreten Lösungshinweis.
     */
    private function runDiagnostics(): array
    {
        $checks = [];
        $host = rtrim((string) config('meilisearch.host', 'http://127.0.0.1:7700'), '/');
        $apiKey = (string) config('meilisearch.api_key', '');
        $index = (string) config('meilisearch.index', 'products');

        // ── 1. Meilisearch erreichbar ────────────────────────────────────────
        $meiliUp = false;
        try {
            $health = Http::timeout(3)->get("{$host}/health");
            $meiliUp = $health->successful() && $health->json('status') === 'available';
            $checks[] = [
                'key' => 'meilisearch',
                'label' => 'Meilisearch erreichbar',
                'status' => $meiliUp ? 'ok' : 'error',
                'detail' => $meiliUp ? $host : "Keine Antwort von {$host}",
                'hint' => $meiliUp ? null : 'Dienst prüfen: systemctl status meilisearch && systemctl start meilisearch',
            ];
        } catch (\Throwable $e) {
            $checks[] = [
                'key' => 'meilisearch',
                'label' => 'Meilisearch erreichbar',
                'status' => 'error',
                'detail' => $e->getMessage(),
                'hint' => 'Dienst prüfen: systemctl status meilisearch && systemctl start meilisearch',
            ];
        }

        // ── 2. Ollama erreichbar + Modell geladen ────────────────────────────
        $model = (string) config('meilisearch.embedder.model', 'nomic-embed-text');
        $embedderUrl = (string) config('meilisearch.embedder.url', 'http://localhost:11434/api/embeddings');
        $parts = parse_url($embedderUrl);
        $ollamaBase = ($parts['scheme'] ?? 'http') . '://' . ($parts['host'] ?? 'localhost') . ':' . ($parts['port'] ?? 11434);
        $modelLoaded = false;

        if ((bool) config('meilisearch.embedder.enabled') && config('meilisearch.embedder.source') === 'ollama') {
            try {
                $tags = Http::timeout(3)->get("{$ollamaBase}/api/tags");
                if ($tags->successful()) {
                    $models = array_column($tags->json('models', []), 'name');
                    $modelLoaded = (bool) array_filter($models, fn (string $m): bool => str_starts_with($m, $model));
                    $checks[] = [
                        'key' => 'ollama',
                        'label' => "Ollama + Modell '{$model}'",
                        'status' => $modelLoaded ? 'ok' : 'error',
                        'detail' => $modelLoaded ? "Modell geladen ({$ollamaBase})" : "Ollama läuft, Modell '{$model}' fehlt",
                        'hint' => $modelLoaded ? null : "Aktion „Modell laden“ ausführen oder: ollama pull {$model}",
                    ];
                } else {
                    $checks[] = [
                        'key' => 'ollama',
                        'label' => "Ollama + Modell '{$model}'",
                        'status' => 'error',
                        'detail' => "Keine Antwort von {$ollamaBase}",
                        'hint' => 'Dienst prüfen: systemctl status ollama && systemctl start ollama',
                    ];
                }
            } catch (\Throwable $e) {
                $checks[] = [
                    'key' => 'ollama',
                    'label' => "Ollama + Modell '{$model}'",
                    'status' => 'error',
                    'detail' => $e->getMessage(),
                    'hint' => 'Dienst prüfen: systemctl status ollama && systemctl start ollama',
                ];
            }
        }

        if (!$meiliUp) {
            return $checks;
        }

        $http = fn () => $apiKey !== ''
            ? Http::timeout(5)->withToken($apiKey)
            : Http::timeout(5);

        // ── 3. Index + Dokumente ─────────────────────────────────────────────
        $isIndexing = false;
        try {
            $stats = $http()->get("{$host}/indexes/{$index}/stats");
            if ($stats->successful()) {
                $docs = (int) $stats->json('numberOfDocuments', 0);
                $isIndexing = (bool) $stats->json('isIndexing', false);
                $checks[] = [
                    'key' => 'index',
                    'label' => "Index '{$index}'",
                    'status' => $docs > 0 ? 'ok' : 'warn',
                    'detail' => $docs > 0
                        ? number_format($docs, 0, ',', '.') . ' Dokumente' . ($isIndexing ? ' — Indizierung läuft' : '')
                        : 'Index ist leer',
                    'hint' => $docs > 0 ? null : 'Aktion „Alle Produkte neu indizieren“ ausführen',
                ];
            } else {
                $checks[] = [
                    'key' => 'index',
                    'label' => "Index '{$index}'",
                    'status' => 'error',
                    'detail' => 'Index existiert nicht',
                    'hint' => 'Aktion „Index-Setup“ ausführen',
                ];
            }
        } catch (\Throwable $e) {
            $checks[] = [
                'key' => 'index',
                'label' => "Index '{$index}'",
                'status' => 'error',
                'detail' => $e->getMessage(),
                'hint' => null,
            ];
        }

        // ── 4. Embedder im Index konfiguriert ────────────────────────────────
        try {
            $settings = $http()->get("{$host}/indexes/{$index}/settings");
            $embedders = $settings->successful() ? ($settings->json('embedders') ?? []) : [];
            $configured = $embedders !== [];
            $checks[] = [
                'key' => 'embedder',
                'label' => 'Embedder im Index konfiguriert',
                'status' => $configured ? 'ok' : 'error',
                'detail' => $configured
                    ? implode(', ', array_keys($embedders))
                    : 'Kein Embedder gesetzt — Vektorsuche inaktiv',
                'hint' => $configured ? null : 'Erst Modell laden, dann Aktion „Index-Setup“ ausführen',
            ];
        } catch (\Throwable) {
            $checks[] = [
                'key' => 'embedder',
                'label' => 'Embedder im Index konfiguriert',
                'status' => 'warn',
                'detail' => 'Settings nicht lesbar',
                'hint' => null,
            ];
        }

        // ── 5. Vektoren vorhanden (Stichprobe) ───────────────────────────────
        try {
            $sample = $http()->get("{$host}/indexes/{$index}/documents", [
                'limit' => 1,
                'retrieveVectors' => 'true',
            ]);
            $results = $sample->successful() ? ($sample->json('results') ?? []) : [];
            if ($results !== []) {
                $hasVectors = !empty($results[0]['_vectors']);
                $checks[] = [
                    'key' => 'vectors',
                    'label' => 'Embeddings vorhanden (Stichprobe)',
                    'status' => $hasVectors ? 'ok' : ($isIndexing ? 'warn' : 'error'),
                    'detail' => $hasVectors
                        ? 'Vektoren vorhanden — semantische Suche aktiv'
                        : ($isIndexing
                            ? 'Noch keine Vektoren — Indizierung läuft gerade'
                            : 'Keine Vektoren — semantische Suche findet nichts'),
                    'hint' => $hasVectors ? null : ($isIndexing
                        ? 'Warten bis die Indizierung abgeschlossen ist, dann erneut prüfen'
                        : 'Modell laden → Index-Setup → Alle Produkte neu indizieren'),
                ];
            }
        } catch (\Throwable) {
            // Stichprobe ist optional (retrieveVectors erst ab Meilisearch 1.10)
        }

        // ── 6. Fehlgeschlagene Tasks ─────────────────────────────────────────
        try {
            $tasks = $http()->get("{$host}/tasks", [
                'indexUids' => $index,
                'statuses' => 'failed',
                'limit' => 3,
            ]);
            $failed = $tasks->successful() ? ($tasks->json('results') ?? []) : [];
            if ($failed !== []) {
                $lastError = $failed[0]['error']['message'] ?? 'Unbekannter Fehler';
                $checks[] = [
                    'key' => 'tasks',
                    'label' => 'Fehlgeschlagene Meilisearch-Tasks',
                    'status' => 'warn',
                    'detail' => count($failed) . ' fehlgeschlagene(r) Task(s), zuletzt: ' . $lastError,
                    'hint' => 'Ursache beheben (siehe Fehlertext), dann Aktion erneut ausführen',
                ];
            }
        } catch (\Throwable) {
            // Task-Historie ist optional
        }

        return $checks;
    }
}

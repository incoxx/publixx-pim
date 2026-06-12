<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * RunMeilisearchMaintenance — führt Wartungsaktionen für die semantische
 * Suche im Hintergrund aus (Administration > Einstellungen > Meilisearch).
 *
 * Aktionen:
 * - pull-model: Embedding-Modell über die Ollama-API laden (~274 MB Download)
 * - setup:      pim:meili-setup (Index-Settings inkl. Embedder anwenden)
 * - index-all:  pim:meili-index --all (alle Produkte neu synchronisieren)
 *
 * Der Fortschritt wird im Cache abgelegt; das Frontend pollt den
 * Status-Endpunkt des MeilisearchAdminControllers.
 */
class RunMeilisearchMaintenance implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const ACTIONS = ['pull-model', 'setup', 'index-all'];

    private const STATE_TTL = 86400;

    public int $tries = 1;

    /** Modell-Download über langsame Leitungen kann dauern. */
    public int $timeout = 3600;

    public function __construct(
        public readonly string $action,
    ) {
        $this->onQueue('indexing');
    }

    public function uniqueId(): string
    {
        return 'meili-maintenance:' . $this->action;
    }

    public static function stateKey(string $action): string
    {
        return 'meilisearch:maintenance:' . $action;
    }

    public function handle(): void
    {
        $this->writeState('running', 'Wird ausgeführt...');

        try {
            $output = match ($this->action) {
                'pull-model' => $this->pullModel(),
                'setup' => $this->runArtisan('pim:meili-setup', []),
                'index-all' => $this->runArtisan('pim:meili-index', ['--all' => true]),
                default => throw new RuntimeException("Unbekannte Aktion: {$this->action}"),
            };

            $this->writeState('success', 'Erfolgreich abgeschlossen.', $output);
        } catch (\Throwable $e) {
            $this->writeState('failed', $e->getMessage());

            throw $e;
        }
    }

    public function failed(?\Throwable $e): void
    {
        $this->writeState('failed', $e?->getMessage() ?? 'Unbekannter Fehler');
    }

    /**
     * Embedding-Modell über die Ollama-API laden (blockierend, stream=false).
     */
    private function pullModel(): string
    {
        $model = (string) config('meilisearch.embedder.model', 'nomic-embed-text');
        $embedderUrl = (string) config('meilisearch.embedder.url', 'http://localhost:11434/api/embeddings');
        $parts = parse_url($embedderUrl);
        $base = ($parts['scheme'] ?? 'http') . '://' . ($parts['host'] ?? 'localhost') . ':' . ($parts['port'] ?? 11434);

        $this->writeState('running', "Modell '{$model}' wird geladen (Download kann mehrere Minuten dauern)...");

        $response = Http::timeout(3000)->post("{$base}/api/pull", [
            'model' => $model,
            'stream' => false,
        ]);

        if ($response->failed()) {
            throw new RuntimeException("Ollama-Pull fehlgeschlagen ({$response->status()}): " . $response->body());
        }

        $status = (string) $response->json('status', '');
        if ($status !== 'success') {
            throw new RuntimeException('Ollama-Pull lieferte unerwarteten Status: ' . $response->body());
        }

        return "Modell '{$model}' geladen.";
    }

    /**
     * Artisan-Kommando ausführen und Ausgabe zurückgeben.
     */
    private function runArtisan(string $command, array $parameters): string
    {
        $exitCode = Artisan::call($command, $parameters);
        $output = trim(Artisan::output());

        if ($exitCode !== 0) {
            throw new RuntimeException($output !== '' ? $output : "{$command} fehlgeschlagen (Exit-Code {$exitCode})");
        }

        return $output;
    }

    private function writeState(string $status, string $message, string $output = ''): void
    {
        $existing = Cache::get(self::stateKey($this->action), []);

        $startedAt = $existing['started_at'] ?? null;
        if ($status === 'running' && ($existing['status'] ?? null) !== 'running') {
            $startedAt = now()->toIso8601String();
        }

        Cache::put(self::stateKey($this->action), [
            'action' => $this->action,
            'status' => $status,
            'message' => $message,
            'output' => $output,
            'started_at' => $startedAt,
            'finished_at' => in_array($status, ['success', 'failed'], true) ? now()->toIso8601String() : null,
        ], self::STATE_TTL);
    }
}

<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\ImportCompleted;
use App\Models\ImportJob;
use App\Services\Import\ImportExecutor;
use App\Services\Import\ParseResult;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queue-Job für den Import großer Dateien (> 100 Zeilen).
 * Wird vom ImportService dispatcht wenn die Gesamtzeilenzahl den Threshold überschreitet.
 */
class ExecuteImportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Maximale Ausführungszeit in Sekunden.
     */
    public int $timeout = 600;

    /**
     * Anzahl Versuche bei Fehlern.
     */
    public int $tries = 1;

    public function __construct(
        private readonly string $importJobId,
        private readonly ParseResult $parseResult,
    ) {
        $this->onQueue('imports');
    }

    public function handle(ImportExecutor $executor): void
    {
        $importJob = ImportJob::find($this->importJobId);
        if (!$importJob) {
            Log::error("ImportJob nicht gefunden: {$this->importJobId}");
            return;
        }

        try {
            $importJob->update([
                'status' => 'executing',
                'started_at' => now(),
            ]);

            $result = $executor->execute($this->parseResult);

            $importJob->update([
                'status' => 'completed',
                'completed_at' => now(),
                'result' => $result->toArray(),
            ]);

            // Event dispatchen für Agent 9 (Performance/Cache-Warmup)
            event(new ImportCompleted(
                importJobId: $this->importJobId,
                productIds: $result->affectedProductIds,
            ));

            Log::info("Import abgeschlossen: {$this->importJobId}", [
                'stats' => $result->stats,
                'affected_products' => count($result->affectedProductIds),
            ]);
        } catch (\Throwable $e) {
            $importJob->update([
                'status' => 'failed',
                'completed_at' => now(),
                'result' => [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ],
            ]);

            Log::error("Import fehlgeschlagen: {$this->importJobId}", [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $importJob = ImportJob::find($this->importJobId);
        $importJob?->update([
            'status' => 'failed',
            'completed_at' => now(),
            'result' => ['error' => $exception->getMessage()],
        ]);

        Log::error("Import-Job endgültig fehlgeschlagen: {$this->importJobId}", [
            'error' => $exception->getMessage(),
        ]);
    }
}

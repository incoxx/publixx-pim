<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\ImportCompleted;
use App\Models\ImportJob;
use App\Services\Import\FlatImportExecutor;
use App\Services\Import\ImportLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Queue-Job für Flat-Excel-Importe großer Dateien (> 100 Zeilen).
 * Wird von ImportService::executeFlatImport() dispatcht, wenn die Zeilenzahl
 * den Threshold überschreitet — analog zu ExecuteImportJob für Vorlagen-Importe.
 */
class ExecuteFlatImportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Maximale Ausführungszeit in Sekunden (30 Minuten für große Imports).
     */
    public int $timeout = 1800;

    /**
     * Anzahl Versuche bei Fehlern.
     */
    public int $tries = 1;

    public function __construct(
        private readonly string $importJobId,
        private readonly array $options,
    ) {
        $this->onQueue('import');
    }

    public function handle(FlatImportExecutor $executor, ImportLogger $logger): void
    {
        $importJob = ImportJob::find($this->importJobId);
        if (!$importJob) {
            Log::error("ImportJob nicht gefunden: {$this->importJobId}");
            return;
        }

        $importJob->update([
            'status' => 'executing',
            'started_at' => now(),
        ]);

        $logger->info($importJob, 'execution', 'Flat-Import gestartet');

        try {
            $fullPath = Storage::disk('local')->path($importJob->file_path);
            $executor->setMode($this->options['mode'] ?? 'update');
            $executor->setLogger($logger, $importJob);

            $result = $executor->execute(
                $fullPath,
                $this->options['column_mappings'] ?? [],
                $this->options['sku_column'] ?? 'SKU',
                $this->options['product_type_id'] ?? null,
                $this->options['master_hierarchy_node_id'] ?? null,
                $this->options['name_column'] ?? null,
                $this->options['ean_column'] ?? null,
            );

            $importJob->update([
                'status' => 'completed',
                'completed_at' => now(),
                'result' => $result->toArray(),
            ]);

            event(new ImportCompleted(
                importJobId: $this->importJobId,
                productIds: $result->affectedProductIds,
            ));

            Log::channel('import')->info("Flat-Import abgeschlossen: {$this->importJobId}", $result->stats);
            $logger->info($importJob, 'execution', 'Flat-Import erfolgreich abgeschlossen', $result->stats);
        } catch (\Throwable $e) {
            $importJob->update([
                'status' => 'failed',
                'completed_at' => now(),
                'result' => ['error' => $e->getMessage()],
            ]);

            Log::channel('import')->error("Flat-Import fehlgeschlagen: {$this->importJobId}", ['error' => $e->getMessage()]);
            $logger->error($importJob, 'execution', "Flat-Import fehlgeschlagen: {$e->getMessage()}");

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

        Log::error("Flat-Import-Job endgültig fehlgeschlagen: {$this->importJobId}", [
            'error' => $exception->getMessage(),
        ]);
    }
}

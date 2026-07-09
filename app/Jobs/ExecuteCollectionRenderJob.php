<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\CollectionRenderJob;
use App\Services\Collections\CollectionRenderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Struktureller Spiegel von ExecuteReportJob. Landet ueber den Dispatch-Aufrufer bewusst
 * auf Horizons 'pdf'-Queue (config/horizon.php: supervisor-pdf, bislang ungenutzt) --
 * einzige Abweichung vom ReportJob-Vorbild, der ohne onQueue() auf 'default' laeuft.
 */
class ExecuteCollectionRenderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 600;

    public function __construct(
        public readonly string $collectionRenderJobId,
    ) {}

    public function handle(CollectionRenderService $service): void
    {
        $job = CollectionRenderJob::find($this->collectionRenderJobId);
        if (!$job) {
            Log::warning("Collection-Render-Job nicht gefunden: {$this->collectionRenderJobId}");
            return;
        }

        $service->executeJob($job);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Collection-Render-Job Queue-Fehler: {$this->collectionRenderJobId}", [
            'error' => $exception->getMessage(),
        ]);

        $job = CollectionRenderJob::find($this->collectionRenderJobId);
        $job?->update([
            'last_status' => 'failed',
            'last_error' => $exception->getMessage(),
        ]);
    }
}

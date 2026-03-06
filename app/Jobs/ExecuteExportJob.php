<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ExportJob;
use App\Services\Export\ExportJobService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Führt einen Export-Job asynchron über die Queue aus.
 */
class ExecuteExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 600; // 10 Minuten

    public function __construct(
        public readonly string $exportJobId,
    ) {}

    public function handle(ExportJobService $service): void
    {
        $job = ExportJob::find($this->exportJobId);
        if (!$job) {
            Log::channel('export')->warning("Export-Job nicht gefunden: {$this->exportJobId}");
            return;
        }

        $service->execute($job);
    }

    public function failed(\Throwable $exception): void
    {
        Log::channel('export')->error("Export-Job Queue-Fehler: {$this->exportJobId}", [
            'error' => $exception->getMessage(),
        ]);

        $job = ExportJob::find($this->exportJobId);
        $job?->update([
            'last_status' => 'failed',
            'last_error' => $exception->getMessage(),
        ]);
    }
}

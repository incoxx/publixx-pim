<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ReportJob;
use App\Services\Report\ReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExecuteReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 600;

    public function __construct(
        public readonly string $reportJobId,
    ) {}

    public function handle(ReportService $service): void
    {
        $job = ReportJob::find($this->reportJobId);
        if (!$job) {
            Log::warning("Report-Job nicht gefunden: {$this->reportJobId}");
            return;
        }

        $service->executeJob($job);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Report-Job Queue-Fehler: {$this->reportJobId}", [
            'error' => $exception->getMessage(),
        ]);

        $job = ReportJob::find($this->reportJobId);
        $job?->update([
            'last_status' => 'failed',
            'last_error' => $exception->getMessage(),
        ]);
    }
}

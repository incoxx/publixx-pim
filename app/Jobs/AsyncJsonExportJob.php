<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Export\JsonFormatExporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AsyncJsonExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Bei echter Exception sofort abbrechen (kein Retry).
     */
    public int $maxExceptions = 1;

    /**
     * Job darf bis zu 30 Minuten laufen.
     */
    public int $timeout = 1800;

    private const CACHE_PREFIX = 'async_json_export:';

    private const CANCEL_PREFIX = 'async_json_export_cancel:';

    public function __construct(
        public readonly string $exportKey,
        public readonly array $sections,
        public readonly array $filters,
        public readonly string $fileName,
    ) {}

    /**
     * Job darf bis zu 45 Minuten nach Dispatch versucht werden.
     */
    public function retryUntil(): \DateTimeInterface
    {
        return now()->addMinutes(45);
    }

    public function handle(JsonFormatExporter $exporter): void
    {
        $this->updateProgress('collecting', 0, 0);

        $outputDir = storage_path('app/exports');
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $outputPath = $outputDir . '/' . $this->exportKey . '.json';

        $json = $exporter->export($this->sections, $this->filters);

        // Abbruch nach export() prüfen (export selbst prüft keinen Cancel)
        if (Cache::get(self::CANCEL_PREFIX . $this->exportKey)) {
            $this->updateProgress('cancelled', 0, 0);
            Cache::forget(self::CANCEL_PREFIX . $this->exportKey);
            Log::channel('export')->info("AsyncJsonExportJob: Abgebrochen.", ['key' => $this->exportKey]);
            return;
        }

        $this->updateProgress('writing', 0, 0);
        file_put_contents($outputPath, $json);

        $this->updateProgress('completed', 0, 0, outputPath: $outputPath, fileName: $this->fileName);
        Log::channel('export')->info("AsyncJsonExportJob: Fertig.", [
            'key' => $this->exportKey,
            'size' => strlen($json),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $this->updateProgress('failed', 0, 0, error: $exception->getMessage());
        Log::channel('export')->error('AsyncJsonExportJob fehlgeschlagen: ' . $exception->getMessage(), [
            'key' => $this->exportKey,
        ]);

        $outputPath = storage_path('app/exports/' . $this->exportKey . '.json');
        if (file_exists($outputPath)) {
            @unlink($outputPath);
        }
    }

    private function updateProgress(
        string $status,
        int $processed,
        int $total,
        ?string $error = null,
        ?string $outputPath = null,
        ?string $fileName = null,
    ): void {
        Cache::put(self::CACHE_PREFIX . $this->exportKey, [
            'status'      => $status,
            'processed'   => $processed,
            'total'       => $total,
            'percent'     => $total > 0 ? round(($processed / $total) * 100, 1) : 0,
            'error'       => $error,
            'output_path' => $outputPath,
            'file_name'   => $fileName,
            'updated_at'  => now()->toIso8601String(),
        ], 3600);
    }

    public static function cacheKey(string $exportKey): string
    {
        return self::CACHE_PREFIX . $exportKey;
    }

    public static function cancel(string $exportKey): void
    {
        Cache::put(self::CANCEL_PREFIX . $exportKey, true, 600);
    }
}

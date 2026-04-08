<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ExportProfile;
use App\Services\Export\ExportProfileService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AsyncProfileExportJob implements ShouldQueue
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

    private const CACHE_PREFIX = 'async_profile_export:';

    private const CANCEL_PREFIX = 'async_profile_export_cancel:';

    public function __construct(
        public readonly string $exportKey,
        public readonly string $profileId,
        public readonly bool $zip = false,
    ) {}

    /**
     * Job darf bis zu 45 Minuten nach Dispatch versucht werden.
     */
    public function retryUntil(): \DateTimeInterface
    {
        return now()->addMinutes(45);
    }

    public function handle(ExportProfileService $exportService): void
    {
        $profile = ExportProfile::findOrFail($this->profileId);

        $this->updateProgress('collecting', 0, 0);

        $outputDir = storage_path('app/exports');
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $outputBasePath = $outputDir . '/' . $this->exportKey;

        $result = $exportService->executeToFile($profile, $outputBasePath);

        // Abbruch-Prüfung (läuft synchron im Job, aber Cancel-Flag respektieren)
        if (Cache::get(self::CANCEL_PREFIX . $this->exportKey)) {
            Cache::forget(self::CANCEL_PREFIX . $this->exportKey);
            if (file_exists($result['output_path'])) {
                @unlink($result['output_path']);
            }
            $this->updateProgress('cancelled', 0, 0);
            Log::channel('export')->info("AsyncProfileExportJob: Abgebrochen.", ['key' => $this->exportKey]);
            return;
        }

        $ext = $result['ext'];
        $baseName = $result['file_name'];

        // Optional ZIP-Wrapping (wenn gewünscht und noch kein ZIP)
        if ($this->zip && $ext !== 'zip') {
            $zipPath = $outputBasePath . '.zip';
            $zip = new \ZipArchive();
            $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
            $zip->addFile($result['output_path'], $baseName . '.' . $ext);
            $zip->close();
            @unlink($result['output_path']);
            $finalPath = $zipPath;
            $finalExt = 'zip';
        } else {
            $finalPath = $result['output_path'];
            $finalExt = $ext;
        }

        $fileName = $baseName . '.' . $finalExt;

        $this->updateProgress('completed', 0, 0, outputPath: $finalPath, fileName: $fileName);
        Log::channel('export')->info("AsyncProfileExportJob: Fertig.", [
            'key'    => $this->exportKey,
            'file'   => $finalPath,
            'format' => $profile->format,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $this->updateProgress('failed', 0, 0, error: $exception->getMessage());
        Log::channel('export')->error('AsyncProfileExportJob fehlgeschlagen: ' . $exception->getMessage(), [
            'key' => $this->exportKey,
        ]);

        // Aufräumen
        foreach (['json', 'xlsx', 'csv', 'xml', 'zip'] as $ext) {
            $path = storage_path('app/exports/' . $this->exportKey . '.' . $ext);
            if (file_exists($path)) {
                @unlink($path);
            }
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

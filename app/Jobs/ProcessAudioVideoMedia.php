<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Media;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/**
 * ProcessAudioVideoMedia – Dauer ermitteln (ffprobe) und bei Video ein
 * Vorschaubild aus dem ersten Frame extrahieren (ffmpeg).
 *
 * Alle Statusänderungen laufen über updateQuietly()/saveQuietly(), da die
 * betroffenen Felder direkt auf dem Media-Model liegen: ein normales
 * update() würde MediaObserver::updated() erneut auslösen und den Job
 * dadurch endlos neu dispatchen.
 */
class ProcessAudioVideoMedia implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 30, 120];

    public int $timeout = 300;

    public function __construct(
        public readonly string $mediaId,
    ) {
        $this->onQueue('av');
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->mediaId))
                ->releaseAfter(300)
                ->expireAfter(600),
        ];
    }

    public function handle(): void
    {
        $media = Media::find($this->mediaId);

        if (!$media || !in_array($media->media_type, ['audio', 'video'], true)) {
            return;
        }

        $media->updateQuietly(['av_processing_status' => 'processing', 'av_error_message' => null]);

        $disk = Storage::disk('public');
        $localPath = $disk->path($media->file_path);

        try {
            if (!file_exists($localPath)) {
                throw new \RuntimeException('Quelldatei nicht gefunden: ' . $media->file_path);
            }

            $duration = $this->probeDuration($localPath);
            $updates = ['duration_seconds' => $duration, 'av_processing_status' => 'ready'];

            if ($media->media_type === 'video') {
                $thumbPath = $this->extractThumbnail($media->id, $localPath, $duration);
                if ($thumbPath) {
                    $updates['video_thumbnail_path'] = $thumbPath;
                }
            }

            $media->updateQuietly($updates);

            Log::info('ProcessAudioVideoMedia: Completed', [
                'media_id' => $media->id,
                'duration_seconds' => $duration,
            ]);
        } catch (\Throwable $e) {
            $media->updateQuietly([
                'av_processing_status' => 'error',
                'av_error_message' => mb_substr($e->getMessage(), 0, 2000),
            ]);

            Log::error('ProcessAudioVideoMedia: Failed', [
                'media_id' => $media->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $media = Media::find($this->mediaId);

        $media?->updateQuietly([
            'av_processing_status' => 'error',
            'av_error_message' => mb_substr($exception->getMessage(), 0, 2000),
        ]);
    }

    private function probeDuration(string $path): int
    {
        // Bewusst ohne "-of default=noprint_wrapper=1:nokey=1": diese Writer-Option wird nicht
        // von jedem ffprobe-Build akzeptiert ("Failed to set option 'noprint_wrapper' ...").
        // Stattdessen die Standardausgabe (immer verfügbar, unabhängig von Writer-Optionen)
        // per Regex auswerten — robust gegenüber unterschiedlichen ffprobe-Versionen/-Builds.
        $result = Process::timeout(60)->run([
            'ffprobe',
            '-v', 'error',
            '-show_entries', 'format=duration',
            $path,
        ]);

        if (!$result->successful()) {
            throw new \RuntimeException('ffprobe fehlgeschlagen: ' . $result->errorOutput());
        }

        $output = trim($result->output());

        if (preg_match('/duration\s*=\s*([\d.]+)/', $output, $matches)) {
            return (int) round((float) $matches[1]);
        }

        if (is_numeric($output)) {
            return (int) round((float) $output);
        }

        throw new \RuntimeException('ffprobe: Konnte Dauer nicht aus Ausgabe ermitteln: ' . $output);
    }

    private function extractThumbnail(string $mediaId, string $sourcePath, int $duration): ?string
    {
        // Bei sehr kurzen Videos (< 1s) ab dem Start suchen, sonst ab Sekunde 1
        // (vermeidet oft schwarze/leere Anfangsframes).
        $seekTo = $duration > 1 ? '00:00:01' : '00:00:00';
        $tmpDir = storage_path('app/private/tmp');
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }
        $tmpPath = $tmpDir . '/av-thumb-' . $mediaId . '.jpg';

        $result = Process::timeout(60)->run([
            'ffmpeg',
            '-y',
            '-ss', $seekTo,
            '-i', $sourcePath,
            '-vframes', '1',
            '-vf', 'scale=640:-1',
            $tmpPath,
        ]);

        if (!$result->successful() || !file_exists($tmpPath)) {
            // Nicht-fataler Fehler: Video bleibt ohne Thumbnail, Frontend zeigt Icon-Fallback
            Log::warning('ProcessAudioVideoMedia: Thumbnail-Extraktion fehlgeschlagen', [
                'media_id' => $mediaId,
                'error' => $result->errorOutput(),
            ]);

            return null;
        }

        $relativePath = 'media-video-thumbnails/' . $mediaId . '.jpg';
        Storage::disk('public')->put($relativePath, file_get_contents($tmpPath));
        @unlink($tmpPath);

        return $relativePath;
    }
}

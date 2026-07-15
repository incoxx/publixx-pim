<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Media;
use App\Support\Media\MediaFileTypes;
use App\Support\Media\VideoImportHosts;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * ImportVideoFromUrl – lädt ein Video von einer erlaubten Video-Plattform (z.B. YouTube)
 * per yt-dlp herunter und übernimmt es als Media-Asset (source_url wird zur
 * Nachvollziehbarkeit gespeichert). Die anschließende Dauer-/Thumbnail-Ermittlung
 * übernimmt automatisch ProcessAudioVideoMedia — ausgelöst über MediaObserver durch das
 * normale update() unten (bewusst NICHT updateQuietly(), damit die bestehende
 * AV-Pipeline wie bei einem regulären Upload greift).
 */
class ImportVideoFromUrl implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public array $backoff = [30];

    public int $timeout = 900; // Downloads können bei langen Videos mehrere Minuten dauern

    public function __construct(
        public readonly string $mediaId,
        public readonly string $sourceUrl,
    ) {
        $this->onQueue('av');
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->mediaId))
                ->releaseAfter(900)
                ->expireAfter(1800),
        ];
    }

    public function handle(): void
    {
        $media = Media::find($this->mediaId);

        if (!$media) {
            return;
        }

        // Defense in depth: der Controller prüft die Allowlist bereits vor dem Dispatch,
        // aber der Job soll auch bei erneutem/direktem Dispatch sicher sein.
        if (!VideoImportHosts::isAllowed($this->sourceUrl)) {
            $media->update([
                'av_processing_status' => 'error',
                'av_error_message' => 'Nicht erlaubte Video-Quelle.',
            ]);

            return;
        }

        $media->updateQuietly(['av_processing_status' => 'processing', 'av_error_message' => null]);

        $tmpDir = storage_path('app/private/tmp/video-import-' . $media->id);

        try {
            if (!is_dir($tmpDir)) {
                mkdir($tmpDir, 0755, true);
            }

            $outputTemplate = $tmpDir . '/download.%(ext)s';

            // Ein einziger yt-dlp-Aufruf lädt die Datei UND liefert den Titel über --print
            // (nach dem finalen Verschieben der Datei) — vermeidet einen zweiten,
            // redundanten Netzwerk-Roundtrip nur für die Metadaten.
            $result = Process::timeout(870)->run([
                'yt-dlp',
                '--no-playlist',
                '--quiet',
                '--no-warnings',
                '-f', 'bv*[ext=mp4]+ba[ext=m4a]/b[ext=mp4]/b',
                '--merge-output-format', 'mp4',
                '--print', 'after_move:%(title)s',
                '-o', $outputTemplate,
                $this->sourceUrl,
            ]);

            if (!$result->successful()) {
                throw new \RuntimeException('yt-dlp fehlgeschlagen: ' . $result->errorOutput());
            }

            $title = trim($result->output());
            $title = $title !== '' ? mb_substr($title, 0, 255) : null;

            $downloadedFiles = glob($tmpDir . '/download.*');
            $downloadedPath = $downloadedFiles[0] ?? null;

            if (!$downloadedPath || !file_exists($downloadedPath)) {
                throw new \RuntimeException('yt-dlp hat keine Datei erzeugt.');
            }

            $extension = strtolower(pathinfo($downloadedPath, PATHINFO_EXTENSION)) ?: 'mp4';
            if (!in_array($extension, MediaFileTypes::VIDEO_EXTENSIONS, true)) {
                $extension = 'mp4';
            }

            $safeFilename = Str::uuid()->toString() . '.' . $extension;
            $relativePath = 'media/' . $safeFilename;

            Storage::disk('public')->put($relativePath, file_get_contents($downloadedPath));

            $media->update([
                'file_name' => $safeFilename,
                'original_file_name' => $title ? $title . '.' . $extension : $safeFilename,
                'file_path' => $relativePath,
                'mime_type' => 'video/mp4',
                'file_size' => Storage::disk('public')->size($relativePath),
                'media_type' => 'video',
                'title_de' => $media->title_de ?: $title,
                'last_uploaded_at' => now(),
            ]);

            Log::info('ImportVideoFromUrl: Completed', ['media_id' => $media->id, 'url' => $this->sourceUrl]);
        } catch (\Throwable $e) {
            $media->updateQuietly([
                'av_processing_status' => 'error',
                'av_error_message' => mb_substr($e->getMessage(), 0, 2000),
            ]);

            Log::error('ImportVideoFromUrl: Failed', [
                'media_id' => $media->id,
                'url' => $this->sourceUrl,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            $this->cleanupTmpDir($tmpDir);
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

    private function cleanupTmpDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = glob($dir . '/*');
        if ($files) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
        @rmdir($dir);
    }
}

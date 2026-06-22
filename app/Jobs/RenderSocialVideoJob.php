<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Rendert eine Reel-Definition zu einem fertigen Social-Media-Video (.mp4).
 *
 * Ruft die Video-Engine (video-engine/src/reel-cli.ts) auf, die HTML-Szenen via
 * Playwright aufnimmt, Voiceover synthetisiert und mit ffmpeg zusammenführt.
 * Der Fortschritt wird als status.json im Job-Verzeichnis abgelegt; das Rendering
 * ist langlaufend und läuft daher über die Queue (nicht synchron in der Request).
 */
class RenderSocialVideoJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Max. Laufzeit (Sekunden) – Rendering ist CPU-intensiv. */
    public int $timeout = 1800;

    public function __construct(
        private readonly string $jobId,
        private readonly array $reelDefinition,
    ) {}

    public function handle(): void
    {
        $jobDir   = self::jobDir($this->jobId);
        $reelPath = $jobDir . '/reel.json';
        $outputDir = public_path('videos/social');
        $outputPath = $outputDir . '/' . $this->jobId . '.mp4';

        @mkdir($jobDir, 0775, true);
        @mkdir($outputDir, 0775, true);

        file_put_contents($reelPath, json_encode($this->reelDefinition, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        $this->writeStatus('rendering', 'Video wird gerendert …');

        $engineDir = base_path('video-engine');
        $cli = $engineDir . '/src/reel-cli.ts';

        if (! is_file($cli)) {
            $this->writeStatus('failed', 'Video-Engine (reel-cli.ts) nicht gefunden.');

            return;
        }

        $cmd = sprintf(
            'cd %s && node_modules/.bin/tsx %s %s %s',
            escapeshellarg($engineDir),
            escapeshellarg('src/reel-cli.ts'),
            escapeshellarg($reelPath),
            escapeshellarg($outputPath),
        );

        try {
            $result = Process::timeout($this->timeout)
                ->env($this->engineEnv())
                ->run($cmd);

            if ($result->successful() && is_file($outputPath)) {
                $this->writeStatus('done', 'Video erstellt.', [
                    'download_url' => url('videos/social/' . $this->jobId . '.mp4'),
                    'size_bytes'   => filesize($outputPath),
                ]);

                return;
            }

            $msg = trim($result->errorOutput() ?: $result->output());
            Log::channel('export')->error('Social-Video Rendering fehlgeschlagen', ['job' => $this->jobId, 'output' => $msg]);
            $this->writeStatus('failed', 'Rendering fehlgeschlagen: ' . $this->lastLine($msg));
        } catch (\Throwable $e) {
            Log::channel('export')->error('Social-Video Rendering Exception', ['job' => $this->jobId, 'error' => $e->getMessage()]);
            $this->writeStatus('failed', 'Rendering-Fehler: ' . $e->getMessage());
        }
    }

    public function failed(\Throwable $e): void
    {
        $this->writeStatus('failed', 'Job abgebrochen: ' . $e->getMessage());
    }

    // --- Status-Helfer (dateibasiert, ohne Migration) ---

    public static function jobDir(string $jobId): string
    {
        return storage_path('app/social-video/' . $jobId);
    }

    public static function statusPath(string $jobId): string
    {
        return self::jobDir($jobId) . '/status.json';
    }

    private function writeStatus(string $status, string $message, array $extra = []): void
    {
        $dir = self::jobDir($this->jobId);
        @mkdir($dir, 0775, true);

        $payload = array_merge([
            'job_id'     => $this->jobId,
            'status'     => $status, // queued | rendering | done | failed
            'message'    => $message,
            'updated_at' => now()->toIso8601String(),
        ], $extra);

        file_put_contents(self::statusPath($this->jobId), json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function lastLine(string $text): string
    {
        $lines = array_values(array_filter(explode("\n", $text), fn ($l) => trim($l) !== ''));

        return $lines ? trim(end($lines)) : 'unbekannter Fehler';
    }

    private function engineEnv(): array
    {
        // Werte über config() lesen (NICHT env()): bleibt bei gecachter Config
        // verfügbar – sonst liefert env() in Produktion null und der Browser-Pfad
        // erreicht die Engine nicht ("Chromium nicht gefunden").
        return array_filter([
            'ANYPIM_BASE_URL'     => config('app.url') ?: (config('video.base_url') ?: 'http://localhost:8000'),
            'VIDEO_DISPLAY'       => config('video.display'),
            'VIDEO_FPS'           => config('video.fps'),
            'VIDEO_QUALITY'       => config('video.quality'),
            'ELEVENLABS_API_KEY'  => config('video.elevenlabs.api_key'),
            'ELEVENLABS_FALLBACK' => config('video.elevenlabs.fallback'),
            // Optionale, explizite Binary-Pfade. Leer lassen → die Engine löst
            // ffmpeg (PATH) und Chromium (bereitgestellter Browser) selbst auf.
            'FFMPEG_PATH'             => config('video.ffmpeg_path'),
            'PLAYWRIGHT_CHROMIUM_PATH' => config('video.chromium_path'),
            'PLAYWRIGHT_BROWSERS_PATH' => config('video.browsers_path'),
            'APP_ENV'             => app()->environment(),
        ]);
    }
}

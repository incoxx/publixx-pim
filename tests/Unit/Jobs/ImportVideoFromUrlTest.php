<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Jobs\ImportVideoFromUrl;
use App\Jobs\ProcessAudioVideoMedia;
use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportVideoFromUrlTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';

    public function test_erfolgreicher_download_aktualisiert_media_und_dispatcht_av_verarbeitung(): void
    {
        Storage::fake('public');
        Queue::fake();

        $media = Media::factory()->create([
            'file_path' => '',
            'mime_type' => 'video/mp4',
            'media_type' => 'video',
            'source_url' => self::URL,
            'av_processing_status' => 'pending',
            // Der echte Platzhalter aus MediaController::importVideoFromUrl() hat noch
            // keinen Titel — die Factory würde sonst einen zufälligen Titel generieren,
            // der (job-seitig korrekt) Vorrang vor dem yt-dlp-Titel hätte.
            'title_de' => null,
        ]);

        Process::fake([
            '*yt-dlp*' => function ($process) {
                $command = $process->command;
                $index = array_search('-o', $command, true);
                $destination = str_replace('%(ext)s', 'mp4', $command[$index + 1]);
                if (! is_dir(dirname($destination))) {
                    mkdir(dirname($destination), 0755, true);
                }
                file_put_contents($destination, 'fake-video-bytes');

                return Process::result(output: "Rick Astley - Never Gonna Give You Up\n");
            },
        ]);

        (new ImportVideoFromUrl($media->id, self::URL))->handle();

        $media->refresh();

        $this->assertNotSame('', $media->file_path);
        Storage::disk('public')->assertExists($media->file_path);
        $this->assertSame('video', $media->media_type);
        $this->assertSame('Rick Astley - Never Gonna Give You Up', $media->title_de);
        $this->assertGreaterThan(0, $media->file_size);

        // MediaObserver::handleAvProcessing() wird durch das reguläre update() im Job
        // ausgelöst (file_path/mime_type/media_type haben sich geändert).
        Queue::assertPushed(ProcessAudioVideoMedia::class);
    }

    public function test_nicht_erlaubter_host_setzt_error_status_ohne_download(): void
    {
        $media = Media::factory()->create([
            'file_path' => '',
            'media_type' => 'video',
            'source_url' => 'https://example.com/video.mp4',
            'av_processing_status' => 'pending',
        ]);

        Process::fake();

        (new ImportVideoFromUrl($media->id, 'https://example.com/video.mp4'))->handle();

        $media->refresh();

        $this->assertSame('error', $media->av_processing_status);
        Process::assertNothingRan();
    }

    public function test_yt_dlp_fehler_setzt_error_status(): void
    {
        $media = Media::factory()->create([
            'file_path' => '',
            'media_type' => 'video',
            'source_url' => self::URL,
            'av_processing_status' => 'pending',
        ]);

        Process::fake([
            '*yt-dlp*' => Process::result(errorOutput: 'ERROR: Video unavailable', exitCode: 1),
        ]);

        $this->expectException(\RuntimeException::class);

        try {
            (new ImportVideoFromUrl($media->id, self::URL))->handle();
        } finally {
            $media->refresh();
            $this->assertSame('error', $media->av_processing_status);
            $this->assertNotNull($media->av_error_message);
        }
    }
}

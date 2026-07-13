<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Jobs\ProcessAudioVideoMedia;
use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessAudioVideoMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_erfolgreiche_verarbeitung_setzt_dauer_und_video_thumbnail(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('media/clip.mp4', 'fake-video-content');

        $media = Media::factory()->create([
            'file_name' => 'clip.mp4',
            'file_path' => 'media/clip.mp4',
            'mime_type' => 'video/mp4',
            'media_type' => 'video',
            'av_processing_status' => 'pending',
        ]);

        Process::fake([
            '*ffprobe*' => Process::result(output: "12.500000\n"),
            '*ffmpeg*' => function ($process) {
                // Letztes Argument ist der Zielpfad des zu extrahierenden Frames
                $destination = end($process->command);
                if (! is_dir(dirname($destination))) {
                    mkdir(dirname($destination), 0755, true);
                }
                file_put_contents($destination, 'fake-jpeg-content');

                return Process::result();
            },
        ]);

        (new ProcessAudioVideoMedia($media->id))->handle();

        $media->refresh();

        $this->assertSame('ready', $media->av_processing_status);
        $this->assertSame(13, $media->duration_seconds); // round(12.5) => 13
        $this->assertNotNull($media->video_thumbnail_path);
        Storage::disk('public')->assertExists($media->video_thumbnail_path);
    }

    public function test_erfolgreiche_verarbeitung_bei_audio_ohne_thumbnail(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('media/song.mp3', 'fake-audio-content');

        $media = Media::factory()->create([
            'file_name' => 'song.mp3',
            'file_path' => 'media/song.mp3',
            'mime_type' => 'audio/mpeg',
            'media_type' => 'audio',
            'av_processing_status' => 'pending',
        ]);

        Process::fake([
            '*ffprobe*' => Process::result(output: "180.000000\n"),
        ]);

        (new ProcessAudioVideoMedia($media->id))->handle();

        $media->refresh();

        $this->assertSame('ready', $media->av_processing_status);
        $this->assertSame(180, $media->duration_seconds);
        $this->assertNull($media->video_thumbnail_path);
    }

    public function test_ffprobe_fehler_setzt_error_status(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('media/broken.mp4', 'not-a-real-video');

        $media = Media::factory()->create([
            'file_name' => 'broken.mp4',
            'file_path' => 'media/broken.mp4',
            'mime_type' => 'video/mp4',
            'media_type' => 'video',
            'av_processing_status' => 'pending',
        ]);

        Process::fake([
            '*ffprobe*' => Process::result(errorOutput: 'Invalid data found when processing input', exitCode: 1),
        ]);

        $this->expectException(\RuntimeException::class);

        try {
            (new ProcessAudioVideoMedia($media->id))->handle();
        } finally {
            $media->refresh();
            $this->assertSame('error', $media->av_processing_status);
            $this->assertNotNull($media->av_error_message);
        }
    }

    public function test_nicht_av_medium_wird_uebersprungen(): void
    {
        Storage::fake('public');

        $media = Media::factory()->create([
            'media_type' => 'image',
            'av_processing_status' => null,
        ]);

        Process::fake();

        (new ProcessAudioVideoMedia($media->id))->handle();

        Process::assertNothingRan();
    }
}

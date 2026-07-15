<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Jobs\ProcessAudioVideoMedia;
use App\Models\Media;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->user = User::factory()->create();
        $role = Role::findOrCreate('Admin', 'sanctum');
        $this->user->assignRole($role);
        $this->actingAs($this->user);
    }

    public function test_index_returns_paginated_media(): void
    {
        Media::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/media');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_index_hides_generated_renditions_by_default(): void
    {
        Media::factory()->count(2)->create(); // normale, manuell hochgeladene Medien
        Media::factory()->create(['generated_at' => now()]); // Pipeline-generierte Rendition

        $this->getJson('/api/v1/media')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/v1/media?include_renditions=true')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_store_uploads_media(): void
    {
        Storage::fake('media');

        $response = $this->postJson('/api/v1/media', [
            'file' => UploadedFile::fake()->image('test.jpg', 640, 480),
            'title_de' => 'Testbild',
        ]);

        $response->assertCreated();
    }

    public function test_show_returns_media(): void
    {
        $media = Media::factory()->create();

        $response = $this->getJson("/api/v1/media/{$media->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $media->id);
    }

    public function test_update_modifies_media(): void
    {
        $media = Media::factory()->create(['title_de' => 'Alt']);

        $response = $this->putJson("/api/v1/media/{$media->id}", [
            'title_de' => 'Neu',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.title_de', 'Neu');
    }

    public function test_update_media_type_auf_audio_dispatcht_av_verarbeitung(): void
    {
        // Regression: reine media_type-Umklassifizierung (z.B. manuell über das UI-Dropdown
        // von 'other' auf 'audio' korrigiert) muss ProcessAudioVideoMedia auslösen, auch ohne
        // dass sich file_path/file_size/mime_type ändern.
        Queue::fake();

        $media = Media::factory()->create([
            'mime_type' => 'audio/mpeg',
            'media_type' => 'other',
            'file_path' => 'media/song.mp3',
        ]);

        $response = $this->putJson("/api/v1/media/{$media->id}", [
            'media_type' => 'audio',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.media_type', 'audio');

        Queue::assertPushed(ProcessAudioVideoMedia::class);
    }

    public function test_destroy_deletes_media(): void
    {
        $media = Media::factory()->create();

        $response = $this->deleteJson("/api/v1/media/{$media->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('media', ['id' => $media->id]);
    }

    public function test_unauthenticated_access_returns_401(): void
    {
        app('auth')->forgetGuards();

        $response = $this->getJson('/api/v1/media');

        $response->assertUnauthorized();
    }

    // ── Upload-Validierung ────────────────────────────────────────────

    public function test_store_validiert_pflichtfeld_datei(): void
    {
        $response = $this->postJson('/api/v1/media', [
            'title_de' => 'Ohne Datei',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    public function test_store_lehnt_zu_grosse_datei_ab(): void
    {
        Storage::fake('media');

        // Limit liegt bei 200 MB (max:204800 KB)
        $response = $this->postJson('/api/v1/media', [
            'file' => UploadedFile::fake()->create('riesig.jpg', 204801),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    public function test_store_lehnt_unerlaubten_dateityp_ab(): void
    {
        Storage::fake('public');

        $response = $this->postJson('/api/v1/media', [
            'file' => UploadedFile::fake()->create('script.exe', 10),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    public function test_store_liefert_422_statt_500_bei_nicht_datei_wert(): void
    {
        // Regression: die Extension-Validierungs-Closure rief zuvor unconditional
        // getClientOriginalExtension() auf und crashte mit 500, wenn 'file' kein Upload war.
        $response = $this->postJson('/api/v1/media', [
            'file' => 'kein-upload-sondern-ein-string',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    public function test_store_uploads_mp3_audio(): void
    {
        Queue::fake();
        Storage::fake('public');

        $response = $this->postJson('/api/v1/media', [
            'file' => UploadedFile::fake()->create('song.mp3', 500, 'audio/mpeg'),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.media_type', 'audio');

        Queue::assertPushed(ProcessAudioVideoMedia::class);
    }

    public function test_store_uploads_mp4_video(): void
    {
        Queue::fake();
        Storage::fake('public');

        $response = $this->postJson('/api/v1/media', [
            'file' => UploadedFile::fake()->create('clip.mp4', 2000, 'video/mp4'),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.media_type', 'video');

        Queue::assertPushed(ProcessAudioVideoMedia::class);
    }

    // ── Lösch-Constraints ─────────────────────────────────────────────

    public function test_destroy_mit_produktzuordnung_liefert_409(): void
    {
        Storage::fake('public');

        $media = Media::factory()->create();
        \App\Models\ProductMediaAssignment::factory()->create(['media_id' => $media->id]);

        $response = $this->deleteJson("/api/v1/media/{$media->id}");

        $response->assertStatus(409)
            ->assertJsonPath('type', 'deletion_constraint');

        $this->assertDatabaseHas('media', ['id' => $media->id]);
    }

    public function test_destroy_mit_force_loescht_trotz_zuordnung(): void
    {
        Storage::fake('public');

        $media = Media::factory()->create();
        \App\Models\ProductMediaAssignment::factory()->create(['media_id' => $media->id]);

        $response = $this->deleteJson("/api/v1/media/{$media->id}?force=true");

        $response->assertNoContent();
        $this->assertDatabaseMissing('media', ['id' => $media->id]);
    }

    public function test_bulk_delete_ueberspringt_medien_mit_zuordnungen(): void
    {
        Storage::fake('public');

        $assigned = Media::factory()->create();
        \App\Models\ProductMediaAssignment::factory()->create(['media_id' => $assigned->id]);
        $unassigned = Media::factory()->create();

        $response = $this->postJson('/api/v1/media/bulk-delete', [
            'media_ids' => [$assigned->id, $unassigned->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('deleted', 1)
            ->assertJsonPath('skipped', 1);

        $this->assertDatabaseHas('media', ['id' => $assigned->id]);
        $this->assertDatabaseMissing('media', ['id' => $unassigned->id]);
    }

    public function test_bulk_delete_mit_force_loescht_zugeordnete_medien(): void
    {
        Storage::fake('public');

        $assigned = Media::factory()->create();
        \App\Models\ProductMediaAssignment::factory()->create(['media_id' => $assigned->id]);

        $response = $this->postJson('/api/v1/media/bulk-delete', [
            'media_ids' => [$assigned->id],
            'force' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('deleted', 1)
            ->assertJsonPath('skipped', 0);

        $this->assertDatabaseMissing('media', ['id' => $assigned->id]);
    }

    // ── Zuordnungen / Verschieben ─────────────────────────────────────

    public function test_bulk_move_verschiebt_medien_in_ordner(): void
    {
        $folder = \App\Models\HierarchyNode::factory()->create();
        $media = Media::factory()->count(2)->create(['asset_folder_id' => null]);

        $response = $this->postJson('/api/v1/media/bulk-move', [
            'media_ids' => $media->pluck('id')->all(),
            'asset_folder_id' => $folder->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('moved', 2);

        foreach ($media as $m) {
            $this->assertDatabaseHas('media', ['id' => $m->id, 'asset_folder_id' => $folder->id]);
        }
    }

    public function test_bulk_move_validiert_unbekannten_ordner(): void
    {
        $media = Media::factory()->create();

        $this->postJson('/api/v1/media/bulk-move', [
            'media_ids' => [$media->id],
            'asset_folder_id' => '00000000-0000-0000-0000-000000000000',
        ])->assertUnprocessable();
    }

    // ── URL-Import ────────────────────────────────────────────────────

    public function test_import_from_url_lehnt_interne_url_ab(): void
    {
        $response = $this->postJson('/api/v1/media/import-url', [
            'url' => 'http://127.0.0.1/geheim.jpg',
        ]);

        $response->assertUnprocessable();
        $this->assertDatabaseCount('media', 0);
    }

    public function test_import_from_url_importiert_bild(): void
    {
        Storage::fake('public');

        // Echte PNG-Bytes erzeugen, damit MIME-Detection und getimagesize greifen
        $img = imagecreatetruecolor(2, 2);
        ob_start();
        imagepng($img);
        $pngBytes = (string) ob_get_clean();
        imagedestroy($img);

        \Illuminate\Support\Facades\Http::fake([
            '*' => \Illuminate\Support\Facades\Http::response($pngBytes, 200, ['Content-Type' => 'image/png']),
        ]);

        // Öffentliche IP statt Hostname, damit der SSRF-Check keine DNS-Auflösung braucht
        $response = $this->postJson('/api/v1/media/import-url', [
            'url' => 'http://93.184.216.34/bilder/test.png',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.mime_type', 'image/png')
            ->assertJsonPath('data.media_type', 'image');

        $this->assertDatabaseHas('media', ['original_file_name' => 'test.png']);
    }

    // ── Video-URL-Import (yt-dlp) ──────────────────────────────────────

    public function test_import_video_from_url_lehnt_interne_url_ab(): void
    {
        $response = $this->postJson('/api/v1/media/import-video-url', [
            'url' => 'http://127.0.0.1/video',
        ]);

        $response->assertUnprocessable();
        $this->assertDatabaseCount('media', 0);
    }

    public function test_import_video_from_url_lehnt_nicht_erlaubten_host_ab(): void
    {
        $response = $this->postJson('/api/v1/media/import-video-url', [
            'url' => 'https://example.com/beliebiges-video.mp4',
        ]);

        $response->assertUnprocessable();
        $this->assertDatabaseCount('media', 0);
    }

    public function test_import_video_from_url_legt_platzhalter_an_und_dispatcht_job(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/v1/media/import-video-url', [
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.media_type', 'video')
            ->assertJsonPath('data.av_processing_status', 'pending')
            ->assertJsonPath('data.source_url', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ');

        Queue::assertPushed(\App\Jobs\ImportVideoFromUrl::class);
    }

    public function test_auto_match_ignoriert_generierte_renditions(): void
    {
        $product = \App\Models\Product::factory()->create(['sku' => 'ABC-123']);
        Media::factory()->create(['file_name' => 'ABC-123.jpg']);
        Media::factory()->create(['file_name' => 'ABC-123.jpg', 'generated_at' => now()]);

        $response = $this->postJson('/api/v1/media/auto-match', [
            'pattern' => '/^([A-Z]{3}-\d{3})/',
            'dry_run' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('matched', 1)
            ->assertJsonPath('total_media', 1);
    }
}

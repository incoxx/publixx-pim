<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Media;
use App\Models\MediaRenditionPreset;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaMotifControllerTest extends TestCase
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

    /**
     * Legt ein Media-Asset mit einer echten JPEG-Datei auf dem (gefaketen) public-Disk an.
     */
    private function createMediaWithRealFile(int $width = 1200, int $height = 900): Media
    {
        $media = Media::factory()->create([
            'file_name' => 'motiv-master.jpg',
            'file_path' => 'media/motiv-master.jpg',
            'mime_type' => 'image/jpeg',
            'width' => $width,
            'height' => $height,
        ]);

        $disk = Storage::disk('public');
        $disk->makeDirectory('media');
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 50, 50));
        imagejpeg($image, $disk->path($media->file_path), 90);
        imagedestroy($image);

        return $media;
    }

    public function test_store_promotes_media_to_motif(): void
    {
        Storage::fake('public');
        $media = $this->createMediaWithRealFile();

        $response = $this->postJson('/api/v1/media-motifs', [
            'media_id' => $media->id,
            'title_de' => 'Akkubohrer Professional',
            'rights_holder' => 'Fotostudio Muster GmbH',
            'creator' => 'Max Mustermann',
            'credit_line' => 'Foto: Fotostudio Muster GmbH',
            'license_type' => 'exklusiv',
            'keywords' => ['akkubohrer', 'werkzeug', 'profi'],
            'valid_from' => '2026-01-01',
            'valid_until' => '2026-12-31',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title_de', 'Akkubohrer Professional')
            ->assertJsonPath('data.creator', 'Max Mustermann')
            ->assertJsonPath('data.keywords', ['akkubohrer', 'werkzeug', 'profi'])
            ->assertJsonPath('data.valid_from', '2026-01-01')
            ->assertJsonPath('data.valid_until', '2026-12-31')
            ->assertJsonPath('data.is_currently_valid', true)
            ->assertJsonPath('data.master_rendition.id', $media->id);

        $this->assertDatabaseHas('media', [
            'id' => $media->id,
            'is_master_rendition' => true,
        ]);
        $this->assertNotNull($media->fresh()->motif_id);
    }

    public function test_metadata_and_validity_period_are_optional(): void
    {
        Storage::fake('public');
        $media = $this->createMediaWithRealFile();

        $response = $this->postJson('/api/v1/media-motifs', ['media_id' => $media->id]);

        $response->assertCreated()
            ->assertJsonPath('data.creator', null)
            ->assertJsonPath('data.keywords', [])
            ->assertJsonPath('data.valid_from', null)
            ->assertJsonPath('data.valid_until', null)
            ->assertJsonPath('data.is_currently_valid', true);
    }

    public function test_store_rejects_media_already_in_motif(): void
    {
        Storage::fake('public');
        $media = $this->createMediaWithRealFile();

        $this->postJson('/api/v1/media-motifs', ['media_id' => $media->id])->assertCreated();

        $response = $this->postJson('/api/v1/media-motifs', ['media_id' => $media->id]);

        $response->assertStatus(422);
    }

    public function test_generate_renditions_creates_variants_and_reports_unsupported_preset(): void
    {
        Storage::fake('public');
        $media = $this->createMediaWithRealFile();

        $motifResponse = $this->postJson('/api/v1/media-motifs', ['media_id' => $media->id]);
        $motifId = $motifResponse->json('data.id');

        $response = $this->postJson("/api/v1/media-motifs/{$motifId}/generate-renditions");

        $response->assertOk();

        // 4 RGB-Presets sollten mit GD funktionieren, das CMYK/TIFF-Preset schlägt ohne Imagick fehl
        $generatedCount = count($response->json('generated'));
        $errors = $response->json('errors');

        $this->assertGreaterThanOrEqual(4, $generatedCount);
        $this->assertArrayHasKey('print-tiff-cmyk-300', $errors);

        $this->assertDatabaseHas('media', [
            'motif_id' => $motifId,
            'rendition_channel' => 'web',
        ]);
    }

    public function test_destroy_ungroups_motif_and_keeps_master_media(): void
    {
        Storage::fake('public');
        $media = $this->createMediaWithRealFile();

        $motifResponse = $this->postJson('/api/v1/media-motifs', ['media_id' => $media->id]);
        $motifId = $motifResponse->json('data.id');

        $this->postJson("/api/v1/media-motifs/{$motifId}/generate-renditions", [
            'preset_ids' => [MediaRenditionPreset::where('technical_name', 'web-jpeg-rgb')->value('id')],
        ])->assertOk();

        $response = $this->deleteJson("/api/v1/media-motifs/{$motifId}");
        $response->assertOk();

        $this->assertDatabaseMissing('media_motifs', ['id' => $motifId]);
        $this->assertDatabaseHas('media', ['id' => $media->id, 'motif_id' => null, 'is_master_rendition' => false]);
        $this->assertDatabaseMissing('media', ['motif_id' => $motifId]);
    }

    public function test_destroy_blocked_when_motif_directly_assigned_to_product(): void
    {
        Storage::fake('public');
        $media = $this->createMediaWithRealFile();
        $motifId = $this->postJson('/api/v1/media-motifs', ['media_id' => $media->id])->json('data.id');

        \App\Models\ProductMediaAssignment::factory()->create(['motif_id' => $motifId, 'media_id' => null]);

        $response = $this->deleteJson("/api/v1/media-motifs/{$motifId}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('media_motifs', ['id' => $motifId]);
    }

    public function test_direct_motif_assignment_marks_motif_as_used(): void
    {
        Storage::fake('public');
        $media = $this->createMediaWithRealFile();
        $motifId = $this->postJson('/api/v1/media-motifs', ['media_id' => $media->id])->json('data.id');

        $this->getJson("/api/v1/media-motifs/{$motifId}")->assertJsonPath('data.is_used', false);

        \App\Models\ProductMediaAssignment::factory()->create(['motif_id' => $motifId, 'media_id' => null]);

        $this->getJson("/api/v1/media-motifs/{$motifId}")->assertJsonPath('data.is_used', true);
        $this->getJson('/api/v1/media-motifs?filter[is_used]=true')->assertJsonCount(1, 'data');
    }

    public function test_index_supports_search_and_is_used_filter(): void
    {
        Storage::fake('public');

        $used = $this->createMediaWithRealFile();
        $usedMotifId = $this->postJson('/api/v1/media-motifs', [
            'media_id' => $used->id,
            'title_de' => 'Akkuschrauber MiniDrive',
        ])->json('data.id');

        \App\Models\ProductMediaAssignment::factory()->create([
            'media_id' => $used->id,
        ]);

        $unused = $this->createMediaWithRealFile();
        $unused->update(['file_name' => 'anderes-bild.jpg', 'file_path' => 'media/anderes-bild.jpg']);
        $this->postJson('/api/v1/media-motifs', [
            'media_id' => $unused->id,
            'title_de' => 'Schleifgerät Pro',
        ])->assertCreated();

        // Suche nach Titel
        $searchResponse = $this->getJson('/api/v1/media-motifs?search=Akkuschrauber');
        $searchResponse->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title_de', 'Akkuschrauber MiniDrive');

        // Filter: nur verwendete Motive
        $usedResponse = $this->getJson('/api/v1/media-motifs?filter[is_used]=true');
        $usedResponse->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $usedMotifId)
            ->assertJsonPath('data.0.is_used', true);

        // Filter: nur nicht verwendete Motive
        $unusedResponse = $this->getJson('/api/v1/media-motifs?filter[is_used]=false');
        $unusedResponse->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_used', false);
    }

    public function test_show_reports_is_used_for_single_motif(): void
    {
        Storage::fake('public');
        $media = $this->createMediaWithRealFile();
        $motifId = $this->postJson('/api/v1/media-motifs', ['media_id' => $media->id])->json('data.id');

        $this->getJson("/api/v1/media-motifs/{$motifId}")
            ->assertOk()
            ->assertJsonPath('data.is_used', false);

        \App\Models\ProductMediaAssignment::factory()->create(['media_id' => $media->id]);

        $this->getJson("/api/v1/media-motifs/{$motifId}")
            ->assertOk()
            ->assertJsonPath('data.is_used', true);
    }
}

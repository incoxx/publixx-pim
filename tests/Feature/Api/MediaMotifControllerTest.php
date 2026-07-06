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
            'license_type' => 'exklusiv',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title_de', 'Akkubohrer Professional')
            ->assertJsonPath('data.master_rendition.id', $media->id);

        $this->assertDatabaseHas('media', [
            'id' => $media->id,
            'is_master_rendition' => true,
        ]);
        $this->assertNotNull($media->fresh()->motif_id);
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
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Media;
use App\Models\MediaUsageType;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\Media\MediaRenditionPipelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductMediaControllerTest extends TestCase
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

    public function test_store_assigns_single_media_as_before(): void
    {
        $product = Product::factory()->create();
        $media = Media::factory()->create();
        $usageType = MediaUsageType::factory()->create();

        $response = $this->postJson("/api/v1/products/{$product->id}/media", [
            'media_id' => $media->id,
            'usage_type_id' => $usageType->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.media_id', $media->id)
            ->assertJsonPath('data.motif_id', null);
    }

    public function test_store_assigns_a_motif_instead_of_single_media(): void
    {
        Storage::fake('public');

        $product = Product::factory()->create();
        $usageType = MediaUsageType::factory()->create();

        $master = Media::factory()->create([
            'file_name' => 'motiv-master.jpg',
            'file_path' => 'media/motiv-master.jpg',
            'mime_type' => 'image/jpeg',
        ]);
        $disk = Storage::disk('public');
        $disk->makeDirectory('media');
        $image = imagecreatetruecolor(400, 300);
        imagefill($image, 0, 0, imagecolorallocate($image, 10, 10, 10));
        imagejpeg($image, $disk->path($master->file_path), 90);
        imagedestroy($image);

        $motif = app(MediaRenditionPipelineService::class)->promoteToMotif($master, ['title_de' => 'Akkuschrauber']);

        $response = $this->postJson("/api/v1/products/{$product->id}/media", [
            'motif_id' => $motif->id,
            'usage_type_id' => $usageType->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.media_id', null)
            ->assertJsonPath('data.motif_id', $motif->id)
            ->assertJsonPath('data.motif.title_de', 'Akkuschrauber');

        $this->assertNotNull($response->json('data.preview_thumb_url'));
    }

    public function test_store_rejects_both_media_and_motif(): void
    {
        $product = Product::factory()->create();
        $media = Media::factory()->create();
        $usageType = MediaUsageType::factory()->create();

        $motifMedia = Media::factory()->create();
        $motif = app(MediaRenditionPipelineService::class)->promoteToMotif($motifMedia);

        $response = $this->postJson("/api/v1/products/{$product->id}/media", [
            'media_id' => $media->id,
            'motif_id' => $motif->id,
            'usage_type_id' => $usageType->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_store_rejects_neither_media_nor_motif(): void
    {
        $product = Product::factory()->create();
        $usageType = MediaUsageType::factory()->create();

        $response = $this->postJson("/api/v1/products/{$product->id}/media", [
            'usage_type_id' => $usageType->id,
        ]);

        $response->assertStatus(422);
    }
}

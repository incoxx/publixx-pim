<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use App\Models\Role;
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
}

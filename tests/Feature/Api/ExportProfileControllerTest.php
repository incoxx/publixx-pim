<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\ExportProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Models\Role;
use Tests\TestCase;

class ExportProfileControllerTest extends TestCase
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

    public function test_index_returns_export_profiles(): void
    {
        ExportProfile::factory()->count(2)->create(['user_id' => $this->user->id]);

        $response = $this->getJson('/api/v1/export-profiles');

        $response->assertOk();
    }

    public function test_store_creates_export_profile(): void
    {
        $response = $this->postJson('/api/v1/export-profiles', [
            'name' => 'My Export',
            'is_shared' => false,
            'include_products' => true,
            'include_attributes' => true,
            'include_hierarchies' => false,
            'include_prices' => false,
            'include_relations' => false,
            'include_media' => false,
            'include_variants' => false,
            'languages' => ['de', 'en'],
            'format' => 'excel',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'My Export');

        $this->assertDatabaseHas('export_profiles', ['name' => 'My Export']);
    }

    public function test_update_modifies_export_profile(): void
    {
        $profile = ExportProfile::factory()->create(['user_id' => $this->user->id]);

        $response = $this->putJson("/api/v1/export-profiles/{$profile->id}", [
            'name' => 'Updated Export',
        ]);

        $response->assertOk();
        $this->assertEquals('Updated Export', $profile->fresh()->name);
    }

    public function test_destroy_deletes_export_profile(): void
    {
        $profile = ExportProfile::factory()->create(['user_id' => $this->user->id]);

        $response = $this->deleteJson("/api/v1/export-profiles/{$profile->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('export_profiles', ['id' => $profile->id]);
    }

    public function test_shared_profiles_visible_to_other_users(): void
    {
        $otherUser = User::factory()->create();
        ExportProfile::factory()->shared()->create(['user_id' => $otherUser->id]);

        $response = $this->getJson('/api/v1/export-profiles');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }
}

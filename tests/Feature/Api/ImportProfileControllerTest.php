<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\ImportProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ImportProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $role = Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        $this->user->assignRole($role);
        Sanctum::actingAs($this->user);
    }

    public function test_index_returns_import_profiles(): void
    {
        $response = $this->getJson('/api/v1/import-profiles');

        $response->assertOk();
    }

    public function test_store_creates_import_profile(): void
    {
        $response = $this->postJson('/api/v1/import-profiles', [
            'name' => 'CSV Import',
            'is_shared' => false,
            'sku_column' => 'SKU',
            'column_mappings' => [],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'CSV Import');
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/import-profiles', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_destroy_deletes_import_profile(): void
    {
        $profile = ImportProfile::factory()->create(['user_id' => $this->user->id]);

        $response = $this->deleteJson("/api/v1/import-profiles/{$profile->id}");

        $response->assertNoContent();
    }
}

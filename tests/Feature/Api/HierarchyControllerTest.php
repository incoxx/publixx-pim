<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Hierarchy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Models\Role;
use Tests\TestCase;

class HierarchyControllerTest extends TestCase
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

    public function test_index_returns_hierarchies(): void
    {
        Hierarchy::factory()->count(2)->create();

        $response = $this->getJson('/api/v1/hierarchies');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_store_creates_hierarchy(): void
    {
        $response = $this->postJson('/api/v1/hierarchies', [
            'technical_name' => 'main-catalog',
            'name_de' => 'Hauptkatalog',
            'name_en' => 'Main Catalog',
            'hierarchy_type' => 'master',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.technical_name', 'main-catalog');

        $this->assertDatabaseHas('hierarchies', ['technical_name' => 'main-catalog']);
    }

    public function test_show_returns_hierarchy(): void
    {
        $hierarchy = Hierarchy::factory()->create();

        $response = $this->getJson("/api/v1/hierarchies/{$hierarchy->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $hierarchy->id);
    }

    public function test_update_modifies_hierarchy(): void
    {
        $hierarchy = Hierarchy::factory()->create(['name_de' => 'Alt']);

        $response = $this->putJson("/api/v1/hierarchies/{$hierarchy->id}", [
            'name_de' => 'Neu',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name_de', 'Neu');
    }

    public function test_destroy_deletes_hierarchy(): void
    {
        $hierarchy = Hierarchy::factory()->create();

        $response = $this->deleteJson("/api/v1/hierarchies/{$hierarchy->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('hierarchies', ['id' => $hierarchy->id]);
    }

    public function test_dependencies_returns_info(): void
    {
        $hierarchy = Hierarchy::factory()->create();

        $response = $this->getJson("/api/v1/hierarchies/{$hierarchy->id}/dependencies");

        $response->assertOk()
            ->assertJsonStructure(['data' => ['has_dependencies', 'total_count', 'dependencies']]);
    }
}

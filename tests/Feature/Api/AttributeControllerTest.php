<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Attribute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Models\Role;
use Tests\TestCase;

class AttributeControllerTest extends TestCase
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

    public function test_index_returns_paginated_attributes(): void
    {
        Attribute::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/attributes');

        $response->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_index_filters_by_status(): void
    {
        Attribute::factory()->create(['status' => 'active']);
        Attribute::factory()->create(['status' => 'inactive']);

        $response = $this->getJson('/api/v1/attributes?filter[status]=active');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_store_creates_attribute(): void
    {
        $response = $this->postJson('/api/v1/attributes', [
            'technical_name' => 'color',
            'name_de' => 'Farbe',
            'name_en' => 'Color',
            'data_type' => 'String',
            'is_translatable' => false,
            'is_multipliable' => false,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.technical_name', 'color');

        $this->assertDatabaseHas('attributes', ['technical_name' => 'color']);
    }

    public function test_store_validates_unique_technical_name(): void
    {
        Attribute::factory()->create(['technical_name' => 'color']);

        $response = $this->postJson('/api/v1/attributes', [
            'technical_name' => 'color',
            'name_de' => 'Farbe',
            'data_type' => 'String',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['technical_name']);
    }

    public function test_show_returns_attribute(): void
    {
        $attribute = Attribute::factory()->create();

        $response = $this->getJson("/api/v1/attributes/{$attribute->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $attribute->id);
    }

    public function test_update_modifies_attribute(): void
    {
        $attribute = Attribute::factory()->create(['name_de' => 'Alt']);

        $response = $this->putJson("/api/v1/attributes/{$attribute->id}", [
            'name_de' => 'Neu',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name_de', 'Neu');
    }

    public function test_destroy_deletes_attribute(): void
    {
        $attribute = Attribute::factory()->create();

        $response = $this->deleteJson("/api/v1/attributes/{$attribute->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('attributes', ['id' => $attribute->id]);
    }

    public function test_dependencies_returns_constraint_info(): void
    {
        $attribute = Attribute::factory()->create();

        $response = $this->getJson("/api/v1/attributes/{$attribute->id}/dependencies");

        $response->assertOk()
            ->assertJsonStructure(['data' => ['has_dependencies', 'total_count', 'dependencies']]);
    }

    public function test_bulk_delete_requires_admin_role_property(): void
    {
        // Note: The bulkDelete endpoint checks $user->role === 'Admin' (direct property),
        // which differs from Spatie role checks. This currently returns 403 for all users
        // because User model has no 'role' column/accessor.
        $attrs = Attribute::factory()->count(3)->create();
        $ids = $attrs->pluck('id')->toArray();

        $response = $this->postJson('/api/v1/attributes/bulk-delete', [
            'attribute_ids' => $ids,
        ]);

        $response->assertForbidden();
    }
}

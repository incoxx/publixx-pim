<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\ProductType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Models\Role;
use Tests\TestCase;

class ProductControllerTest extends TestCase
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

    public function test_index_returns_paginated_products(): void
    {
        Product::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/products');

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'sku', 'name', 'status']],
                'meta' => ['current_page', 'per_page', 'total'],
            ]);
    }

    public function test_index_filters_by_status(): void
    {
        Product::factory()->create(['status' => 'active']);
        Product::factory()->create(['status' => 'draft']);

        $response = $this->getJson('/api/v1/products?filter[status]=active');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'active');
    }

    public function test_store_creates_product(): void
    {
        $productType = ProductType::factory()->create();

        $response = $this->postJson('/api/v1/products', [
            'product_type_id' => $productType->id,
            'sku' => 'TEST-001',
            'name' => 'Test Product',
            'status' => 'draft',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.sku', 'TEST-001');

        $this->assertDatabaseHas('products', ['sku' => 'TEST-001']);
    }

    public function test_show_returns_product(): void
    {
        $product = Product::factory()->create();

        $response = $this->getJson("/api/v1/products/{$product->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.sku', $product->sku);
    }

    public function test_show_with_includes(): void
    {
        $product = Product::factory()->create();

        $response = $this->getJson("/api/v1/products/{$product->id}?include=productType");

        $response->assertOk()
            ->assertJsonStructure(['data' => ['id', 'product_type']]);
    }

    public function test_update_modifies_product(): void
    {
        $product = Product::factory()->create(['name' => 'Original']);

        $response = $this->putJson("/api/v1/products/{$product->id}", [
            'name' => 'Updated Product',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Product');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated Product',
        ]);
    }

    public function test_destroy_deletes_product(): void
    {
        $product = Product::factory()->create();

        $response = $this->deleteJson("/api/v1/products/{$product->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_unauthenticated_access_returns_401(): void
    {
        app('auth')->forgetGuards();

        $response = $this->getJson('/api/v1/products');

        $response->assertUnauthorized();
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/products', []);

        $response->assertUnprocessable();
    }
}

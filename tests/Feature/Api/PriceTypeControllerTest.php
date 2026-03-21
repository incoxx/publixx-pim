<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\PriceType;
use App\Models\ProductPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PriceTypeControllerTest extends TestCase
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

    public function test_index_returns_price_types(): void
    {
        PriceType::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/price-types');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_store_creates_price_type(): void
    {
        $response = $this->postJson('/api/v1/price-types', [
            'technical_name' => 'retail',
            'name_de' => 'Einzelhandelspreis',
            'name_en' => 'Retail Price',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.technical_name', 'retail');

        $this->assertDatabaseHas('price_types', ['technical_name' => 'retail']);
    }

    public function test_show_returns_price_type(): void
    {
        $type = PriceType::factory()->create();

        $response = $this->getJson("/api/v1/price-types/{$type->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $type->id);
    }

    public function test_update_modifies_price_type(): void
    {
        $type = PriceType::factory()->create(['name_de' => 'Alt']);

        $response = $this->putJson("/api/v1/price-types/{$type->id}", [
            'name_de' => 'Neu',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name_de', 'Neu');
    }

    public function test_destroy_deletes_price_type(): void
    {
        $type = PriceType::factory()->create();

        $response = $this->deleteJson("/api/v1/price-types/{$type->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('price_types', ['id' => $type->id]);
    }

    public function test_destroy_with_dependencies_returns_409(): void
    {
        $type = PriceType::factory()->create();
        ProductPrice::factory()->create(['price_type_id' => $type->id]);

        $response = $this->deleteJson("/api/v1/price-types/{$type->id}");

        $response->assertStatus(409)
            ->assertJsonPath('type', 'deletion_constraint');

        $this->assertDatabaseHas('price_types', ['id' => $type->id]);
    }

    public function test_destroy_with_force_deletes_despite_dependencies(): void
    {
        $type = PriceType::factory()->create();
        ProductPrice::factory()->create(['price_type_id' => $type->id]);

        $response = $this->deleteJson("/api/v1/price-types/{$type->id}?force=true");

        $response->assertNoContent();
        $this->assertDatabaseMissing('price_types', ['id' => $type->id]);
    }

    public function test_dependencies_returns_constraint_info(): void
    {
        $type = PriceType::factory()->create();

        $response = $this->getJson("/api/v1/price-types/{$type->id}/dependencies");

        $response->assertOk()
            ->assertJsonStructure(['data' => ['has_dependencies', 'total_count', 'dependencies']]);
    }
}

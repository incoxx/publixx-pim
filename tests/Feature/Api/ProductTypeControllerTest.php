<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\ProductType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTypeControllerTest extends TestCase
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

    /** Minimalset gültiger Pflichtfelder für StoreProductTypeRequest */
    private function validPayload(array $override = []): array
    {
        return array_merge([
            'technical_name'         => 'standard-produkt',
            'name_de'                => 'Standardprodukt',
            'has_variants'           => false,
            'has_ean'                => true,
            'has_prices'             => true,
            'has_media'              => true,
            'has_stock'              => false,
            'has_physical_dimensions' => false,
        ], $override);
    }

    public function test_index_gibt_paginierte_produkttypen_zurueck(): void
    {
        ProductType::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/product-types');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_index_filtert_nach_is_active(): void
    {
        ProductType::factory()->create(['is_active' => true]);
        ProductType::factory()->create(['is_active' => false]);

        $response = $this->getJson('/api/v1/product-types?filter[is_active]=1');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_store_legt_produkttyp_an(): void
    {
        $response = $this->postJson('/api/v1/product-types', $this->validPayload());

        $response->assertCreated()
            ->assertJsonPath('data.technical_name', 'standard-produkt');

        $this->assertDatabaseHas('product_types', ['technical_name' => 'standard-produkt']);
    }

    public function test_store_validierung_schlaegt_bei_fehlendem_name_de_fehl(): void
    {
        $payload = $this->validPayload();
        unset($payload['name_de']);

        $response = $this->postJson('/api/v1/product-types', $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name_de']);
    }

    public function test_store_validierung_verhindert_doppelten_technical_name(): void
    {
        ProductType::factory()->create(['technical_name' => 'duplikat-typ']);

        $response = $this->postJson('/api/v1/product-types', $this->validPayload([
            'technical_name' => 'duplikat-typ',
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['technical_name']);
    }

    public function test_show_gibt_produkttyp_zurueck(): void
    {
        $type = ProductType::factory()->create();

        $response = $this->getJson("/api/v1/product-types/{$type->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $type->id);
    }

    public function test_update_aendert_produkttyp(): void
    {
        $type = ProductType::factory()->create(['name_de' => 'Alt']);

        $response = $this->patchJson("/api/v1/product-types/{$type->id}", [
            'name_de' => 'Neu',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name_de', 'Neu');

        $this->assertDatabaseHas('product_types', ['id' => $type->id, 'name_de' => 'Neu']);
    }

    public function test_update_setzt_allows_free_attributes(): void
    {
        $type = ProductType::factory()->create(['allows_free_attributes' => false]);

        $response = $this->patchJson("/api/v1/product-types/{$type->id}", [
            'allows_free_attributes' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.allows_free_attributes', true);

        $this->assertDatabaseHas('product_types', ['id' => $type->id, 'allows_free_attributes' => true]);
    }

    public function test_destroy_loescht_produkttyp_ohne_abhaengigkeiten(): void
    {
        $type = ProductType::factory()->create();

        $response = $this->deleteJson("/api/v1/product-types/{$type->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('product_types', ['id' => $type->id]);
    }

    public function test_dependencies_meldet_keine_abhaengigkeiten_wenn_typ_frei(): void
    {
        $type = ProductType::factory()->create();

        $response = $this->getJson("/api/v1/product-types/{$type->id}/dependencies");

        $response->assertOk()
            ->assertJsonStructure(['data' => ['has_dependencies', 'total_count', 'dependencies']])
            ->assertJsonPath('data.has_dependencies', false);
    }

    public function test_dependencies_meldet_abhaengigkeiten_wenn_produkt_zugewiesen(): void
    {
        $type = ProductType::factory()->create();
        Product::factory()->create(['product_type_id' => $type->id]);

        $response = $this->getJson("/api/v1/product-types/{$type->id}/dependencies");

        $response->assertOk()
            ->assertJsonPath('data.has_dependencies', true);
    }

    public function test_schema_endpoint_gibt_typ_konfiguration_zurueck(): void
    {
        $type = ProductType::factory()->create([
            'has_variants'           => true,
            'has_ean'                => true,
            'has_prices'             => false,
            'has_media'              => true,
            'has_stock'              => false,
            'has_physical_dimensions' => false,
        ]);

        $response = $this->getJson("/api/v1/product-types/{$type->id}/schema");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'technical_name',
                    'has_variants',
                    'has_ean',
                    'has_prices',
                    'has_media',
                    'has_stock',
                    'has_physical_dimensions',
                ],
            ])
            ->assertJsonPath('data.id', $type->id)
            ->assertJsonPath('data.has_variants', true)
            ->assertJsonPath('data.has_prices', false);
    }

    public function test_unauthentifizierter_request_wird_abgelehnt(): void
    {
        app('auth')->forgetGuards();

        $response = $this->getJson('/api/v1/product-types');

        $response->assertStatus(401);
    }
}

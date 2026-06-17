<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Attribute;
use App\Models\Media;
use App\Models\MediaUsageType;
use App\Models\Product;
use App\Models\ProductMediaAssignment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests für MediaUsageTypeController.
 *
 * Routen:
 *   GET/POST            /api/v1/media-usage-types
 *   GET/PUT/DELETE      /api/v1/media-usage-types/{media_usage_type}
 *   GET                 /api/v1/media-usage-types/{media_usage_type}/dependencies
 *   PUT                 /api/v1/media-usage-types/{media_usage_type}/default-attributes
 */
class MediaUsageTypeControllerTest extends TestCase
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

    public function test_index_gibt_paginierte_media_usage_types_zurueck(): void
    {
        // Die Migration legt bereits 4 Standard-Typen an; wir prüfen daher nur,
        // dass die Antwort okay ist und die Pagination-Struktur stimmt.
        $response = $this->getJson('/api/v1/media-usage-types');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta', 'links']);
    }

    public function test_store_erstellt_media_usage_type(): void
    {
        $response = $this->postJson('/api/v1/media-usage-types', [
            'technical_name' => 'teaser-bild',
            'name_de'        => 'Teaserbild',
            'name_en'        => 'Teaser Image',
            'sort_order'     => 10,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.technical_name', 'teaser-bild');

        $this->assertDatabaseHas('media_usage_types', ['technical_name' => 'teaser-bild']);
    }

    public function test_store_validiert_duplikaten_technical_name(): void
    {
        MediaUsageType::factory()->create(['technical_name' => 'teaser-bild']);

        $response = $this->postJson('/api/v1/media-usage-types', [
            'technical_name' => 'teaser-bild',
            'name_de'        => 'Anderer',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['technical_name']);
    }

    public function test_store_validiert_pflichtfelder(): void
    {
        $response = $this->postJson('/api/v1/media-usage-types', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['technical_name', 'name_de']);
    }

    public function test_show_gibt_media_usage_type_zurueck(): void
    {
        $type = MediaUsageType::factory()->create();

        $response = $this->getJson("/api/v1/media-usage-types/{$type->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $type->id);
    }

    public function test_update_aendert_media_usage_type(): void
    {
        $type = MediaUsageType::factory()->create(['name_de' => 'Alt']);

        $response = $this->putJson("/api/v1/media-usage-types/{$type->id}", [
            'name_de' => 'Neu',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name_de', 'Neu');
    }

    public function test_destroy_loescht_media_usage_type(): void
    {
        $type = MediaUsageType::factory()->create();

        $response = $this->deleteJson("/api/v1/media-usage-types/{$type->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('media_usage_types', ['id' => $type->id]);
    }

    public function test_destroy_mit_dependencies_gibt_409(): void
    {
        $type    = MediaUsageType::factory()->create();
        $product = Product::factory()->create();
        $media   = Media::factory()->create();

        ProductMediaAssignment::factory()->create([
            'usage_type_id' => $type->id,
            'product_id'    => $product->id,
            'media_id'      => $media->id,
        ]);

        $response = $this->deleteJson("/api/v1/media-usage-types/{$type->id}");

        $response->assertStatus(409)
            ->assertJsonPath('type', 'deletion_constraint');

        $this->assertDatabaseHas('media_usage_types', ['id' => $type->id]);
    }

    public function test_destroy_force_loescht_trotz_dependencies(): void
    {
        $type    = MediaUsageType::factory()->create();
        $product = Product::factory()->create();
        $media   = Media::factory()->create();

        ProductMediaAssignment::factory()->create([
            'usage_type_id' => $type->id,
            'product_id'    => $product->id,
            'media_id'      => $media->id,
        ]);

        $response = $this->deleteJson("/api/v1/media-usage-types/{$type->id}?force=true");

        $response->assertNoContent();
        $this->assertDatabaseMissing('media_usage_types', ['id' => $type->id]);
    }

    public function test_dependencies_gibt_constraint_info_zurueck(): void
    {
        $type = MediaUsageType::factory()->create();

        $response = $this->getJson("/api/v1/media-usage-types/{$type->id}/dependencies");

        $response->assertOk()
            ->assertJsonStructure(['data' => ['has_dependencies', 'total_count', 'dependencies']]);
    }

    public function test_update_default_attributes_synchronisiert_attribute(): void
    {
        $type      = MediaUsageType::factory()->create();
        $attribute = Attribute::factory()->create();

        $response = $this->putJson("/api/v1/media-usage-types/{$type->id}/default-attributes", [
            'attributes' => [
                ['attribute_id' => $attribute->id, 'sort_order' => 1],
            ],
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('usage_type_default_attributes', [
            'usage_type_id' => $type->id,
            'attribute_id'  => $attribute->id,
        ]);
    }

    public function test_update_default_attributes_leert_liste_bei_leerem_array(): void
    {
        $type      = MediaUsageType::factory()->create();
        $attribute = Attribute::factory()->create();
        $type->defaultAttributes()->attach($attribute->id, ['sort_order' => 0]);

        $response = $this->putJson("/api/v1/media-usage-types/{$type->id}/default-attributes", [
            'attributes' => [],
        ]);

        $response->assertOk();

        $this->assertDatabaseMissing('usage_type_default_attributes', [
            'usage_type_id' => $type->id,
        ]);
    }

    public function test_update_default_attributes_validiert_ungueltige_attribute_id(): void
    {
        $type = MediaUsageType::factory()->create();

        $response = $this->putJson("/api/v1/media-usage-types/{$type->id}/default-attributes", [
            'attributes' => [
                ['attribute_id' => 'kein-gueltiger-uuid', 'sort_order' => 0],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['attributes.0.attribute_id']);
    }
}

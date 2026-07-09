<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Attribute;
use App\Models\AttributeType;
use App\Models\AttributeViewAssignment;
use App\Models\HierarchyNode;
use App\Models\HierarchyNodeAttributeAssignment;
use App\Models\ImportProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Models\AttributeView;
use App\Models\Role;
use Tests\TestCase;

class ImportProfileControllerTest extends TestCase
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

    public function test_index_returns_import_profiles(): void
    {
        $response = $this->getJson('/api/v1/import-profiles');

        $response->assertOk();
    }

    public function test_store_creates_import_profile(): void
    {
        $attr = Attribute::factory()->create();

        $response = $this->postJson('/api/v1/import-profiles', [
            'name' => 'CSV Import',
            'is_shared' => false,
            'sku_column' => 'SKU',
            'column_mappings' => [
                ['source' => 'Name', 'target_attribute_id' => $attr->id, 'language' => 'de'],
            ],
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

    public function test_auto_generate_attributes_requires_hierarchy_node(): void
    {
        $response = $this->postJson('/api/v1/import-profiles/auto-generate-attributes', [
            'columns' => [
                ['header' => 'Lieferzeit', 'auto_generate' => true, 'detected_type' => 'String'],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['hierarchy_node_id']);
    }

    public function test_auto_generate_attributes_derives_kebab_case_technical_name(): void
    {
        $node = HierarchyNode::factory()->create();

        $response = $this->postJson('/api/v1/import-profiles/auto-generate-attributes', [
            'hierarchy_node_id' => $node->id,
            'columns' => [
                ['header' => '3. Lieferzeit in Kalendertage', 'auto_generate' => true, 'detected_type' => 'Number'],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.created.0.technical_name', '3-lieferzeit-in-kalendertage')
            ->assertJsonPath('data.assigned_to_category', 1)
            ->assertJsonPath('data.assigned_to_view', 0);

        $attribute = Attribute::where('technical_name', '3-lieferzeit-in-kalendertage')->firstOrFail();

        $this->assertDatabaseHas('hierarchy_node_attribute_assignments', [
            'hierarchy_node_id' => $node->id,
            'attribute_id' => $attribute->id,
        ]);
    }

    public function test_auto_generate_attributes_works_without_attribute_view(): void
    {
        $node = HierarchyNode::factory()->create();

        $response = $this->postJson('/api/v1/import-profiles/auto-generate-attributes', [
            'hierarchy_node_id' => $node->id,
            'columns' => [
                ['header' => 'Farbe (RAL)', 'auto_generate' => true, 'detected_type' => 'String'],
            ],
        ]);

        $response->assertOk();

        $attribute = Attribute::where('technical_name', 'farbe-ral')->firstOrFail();

        $this->assertDatabaseCount('attribute_view_assignments', 0);
        $this->assertDatabaseHas('hierarchy_node_attribute_assignments', [
            'hierarchy_node_id' => $node->id,
            'attribute_id' => $attribute->id,
            'collection_name' => 'Import',
        ]);
    }

    public function test_auto_generate_attributes_assigns_view_and_group_when_given(): void
    {
        $node = HierarchyNode::factory()->create();
        $view = AttributeView::factory()->create();
        $type = AttributeType::factory()->create();

        $response = $this->postJson('/api/v1/import-profiles/auto-generate-attributes', [
            'hierarchy_node_id' => $node->id,
            'attribute_view_id' => $view->id,
            'attribute_type_id' => $type->id,
            'columns' => [
                ['header' => 'Gewicht', 'auto_generate' => true, 'detected_type' => 'Float'],
            ],
        ]);

        $response->assertOk()->assertJsonPath('data.assigned_to_view', 1);

        $attribute = Attribute::where('technical_name', 'gewicht')->firstOrFail();

        $this->assertSame($type->id, $attribute->attribute_type_id);
        $this->assertDatabaseHas('attribute_view_assignments', [
            'attribute_id' => $attribute->id,
            'attribute_view_id' => $view->id,
        ]);
        $this->assertDatabaseHas('hierarchy_node_attribute_assignments', [
            'hierarchy_node_id' => $node->id,
            'attribute_id' => $attribute->id,
            'collection_name' => $view->name_de,
        ]);
    }

    public function test_auto_generate_attributes_reuses_existing_attribute_by_technical_name(): void
    {
        $node = HierarchyNode::factory()->create();
        $existing = Attribute::factory()->create(['technical_name' => 'lieferzeit']);

        $response = $this->postJson('/api/v1/import-profiles/auto-generate-attributes', [
            'hierarchy_node_id' => $node->id,
            'columns' => [
                ['header' => 'Lieferzeit', 'auto_generate' => true, 'detected_type' => 'Number'],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.created', [])
            ->assertJsonPath('data.existing.0.id', $existing->id);

        $this->assertSame(1, Attribute::where('technical_name', 'lieferzeit')->count());
    }
}

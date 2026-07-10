<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Attribute;
use App\Models\HierarchyNode;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\Role;
use App\Models\User;
use App\Services\Attributes\AttributeValuePresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Deckt die vier neuen Attribut-Datentypen ab:
 * HierarchyNodeReference, ProductReference, SimpleSelect, SimpleMultiSelect.
 */
class NewAttributeTypesTest extends TestCase
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

    /**
     * @return array<string, array{0: string}>
     */
    public static function newTypeProvider(): array
    {
        return [
            'HierarchyNodeReference' => ['HierarchyNodeReference'],
            'ProductReference' => ['ProductReference'],
            'SimpleSelect' => ['SimpleSelect'],
            'SimpleMultiSelect' => ['SimpleMultiSelect'],
        ];
    }

    /**
     * @dataProvider newTypeProvider
     */
    public function test_can_create_attribute_of_new_type(string $dataType): void
    {
        $response = $this->postJson('/api/v1/attributes', [
            'technical_name' => 'ref_' . strtolower($dataType),
            'name_de' => 'Referenz ' . $dataType,
            'data_type' => $dataType,
            'is_translatable' => false,
            'is_multipliable' => false,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.data_type', $dataType);

        $this->assertDatabaseHas('attributes', ['data_type' => $dataType]);
    }

    public function test_simple_select_persists_options(): void
    {
        $response = $this->postJson('/api/v1/attributes', [
            'technical_name' => 'prio',
            'name_de' => 'Priorität',
            'data_type' => 'SimpleSelect',
            'simple_options' => ['1', '2', '3'],
            'is_translatable' => false,
            'is_multipliable' => false,
        ]);

        $response->assertCreated();

        $attr = Attribute::where('technical_name', 'prio')->firstOrFail();
        $this->assertSame(['1', '2', '3'], $attr->simple_options);
    }

    public function test_product_reference_value_stored_as_uuid_in_value_string(): void
    {
        $target = Product::factory()->create();
        $attr = Attribute::factory()->create(['data_type' => 'ProductReference', 'technical_name' => 'related_product']);
        $product = Product::factory()->create();

        $this->putJson("/api/v1/products/{$product->id}/attribute-values", [
            'values' => [
                ['attribute_id' => $attr->id, 'value' => $target->id],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('product_attribute_values', [
            'product_id' => $product->id,
            'attribute_id' => $attr->id,
            'value_string' => $target->id,
        ]);
    }

    public function test_simple_multi_select_value_stored_as_json_array(): void
    {
        $attr = Attribute::factory()->create(['data_type' => 'SimpleMultiSelect', 'technical_name' => 'tags_simple']);
        $product = Product::factory()->create();

        $this->putJson("/api/v1/products/{$product->id}/attribute-values", [
            'values' => [
                ['attribute_id' => $attr->id, 'value' => ['1', '3']],
            ],
        ])->assertOk();

        $pav = ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', $attr->id)
            ->firstOrFail();

        $this->assertSame(['1', '3'], json_decode((string) $pav->value_string, true));
    }

    public function test_presenter_resolves_product_reference(): void
    {
        $target = Product::factory()->create(['sku' => 'SKU-123', 'name' => 'Zielprodukt']);
        $attr = Attribute::factory()->create(['data_type' => 'ProductReference']);
        $pav = new ProductAttributeValue(['value_string' => $target->id]);
        $pav->setRelation('attribute', $attr);

        $presenter = new AttributeValuePresenter();

        $this->assertSame('Zielprodukt', $presenter->displayValue($pav));

        $ref = $presenter->referenceData($pav);
        $this->assertTrue($ref['exists']);
        $this->assertSame('SKU-123', $ref['sku']);
        $this->assertSame('product', $ref['type']);
    }

    public function test_presenter_resolves_hierarchy_node_reference(): void
    {
        $node = HierarchyNode::factory()->create(['name_de' => 'Knoten A', 'path' => '/a/']);
        $attr = Attribute::factory()->create(['data_type' => 'HierarchyNodeReference']);
        $pav = new ProductAttributeValue(['value_string' => $node->id]);
        $pav->setRelation('attribute', $attr);

        $presenter = new AttributeValuePresenter();

        $this->assertSame('Knoten A', $presenter->displayValue($pav));
        $ref = $presenter->referenceData($pav);
        $this->assertTrue($ref['exists']);
        $this->assertSame('hierarchy_node', $ref['type']);
    }

    public function test_presenter_marks_broken_reference(): void
    {
        $attr = Attribute::factory()->create(['data_type' => 'ProductReference']);
        // Nicht existierende UUID
        $pav = new ProductAttributeValue(['value_string' => '00000000-0000-0000-0000-000000000000']);
        $pav->setRelation('attribute', $attr);

        $presenter = new AttributeValuePresenter();
        $ref = $presenter->referenceData($pav);

        $this->assertFalse($ref['exists']);
        $this->assertNull($presenter->displayValue($pav));
    }
}

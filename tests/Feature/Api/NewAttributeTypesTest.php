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
use App\Services\Export\MappingResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Deckt die vier neuen Attribut-Datentypen ab (elementare PIM-Funktion):
 * HierarchyNodeReference, ProductReference, SimpleSelect, SimpleMultiSelect.
 *
 * Getestet wird pro Typ: Anlegen (Validierung), Wert-Speicherung (Storage),
 * Anzeige/Export via AttributeValuePresenter, Export via MappingResolver
 * sowie Edge-Cases (leer, gebrochene Referenz, Nicht-JSON, Sprache).
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

    private function makeValue(string $dataType, ?string $valueString): ProductAttributeValue
    {
        $attr = Attribute::factory()->create(['data_type' => $dataType]);
        $pav = new ProductAttributeValue(['value_string' => $valueString]);
        $pav->setRelation('attribute', $attr);

        return $pav;
    }

    // ─────────────────────────────────────────────────────────────
    //  Anlegen & Validierung
    // ─────────────────────────────────────────────────────────────

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

    public function test_store_rejects_unknown_data_type(): void
    {
        $this->postJson('/api/v1/attributes', [
            'technical_name' => 'bogus',
            'name_de' => 'Bogus',
            'data_type' => 'NotARealType',
            'is_translatable' => false,
            'is_multipliable' => false,
        ])->assertUnprocessable()
          ->assertJsonValidationErrors(['data_type']);
    }

    public function test_update_can_switch_to_new_type(): void
    {
        $attr = Attribute::factory()->create(['data_type' => 'String']);

        $this->putJson("/api/v1/attributes/{$attr->id}", ['data_type' => 'ProductReference'])
            ->assertOk()
            ->assertJsonPath('data.data_type', 'ProductReference');
    }

    // ─────────────────────────────────────────────────────────────
    //  SimpleSelect / SimpleMultiSelect — Optionen & Storage
    // ─────────────────────────────────────────────────────────────

    public function test_simple_select_persists_options(): void
    {
        $this->postJson('/api/v1/attributes', [
            'technical_name' => 'prio',
            'name_de' => 'Priorität',
            'data_type' => 'SimpleSelect',
            'simple_options' => ['1', '2', '3'],
            'is_translatable' => false,
            'is_multipliable' => false,
        ])->assertCreated();

        $attr = Attribute::where('technical_name', 'prio')->firstOrFail();
        $this->assertSame(['1', '2', '3'], $attr->simple_options);
    }

    public function test_simple_options_round_trip_through_api(): void
    {
        // Regression: simple_options muss von AttributeResource zurückgegeben werden,
        // sonst wirken die Optionen im Config-Panel nach dem Speichern "verloren".
        $id = $this->postJson('/api/v1/attributes', [
            'technical_name' => 'farbe',
            'name_de' => 'Farbe',
            'data_type' => 'SimpleSelect',
            'simple_options' => ['Rot::#FF0000', 'Grün::#00FF00'],
            'is_translatable' => false,
            'is_multipliable' => false,
        ])->assertCreated()->json('data.id');

        $this->getJson("/api/v1/attributes/{$id}")
            ->assertOk()
            ->assertJsonPath('data.simple_options', ['Rot::#FF0000', 'Grün::#00FF00']);
    }

    public function test_simple_select_rejects_non_string_options(): void
    {
        $this->postJson('/api/v1/attributes', [
            'technical_name' => 'prio2',
            'name_de' => 'Priorität',
            'data_type' => 'SimpleSelect',
            'simple_options' => [['nested' => 'array']],
            'is_translatable' => false,
            'is_multipliable' => false,
        ])->assertUnprocessable()
          ->assertJsonValidationErrors(['simple_options.0']);
    }

    public function test_simple_select_value_stored_in_value_string(): void
    {
        $attr = Attribute::factory()->create(['data_type' => 'SimpleSelect', 'technical_name' => 'prio3']);
        $product = Product::factory()->create();

        $this->putJson("/api/v1/products/{$product->id}/attribute-values", [
            'values' => [['attribute_id' => $attr->id, 'value' => '2']],
        ])->assertOk();

        $this->assertDatabaseHas('product_attribute_values', [
            'product_id' => $product->id,
            'attribute_id' => $attr->id,
            'value_string' => '2',
        ]);
    }

    public function test_simple_multi_select_value_stored_as_json_array(): void
    {
        $attr = Attribute::factory()->create(['data_type' => 'SimpleMultiSelect', 'technical_name' => 'tags_simple']);
        $product = Product::factory()->create();

        $this->putJson("/api/v1/products/{$product->id}/attribute-values", [
            'values' => [['attribute_id' => $attr->id, 'value' => ['1', '3']]],
        ])->assertOk();

        $pav = ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', $attr->id)
            ->firstOrFail();

        $this->assertSame(['1', '3'], json_decode((string) $pav->value_string, true));
    }

    // ─────────────────────────────────────────────────────────────
    //  Referenz-Storage
    // ─────────────────────────────────────────────────────────────

    public function test_product_reference_value_stored_as_uuid(): void
    {
        $target = Product::factory()->create();
        $attr = Attribute::factory()->create(['data_type' => 'ProductReference', 'technical_name' => 'related_product']);
        $product = Product::factory()->create();

        $this->putJson("/api/v1/products/{$product->id}/attribute-values", [
            'values' => [['attribute_id' => $attr->id, 'value' => $target->id]],
        ])->assertOk();

        $this->assertDatabaseHas('product_attribute_values', [
            'product_id' => $product->id,
            'attribute_id' => $attr->id,
            'value_string' => $target->id,
        ]);
    }

    public function test_hierarchy_node_reference_value_stored_as_uuid(): void
    {
        $node = HierarchyNode::factory()->create();
        $attr = Attribute::factory()->create(['data_type' => 'HierarchyNodeReference', 'technical_name' => 'category_ref']);
        $product = Product::factory()->create();

        $this->putJson("/api/v1/products/{$product->id}/attribute-values", [
            'values' => [['attribute_id' => $attr->id, 'value' => $node->id]],
        ])->assertOk();

        $this->assertDatabaseHas('product_attribute_values', [
            'product_id' => $product->id,
            'attribute_id' => $attr->id,
            'value_string' => $node->id,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    //  storageColumn — zentrale Storage-Klassifikation
    // ─────────────────────────────────────────────────────────────

    public function test_storage_column_maps_all_new_types_to_value_string(): void
    {
        $this->assertSame('value_string', Attribute::storageColumn('HierarchyNodeReference'));
        $this->assertSame('value_string', Attribute::storageColumn('ProductReference'));
        $this->assertSame('value_string', Attribute::storageColumn('SimpleSelect'));
        $this->assertSame('value_string', Attribute::storageColumn('SimpleMultiSelect'));
        // Gegenprobe: typisierte Spalten
        $this->assertSame('value_number', Attribute::storageColumn('Number'));
        $this->assertSame('value_date', Attribute::storageColumn('Date'));
        $this->assertSame('value_flag', Attribute::storageColumn('Flag'));
    }

    // ─────────────────────────────────────────────────────────────
    //  AttributeValuePresenter — Anzeige & Struktur
    // ─────────────────────────────────────────────────────────────

    public function test_presenter_handles_only_reference_and_multi_types(): void
    {
        $presenter = new AttributeValuePresenter();
        $this->assertTrue($presenter->handles('ProductReference'));
        $this->assertTrue($presenter->handles('HierarchyNodeReference'));
        $this->assertTrue($presenter->handles('SimpleMultiSelect'));
        // SimpleSelect ist reiner value_string → nicht vom Presenter behandelt
        $this->assertFalse($presenter->handles('SimpleSelect'));
        $this->assertFalse($presenter->handles('String'));
        $this->assertFalse($presenter->handles(null));
    }

    public function test_presenter_resolves_product_reference(): void
    {
        $target = Product::factory()->create(['sku' => 'SKU-123', 'name' => 'Zielprodukt']);
        $pav = $this->makeValue('ProductReference', $target->id);
        $presenter = new AttributeValuePresenter();

        $this->assertSame('Zielprodukt', $presenter->displayValue($pav));

        $ref = $presenter->referenceData($pav);
        $this->assertTrue($ref['exists']);
        $this->assertSame('SKU-123', $ref['sku']);
        $this->assertSame('product', $ref['type']);
        $this->assertSame($target->id, $ref['id']);
    }

    public function test_presenter_product_reference_falls_back_to_sku_when_name_missing(): void
    {
        $target = Product::factory()->create(['sku' => 'ONLY-SKU', 'name' => null]);
        $pav = $this->makeValue('ProductReference', $target->id);

        $this->assertSame('ONLY-SKU', (new AttributeValuePresenter())->displayValue($pav));
    }

    public function test_presenter_resolves_hierarchy_node_reference_with_language(): void
    {
        $node = HierarchyNode::factory()->create([
            'name_de' => 'Knoten A',
            'name_en' => 'Node A',
            'path' => '/a/',
        ]);
        $pav = $this->makeValue('HierarchyNodeReference', $node->id);
        $presenter = new AttributeValuePresenter();

        $this->assertSame('Knoten A', $presenter->displayValue($pav, 'de'));
        $this->assertSame('Node A', $presenter->displayValue($pav, 'en'));

        $ref = $presenter->referenceData($pav, 'de');
        $this->assertTrue($ref['exists']);
        $this->assertSame('hierarchy_node', $ref['type']);
        $this->assertSame('/a/', $ref['path']);
    }

    public function test_presenter_marks_broken_reference(): void
    {
        $pav = $this->makeValue('ProductReference', '00000000-0000-0000-0000-000000000000');
        $presenter = new AttributeValuePresenter();

        $ref = $presenter->referenceData($pav);
        $this->assertFalse($ref['exists']);
        $this->assertNull($presenter->displayValue($pav));
    }

    public function test_presenter_handles_empty_reference_value(): void
    {
        $pav = $this->makeValue('ProductReference', null);
        $presenter = new AttributeValuePresenter();

        $this->assertNull($presenter->referenceData($pav));
        $this->assertNull($presenter->displayValue($pav));
    }

    public function test_presenter_renders_simple_multi_select(): void
    {
        $pav = $this->makeValue('SimpleMultiSelect', json_encode(['1', '3']));
        $presenter = new AttributeValuePresenter();

        $this->assertSame('1, 3', $presenter->displayValue($pav));
        $this->assertSame(['1', '3'], $presenter->simpleMultiValues($pav));
        $this->assertSame(['1', '3'], $presenter->exportValue($pav));
    }

    public function test_simple_multi_values_tolerates_non_json_string(): void
    {
        // Robustheit: einzelner Rohwert ohne JSON-Kodierung
        $pav = $this->makeValue('SimpleMultiSelect', 'einzelwert');
        $this->assertSame(['einzelwert'], (new AttributeValuePresenter())->simpleMultiValues($pav));
    }

    public function test_simple_multi_values_empty_is_empty_array(): void
    {
        $presenter = new AttributeValuePresenter();
        $this->assertSame([], $presenter->simpleMultiValues($this->makeValue('SimpleMultiSelect', null)));
        $this->assertSame([], $presenter->simpleMultiValues($this->makeValue('SimpleMultiSelect', '')));
    }

    public function test_presenter_export_value_returns_structured_reference(): void
    {
        $target = Product::factory()->create(['sku' => 'EXP-1', 'name' => 'Export']);
        $pav = $this->makeValue('ProductReference', $target->id);

        $export = (new AttributeValuePresenter())->exportValue($pav);
        $this->assertIsArray($export);
        $this->assertSame('EXP-1', $export['sku']);
        $this->assertSame($target->id, $export['id']);
    }

    public function test_presenter_caches_repeated_reference_lookups(): void
    {
        $target = Product::factory()->create(['name' => 'Cached']);
        $attr = Attribute::factory()->create(['data_type' => 'ProductReference']);
        $presenter = new AttributeValuePresenter();

        $pav1 = new ProductAttributeValue(['value_string' => $target->id]);
        $pav1->setRelation('attribute', $attr);
        $this->assertSame('Cached', $presenter->displayValue($pav1));

        // Zielprodukt löschen; dieselbe Instanz muss aus dem Cache liefern
        $target->delete();
        $pav2 = new ProductAttributeValue(['value_string' => $target->id]);
        $pav2->setRelation('attribute', $attr);
        $this->assertSame('Cached', $presenter->displayValue($pav2));

        // Frische Instanz umgeht den Cache → gebrochene Referenz
        $this->assertNull((new AttributeValuePresenter())->displayValue($pav2));
    }

    // ─────────────────────────────────────────────────────────────
    //  Label::Wert-Syntax — Preview zeigt Label, Export den Rohwert
    // ─────────────────────────────────────────────────────────────

    private function makeSimpleValue(string $type, ?string $vs, array $options): ProductAttributeValue
    {
        $attr = new Attribute(['data_type' => $type, 'simple_options' => $options]);
        $pav = new ProductAttributeValue(['value_string' => $vs]);
        $pav->setRelation('attribute', $attr);

        return $pav;
    }

    public function test_handles_for_display_adds_simple_select(): void
    {
        $presenter = new AttributeValuePresenter();
        // Export-Guard: SimpleSelect NICHT enthalten (Rohwert im Export)
        $this->assertFalse($presenter->handles('SimpleSelect'));
        // Anzeige-Guard: SimpleSelect enthalten (Label in der Preview)
        $this->assertTrue($presenter->handlesForDisplay('SimpleSelect'));
        $this->assertTrue($presenter->handlesForDisplay('SimpleMultiSelect'));
        $this->assertTrue($presenter->handlesForDisplay('ProductReference'));
        $this->assertFalse($presenter->handlesForDisplay('String'));
    }

    public function test_simple_select_label_in_preview_value_in_export(): void
    {
        $pav = $this->makeSimpleValue('SimpleSelect', '#FF0000', ['Rot::#FF0000', 'Grün::#00FF00']);
        $presenter = new AttributeValuePresenter();

        // Preview: Label
        $this->assertSame('Rot (#FF0000)', $presenter->displayValue($pav, 'de', withLabels: true));
        // Export/flach: Rohwert
        $this->assertSame('#FF0000', $presenter->displayValue($pav));
        $this->assertSame('#FF0000', $presenter->exportValue($pav));
    }

    public function test_simple_multi_select_labels_in_preview(): void
    {
        $pav = $this->makeSimpleValue('SimpleMultiSelect', json_encode(['#FF0000', '#00FF00']), ['Rot::#FF0000', 'Grün::#00FF00']);
        $presenter = new AttributeValuePresenter();

        $this->assertSame('Rot (#FF0000), Grün (#00FF00)', $presenter->displayValue($pav, 'de', withLabels: true));
        // Ohne Labels (Export flach): Rohwerte
        $this->assertSame('#FF0000, #00FF00', $presenter->displayValue($pav));
        // Strukturierter Export: Rohwert-Array
        $this->assertSame(['#FF0000', '#00FF00'], $presenter->exportValue($pav));
    }

    public function test_option_without_label_and_unmatched_value_stay_raw(): void
    {
        $presenter = new AttributeValuePresenter();
        // Option ohne "::" → Anzeige unverändert
        $plain = $this->makeSimpleValue('SimpleSelect', '2', ['1', '2', '3']);
        $this->assertSame('2', $presenter->displayValue($plain, 'de', withLabels: true));
        // Wert ohne passende Option → Rohwert
        $unmatched = $this->makeSimpleValue('SimpleSelect', '#ABCDEF', ['Rot::#FF0000']);
        $this->assertSame('#ABCDEF', $presenter->displayValue($unmatched, 'de', withLabels: true));
    }

    // ─────────────────────────────────────────────────────────────
    //  Export via MappingResolver (type: 'reference')
    // ─────────────────────────────────────────────────────────────

    public function test_mapping_resolver_reference_type_for_product(): void
    {
        $target = Product::factory()->create(['sku' => 'MAP-1', 'name' => 'Gemappt']);
        $attr = Attribute::factory()->create(['data_type' => 'ProductReference', 'technical_name' => 'related_product']);
        $product = Product::factory()->create();

        ProductAttributeValue::create([
            'product_id' => $product->id,
            'attribute_id' => $attr->id,
            'value_string' => $target->id,
            'language' => null,
        ]);
        $product->load('attributeValues.attribute');

        $resolver = new MappingResolver();
        $result = $resolver->resolve(
            [['source' => 'attribute:related_product', 'target' => 'ref', 'type' => 'reference']],
            $product,
            ['de'],
        );

        $this->assertIsArray($result['ref']);
        $this->assertSame('MAP-1', $result['ref']['sku']);
        $this->assertSame('product', $result['ref']['type']);
    }

    public function test_mapping_resolver_reference_type_for_simple_multi(): void
    {
        $attr = Attribute::factory()->create(['data_type' => 'SimpleMultiSelect', 'technical_name' => 'multi_tags']);
        $product = Product::factory()->create();

        ProductAttributeValue::create([
            'product_id' => $product->id,
            'attribute_id' => $attr->id,
            'value_string' => json_encode(['a', 'b']),
            'language' => null,
        ]);
        $product->load('attributeValues.attribute');

        $resolver = new MappingResolver();
        $result = $resolver->resolve(
            [['source' => 'attribute:multi_tags', 'target' => 'tags', 'type' => 'reference']],
            $product,
            ['de'],
        );

        $this->assertSame(['a', 'b'], $result['tags']);
    }
}

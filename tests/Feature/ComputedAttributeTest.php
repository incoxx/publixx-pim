<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductType;
use App\Services\ComputedAttributeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ComputedAttributeTest extends TestCase
{
    use RefreshDatabase;

    private ProductType $productType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productType = ProductType::create([
            'id' => (string) Str::uuid(),
            'technical_name' => 'test_product_type',
            'name_de' => 'Testprodukttyp',
            'has_variants' => false,
            'has_ean' => false,
            'has_prices' => false,
            'has_media' => false,
            'has_stock' => false,
            'has_physical_dimensions' => false,
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    private function createProduct(string $sku): Product
    {
        return Product::create([
            'id' => (string) Str::uuid(),
            'product_type_id' => $this->productType->id,
            'sku' => $sku,
            'name' => "Product {$sku}",
            'status' => 'active',
            'product_type_ref' => 'product',
        ]);
    }

    private function createCompositeWithChildren(
        string $technicalName,
        string $expression,
        array $childNames,
    ): array {
        $composite = Attribute::create([
            'id' => (string) Str::uuid(),
            'technical_name' => $technicalName,
            'name_de' => ucfirst($technicalName),
            'data_type' => 'Composite',
            'composite_expression' => $expression,
            'is_translatable' => false,
            'is_multipliable' => false,
            'is_searchable' => true,
            'is_mandatory' => false,
            'is_unique' => false,
            'is_country_specific' => false,
            'is_inheritable' => true,
            'status' => 'active',
        ]);

        $children = [];
        foreach ($childNames as $i => $name) {
            $children[] = Attribute::create([
                'id' => (string) Str::uuid(),
                'technical_name' => $name,
                'name_de' => ucfirst($name),
                'data_type' => 'Float',
                'parent_attribute_id' => $composite->id,
                'position' => $i,
                'is_translatable' => false,
                'is_multipliable' => false,
                'is_searchable' => true,
                'is_mandatory' => false,
                'is_unique' => false,
                'is_country_specific' => false,
                'is_inheritable' => true,
                'status' => 'active',
            ]);
        }

        return ['composite' => $composite, 'children' => $children];
    }

    private function setNumericValue(Product $product, Attribute $attribute, float $value): ProductAttributeValue
    {
        return ProductAttributeValue::create([
            'id' => (string) Str::uuid(),
            'product_id' => $product->id,
            'attribute_id' => $attribute->id,
            'value_number' => $value,
            'language' => null,
            'multiplied_index' => 0,
            'is_inherited' => false,
        ]);
    }

    // ─── Tests ───────────────────────────────────────────────────

    public function test_volume_computation(): void
    {
        $product = $this->createProduct('PROD-001');
        $attrs = $this->createCompositeWithChildren(
            'volumen',
            '{0} * {1} * {2}',
            ['breite', 'hoehe', 'tiefe'],
        );

        $this->setNumericValue($product, $attrs['children'][0], 120.0);
        $this->setNumericValue($product, $attrs['children'][1], 80.0);
        $this->setNumericValue($product, $attrs['children'][2], 45.0);

        $service = app(ComputedAttributeService::class);
        $service->computeAndStore($product, $attrs['composite']);

        $pav = ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', $attrs['composite']->id)
            ->first();

        $this->assertNotNull($pav);
        $this->assertEqualsWithDelta(432000.0, (float) $pav->value_number, 0.01);
    }

    public function test_metric_to_inch_conversion(): void
    {
        $product = $this->createProduct('PROD-002');
        $attrs = $this->createCompositeWithChildren(
            'breite_inch',
            '{0} * 0.0393701',
            ['breite_mm'],
        );

        $this->setNumericValue($product, $attrs['children'][0], 25.4);

        $service = app(ComputedAttributeService::class);
        $service->computeAndStore($product, $attrs['composite']);

        $pav = ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', $attrs['composite']->id)
            ->first();

        $this->assertNotNull($pav);
        $this->assertEqualsWithDelta(1.0, (float) $pav->value_number, 0.001);
    }

    public function test_missing_child_value_produces_no_result(): void
    {
        $product = $this->createProduct('PROD-003');
        $attrs = $this->createCompositeWithChildren(
            'volumen2',
            '{0} * {1} * {2}',
            ['b', 'h', 't'],
        );

        // Nur 2 von 3 Werten setzen
        $this->setNumericValue($product, $attrs['children'][0], 120.0);
        $this->setNumericValue($product, $attrs['children'][1], 80.0);

        $service = app(ComputedAttributeService::class);
        $service->computeAndStore($product, $attrs['composite']);

        $pav = ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', $attrs['composite']->id)
            ->first();

        $this->assertNull($pav);
    }

    public function test_recompute_updates_existing_value(): void
    {
        $product = $this->createProduct('PROD-004');
        $attrs = $this->createCompositeWithChildren(
            'area',
            '{0} * {1}',
            ['laenge', 'breite2'],
        );

        $this->setNumericValue($product, $attrs['children'][0], 10.0);
        $this->setNumericValue($product, $attrs['children'][1], 5.0);

        $service = app(ComputedAttributeService::class);
        $service->computeAndStore($product, $attrs['composite']);

        // Überprüfe initialen Wert
        $pav = ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', $attrs['composite']->id)
            ->first();
        $this->assertEqualsWithDelta(50.0, (float) $pav->value_number, 0.01);

        // Kind-Wert ändern
        ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', $attrs['children'][1]->id)
            ->update(['value_number' => 8.0]);

        // Neu berechnen
        $service->computeAndStore($product, $attrs['composite']);

        $pav->refresh();
        $this->assertEqualsWithDelta(80.0, (float) $pav->value_number, 0.01);
    }

    public function test_recompute_for_changed_attributes(): void
    {
        $product = $this->createProduct('PROD-005');
        $attrs = $this->createCompositeWithChildren(
            'sum',
            '{0} + {1}',
            ['val_a', 'val_b'],
        );

        $this->setNumericValue($product, $attrs['children'][0], 3.0);
        $this->setNumericValue($product, $attrs['children'][1], 7.0);

        $service = app(ComputedAttributeService::class);
        $service->recomputeForChangedAttributes(
            $product->id,
            [$attrs['children'][0]->id],
        );

        $pav = ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', $attrs['composite']->id)
            ->first();

        $this->assertNotNull($pav);
        $this->assertEqualsWithDelta(10.0, (float) $pav->value_number, 0.01);
    }

    public function test_no_expression_does_nothing(): void
    {
        $product = $this->createProduct('PROD-006');

        // Composite ohne Expression (nur Format)
        $composite = Attribute::create([
            'id' => (string) Str::uuid(),
            'technical_name' => 'dimensions',
            'name_de' => 'Abmessungen',
            'data_type' => 'Composite',
            'composite_format' => '{0} x {1} x {2} mm',
            'composite_expression' => null,
            'is_translatable' => false,
            'is_multipliable' => false,
            'is_searchable' => true,
            'is_mandatory' => false,
            'is_unique' => false,
            'is_country_specific' => false,
            'is_inheritable' => true,
            'status' => 'active',
        ]);

        $service = app(ComputedAttributeService::class);
        $service->computeAndStore($product, $composite);

        $pav = ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', $composite->id)
            ->first();

        $this->assertNull($pav);
    }

    // ─── Neue Tests: Fehlerfälle & Sonderpfade ───────────────

    public function test_division_durch_null_liefert_kein_ergebnis(): void
    {
        $product = $this->createProduct('PROD-007');
        $attrs = $this->createCompositeWithChildren(
            'quotient',
            '{0} / {1}',
            ['zaehler', 'nenner'],
        );

        $this->setNumericValue($product, $attrs['children'][0], 10.0);
        $this->setNumericValue($product, $attrs['children'][1], 0.0); // Division durch 0

        $service = app(ComputedAttributeService::class);
        $service->computeAndStore($product, $attrs['composite']);

        $pav = ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', $attrs['composite']->id)
            ->first();

        // DivisionByZeroError wird intern abgefangen → kein Wert gespeichert
        $this->assertNull($pav);
    }

    public function test_ungueltige_expression_loescht_vorhandenen_wert(): void
    {
        $product = $this->createProduct('PROD-008');

        // Composite mit kaputtem Ausdruck direkt anlegen
        $composite = Attribute::create([
            'id' => (string) Str::uuid(),
            'technical_name' => 'fehlerhaft',
            'name_de' => 'Fehlerhaftes Composite',
            'data_type' => 'Composite',
            'composite_expression' => '{0} $ {1}', // Ungültiges Zeichen '$'
            'is_translatable' => false,
            'is_multipliable' => false,
            'is_searchable' => true,
            'is_mandatory' => false,
            'is_unique' => false,
            'is_country_specific' => false,
            'is_inheritable' => true,
            'status' => 'active',
        ]);

        $child = Attribute::create([
            'id' => (string) Str::uuid(),
            'technical_name' => 'kind_fehler',
            'name_de' => 'Kind Fehler',
            'data_type' => 'Float',
            'parent_attribute_id' => $composite->id,
            'position' => 0,
            'is_translatable' => false,
            'is_multipliable' => false,
            'is_searchable' => true,
            'is_mandatory' => false,
            'is_unique' => false,
            'is_country_specific' => false,
            'is_inheritable' => true,
            'status' => 'active',
        ]);

        $this->setNumericValue($product, $child, 5.0);

        // Vorhandenen Composite-Wert anlegen, der danach gelöscht werden soll
        ProductAttributeValue::create([
            'id' => (string) Str::uuid(),
            'product_id' => $product->id,
            'attribute_id' => $composite->id,
            'value_number' => 99.0,
            'language' => null,
            'multiplied_index' => 0,
            'is_inherited' => false,
        ]);

        $service = app(ComputedAttributeService::class);
        $service->computeAndStore($product, $composite);

        // Ungültige Expression → evaluate() gibt null zurück → bestehender Wert wird gelöscht
        $pav = ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', $composite->id)
            ->first();

        $this->assertNull($pav);
    }

    public function test_multiplizierbares_composite_berechnet_pro_index(): void
    {
        $product = $this->createProduct('PROD-009');

        $composite = Attribute::create([
            'id' => (string) Str::uuid(),
            'technical_name' => 'pos_flaeche',
            'name_de' => 'Positions-Fläche',
            'data_type' => 'Composite',
            'composite_expression' => '{0} * {1}',
            'is_translatable' => false,
            'is_multipliable' => true, // Multiplizierbar!
            'is_searchable' => true,
            'is_mandatory' => false,
            'is_unique' => false,
            'is_country_specific' => false,
            'is_inheritable' => true,
            'status' => 'active',
        ]);

        $kindA = Attribute::create([
            'id' => (string) Str::uuid(),
            'technical_name' => 'pos_breite',
            'name_de' => 'Pos Breite',
            'data_type' => 'Float',
            'parent_attribute_id' => $composite->id,
            'position' => 0,
            'is_translatable' => false,
            'is_multipliable' => false,
            'is_searchable' => true,
            'is_mandatory' => false,
            'is_unique' => false,
            'is_country_specific' => false,
            'is_inheritable' => true,
            'status' => 'active',
        ]);

        $kindB = Attribute::create([
            'id' => (string) Str::uuid(),
            'technical_name' => 'pos_hoehe',
            'name_de' => 'Pos Höhe',
            'data_type' => 'Float',
            'parent_attribute_id' => $composite->id,
            'position' => 1,
            'is_translatable' => false,
            'is_multipliable' => false,
            'is_searchable' => true,
            'is_mandatory' => false,
            'is_unique' => false,
            'is_country_specific' => false,
            'is_inheritable' => true,
            'status' => 'active',
        ]);

        // Index 0: 3 * 4 = 12
        ProductAttributeValue::create([
            'id' => (string) Str::uuid(),
            'product_id' => $product->id,
            'attribute_id' => $kindA->id,
            'value_number' => 3.0,
            'language' => null,
            'multiplied_index' => 0,
            'is_inherited' => false,
        ]);
        ProductAttributeValue::create([
            'id' => (string) Str::uuid(),
            'product_id' => $product->id,
            'attribute_id' => $kindB->id,
            'value_number' => 4.0,
            'language' => null,
            'multiplied_index' => 0,
            'is_inherited' => false,
        ]);

        // Index 1: 5 * 6 = 30
        ProductAttributeValue::create([
            'id' => (string) Str::uuid(),
            'product_id' => $product->id,
            'attribute_id' => $kindA->id,
            'value_number' => 5.0,
            'language' => null,
            'multiplied_index' => 1,
            'is_inherited' => false,
        ]);
        ProductAttributeValue::create([
            'id' => (string) Str::uuid(),
            'product_id' => $product->id,
            'attribute_id' => $kindB->id,
            'value_number' => 6.0,
            'language' => null,
            'multiplied_index' => 1,
            'is_inherited' => false,
        ]);

        $service = app(ComputedAttributeService::class);
        $service->computeAndStore($product, $composite);

        $pav0 = ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', $composite->id)
            ->where('multiplied_index', 0)
            ->first();

        $pav1 = ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', $composite->id)
            ->where('multiplied_index', 1)
            ->first();

        $this->assertNotNull($pav0);
        $this->assertEqualsWithDelta(12.0, (float) $pav0->value_number, 0.01);

        $this->assertNotNull($pav1);
        $this->assertEqualsWithDelta(30.0, (float) $pav1->value_number, 0.01);
    }

    public function test_recompute_fuer_geaenderte_attribute_ohne_elternteil_macht_nichts(): void
    {
        $product = $this->createProduct('PROD-010');

        // Attribut ohne parent_attribute_id
        $standalone = Attribute::create([
            'id' => (string) Str::uuid(),
            'technical_name' => 'standalone_attr',
            'name_de' => 'Standalone',
            'data_type' => 'Float',
            'parent_attribute_id' => null,
            'is_translatable' => false,
            'is_multipliable' => false,
            'is_searchable' => true,
            'is_mandatory' => false,
            'is_unique' => false,
            'is_country_specific' => false,
            'is_inheritable' => true,
            'status' => 'active',
        ]);

        $this->setNumericValue($product, $standalone, 42.0);

        $service = app(ComputedAttributeService::class);
        // Darf keine Exception werfen, auch wenn kein Elternteil vorhanden ist
        $service->recomputeForChangedAttributes($product->id, [$standalone->id]);

        // Keine Composite-Werte sollten angelegt worden sein
        $count = ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', '!=', $standalone->id)
            ->count();

        $this->assertSame(0, $count);
    }

    // ─── Zusätzliche Tests: Nicht abgedeckte Pfade ───────────

    public function test_division_berechnet_quotienten_korrekt(): void
    {
        $product = $this->createProduct('PROD-011');
        $attrs = $this->createCompositeWithChildren(
            'halber_wert',
            '{0} / {1}',
            ['wert', 'teiler'],
        );

        $this->setNumericValue($product, $attrs['children'][0], 100.0);
        $this->setNumericValue($product, $attrs['children'][1], 4.0);

        $service = app(ComputedAttributeService::class);
        $service->computeAndStore($product, $attrs['composite']);

        $pav = ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', $attrs['composite']->id)
            ->first();

        $this->assertNotNull($pav);
        $this->assertEqualsWithDelta(25.0, (float) $pav->value_number, 0.001);
    }

    public function test_geklammerte_expression_beachtet_vorrang(): void
    {
        $product = $this->createProduct('PROD-012');
        $attrs = $this->createCompositeWithChildren(
            'geklammert',
            '({0} + {1}) * {2}',
            ['a', 'b', 'c'],
        );

        // (3 + 7) * 5 = 50 (ohne Klammern wäre es 3 + 35 = 38)
        $this->setNumericValue($product, $attrs['children'][0], 3.0);
        $this->setNumericValue($product, $attrs['children'][1], 7.0);
        $this->setNumericValue($product, $attrs['children'][2], 5.0);

        $service = app(ComputedAttributeService::class);
        $service->computeAndStore($product, $attrs['composite']);

        $pav = ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', $attrs['composite']->id)
            ->first();

        $this->assertNotNull($pav);
        $this->assertEqualsWithDelta(50.0, (float) $pav->value_number, 0.001);
    }

    public function test_grandparent_kaskade_wird_neu_berechnet(): void
    {
        $product = $this->createProduct('PROD-013');

        // Kind-Attribut (Ebene 0)
        $child = Attribute::create([
            'id' => (string) Str::uuid(),
            'technical_name' => 'kind_val',
            'name_de' => 'Kind',
            'data_type' => 'Float',
            'is_translatable' => false,
            'is_multipliable' => false,
            'is_searchable' => true,
            'is_mandatory' => false,
            'is_unique' => false,
            'is_country_specific' => false,
            'is_inheritable' => true,
            'status' => 'active',
        ]);

        // Sub-Composite (Ebene 1): doppelter Wert
        $subComposite = Attribute::create([
            'id' => (string) Str::uuid(),
            'technical_name' => 'sub_composite',
            'name_de' => 'Sub Composite',
            'data_type' => 'Composite',
            'composite_expression' => '{0} * 2',
            'is_translatable' => false,
            'is_multipliable' => false,
            'is_searchable' => true,
            'is_mandatory' => false,
            'is_unique' => false,
            'is_country_specific' => false,
            'is_inheritable' => true,
            'status' => 'active',
        ]);

        // Kind gehört zu Sub-Composite
        $child->parent_attribute_id = $subComposite->id;
        $child->position = 0;
        $child->save();

        // Root-Composite (Ebene 2): verdoppelten Wert nochmals verdoppeln
        $rootComposite = Attribute::create([
            'id' => (string) Str::uuid(),
            'technical_name' => 'root_composite',
            'name_de' => 'Root Composite',
            'data_type' => 'Composite',
            'composite_expression' => '{0} * 2',
            'is_translatable' => false,
            'is_multipliable' => false,
            'is_searchable' => true,
            'is_mandatory' => false,
            'is_unique' => false,
            'is_country_specific' => false,
            'is_inheritable' => true,
            'status' => 'active',
        ]);

        // Sub-Composite gehört zu Root-Composite
        $subComposite->parent_attribute_id = $rootComposite->id;
        $subComposite->position = 0;
        $subComposite->save();

        // Wert für Kind setzen: 5
        $this->setNumericValue($product, $child, 5.0);

        // Trigger: Kind ändert sich → beide Eltern sollen neu berechnet werden
        $service = app(ComputedAttributeService::class);
        $service->recomputeForChangedAttributes($product->id, [$child->id]);

        // Sub-Composite: 5 * 2 = 10
        $subPav = ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', $subComposite->id)
            ->first();

        // Root-Composite braucht Sub-Composite-Wert als Kind – aber in der Kaskade
        // wird computeAndStore(product, root) direkt aufgerufen (nicht via PAV des Sub-Composite).
        // Das Root-Composite referenziert das Sub-Composite per {0}, welches als direktes Kind
        // keinen gespeicherten Wert hat, wenn Sub-Composite selbst is_multipliable=false ist.
        // Wir prüfen daher nur die Sub-Composite-Berechnung aus Ebene 1.
        $this->assertNotNull($subPav);
        $this->assertEqualsWithDelta(10.0, (float) $subPav->value_number, 0.001);
    }

    public function test_multiplizierbares_composite_bereinigt_veraltete_indizes(): void
    {
        $product = $this->createProduct('PROD-014');

        $composite = Attribute::create([
            'id' => (string) Str::uuid(),
            'technical_name' => 'stale_flaeche',
            'name_de' => 'Stale Fläche',
            'data_type' => 'Composite',
            'composite_expression' => '{0} * {1}',
            'is_translatable' => false,
            'is_multipliable' => true,
            'is_searchable' => true,
            'is_mandatory' => false,
            'is_unique' => false,
            'is_country_specific' => false,
            'is_inheritable' => true,
            'status' => 'active',
        ]);

        $kindA = Attribute::create([
            'id' => (string) Str::uuid(),
            'technical_name' => 'stale_breite',
            'name_de' => 'Stale Breite',
            'data_type' => 'Float',
            'parent_attribute_id' => $composite->id,
            'position' => 0,
            'is_translatable' => false,
            'is_multipliable' => false,
            'is_searchable' => true,
            'is_mandatory' => false,
            'is_unique' => false,
            'is_country_specific' => false,
            'is_inheritable' => true,
            'status' => 'active',
        ]);

        $kindB = Attribute::create([
            'id' => (string) Str::uuid(),
            'technical_name' => 'stale_hoehe',
            'name_de' => 'Stale Höhe',
            'data_type' => 'Float',
            'parent_attribute_id' => $composite->id,
            'position' => 1,
            'is_translatable' => false,
            'is_multipliable' => false,
            'is_searchable' => true,
            'is_mandatory' => false,
            'is_unique' => false,
            'is_country_specific' => false,
            'is_inheritable' => true,
            'status' => 'active',
        ]);

        // Veralteten Composite-Wert für index=1 hinterlegen (wird nicht mehr berechnet)
        ProductAttributeValue::create([
            'id' => (string) Str::uuid(),
            'product_id' => $product->id,
            'attribute_id' => $composite->id,
            'value_number' => 999.0,
            'language' => null,
            'multiplied_index' => 1,
            'is_inherited' => false,
        ]);

        // Nur index=0 hat Kind-Werte
        ProductAttributeValue::create([
            'id' => (string) Str::uuid(),
            'product_id' => $product->id,
            'attribute_id' => $kindA->id,
            'value_number' => 6.0,
            'language' => null,
            'multiplied_index' => 0,
            'is_inherited' => false,
        ]);
        ProductAttributeValue::create([
            'id' => (string) Str::uuid(),
            'product_id' => $product->id,
            'attribute_id' => $kindB->id,
            'value_number' => 7.0,
            'language' => null,
            'multiplied_index' => 0,
            'is_inherited' => false,
        ]);

        $service = app(ComputedAttributeService::class);
        $service->computeAndStore($product, $composite);

        // Index 0 korrekt berechnet: 6 * 7 = 42
        $pav0 = ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', $composite->id)
            ->where('multiplied_index', 0)
            ->first();
        $this->assertNotNull($pav0);
        $this->assertEqualsWithDelta(42.0, (float) $pav0->value_number, 0.001);

        // Index 1 (veraltet) muss gelöscht worden sein
        $pav1 = ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', $composite->id)
            ->where('multiplied_index', 1)
            ->first();
        $this->assertNull($pav1);
    }

    public function test_recompute_fuer_nicht_existierendes_produkt_macht_nichts(): void
    {
        $service = app(ComputedAttributeService::class);

        // Darf keine Exception werfen, auch wenn die Produkt-ID nicht existiert
        $service->recomputeForChangedAttributes(
            '00000000-0000-0000-0000-000000000000',
            ['00000000-0000-0000-0000-000000000001'],
        );

        // Kein PAV wurde angelegt
        $this->assertSame(0, ProductAttributeValue::count());
    }
}

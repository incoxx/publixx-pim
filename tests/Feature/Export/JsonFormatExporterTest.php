<?php

declare(strict_types=1);

namespace Tests\Feature\Export;

use App\Models\Attribute;
use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use App\Models\PriceType;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductPrice;
use App\Models\ProductType;
use App\Models\Unit;
use App\Services\Export\JsonFormatExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature-Tests für den JSON-Export im anypim-json Format.
 *
 * Hinweis: JsonFormatExporter arbeitet sektionsbasiert (export($sections, $filters)),
 * nicht mapping-basiert — Mapping/flat/nested wird vom DatasetBuilder abgedeckt
 * (siehe DatasetBuilderTest).
 */
class JsonFormatExporterTest extends TestCase
{
    use RefreshDatabase;

    protected JsonFormatExporter $exporter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->exporter = new JsonFormatExporter();
    }

    /** Export ausführen und dekodiertes Array zurückgeben. */
    private function exportDecoded(array $sections = [], array $filters = []): array
    {
        $json = $this->exporter->export($sections, $filters);
        $decoded = json_decode($json, true);
        $this->assertSame(JSON_ERROR_NONE, json_last_error(), 'Export muss valides JSON liefern');

        return $decoded;
    }

    // ─── Grundstruktur ──────────────────────────────────────────

    public function test_export_liefert_valides_json_mit_meta_block(): void
    {
        Product::factory()->create();

        $data = $this->exportDecoded();

        $this->assertArrayHasKey('_meta', $data);
        $this->assertSame('anypim-json', $data['_meta']['format']);
        $this->assertSame('1.0', $data['_meta']['version']);
        $this->assertContains('products', $data['_meta']['sections']);
        $this->assertContains('attributes', $data['_meta']['sections']);
    }

    public function test_nur_angeforderte_sektionen_werden_exportiert(): void
    {
        Product::factory()->create();
        Attribute::factory()->create();

        $data = $this->exportDecoded(['products', 'attributes']);

        $this->assertArrayHasKey('products', $data);
        $this->assertArrayHasKey('attributes', $data);
        $this->assertArrayNotHasKey('prices', $data);
        $this->assertArrayNotHasKey('units', $data);
        $this->assertSame(['attributes', 'products'], $data['_meta']['sections']);
    }

    public function test_produkte_sektion_enthaelt_stammdaten(): void
    {
        $type = ProductType::factory()->create(['technical_name' => 'werkzeug']);
        Product::factory()->create([
            'sku' => 'JSON-001',
            'name' => 'Akkubohrschrauber',
            'ean' => '4012345678901',
            'status' => 'active',
            'product_type_id' => $type->id,
        ]);

        $data = $this->exportDecoded(['products']);

        $this->assertCount(1, $data['products']);
        $product = $data['products'][0];
        $this->assertSame('JSON-001', $product['sku']);
        $this->assertSame('Akkubohrschrauber', $product['name']);
        $this->assertSame('4012345678901', $product['ean']);
        $this->assertSame('active', $product['status']);
        $this->assertSame('werkzeug', $product['product_type']);
    }

    // ─── Werte-Auflösung ────────────────────────────────────────

    public function test_attributwerte_sektion_mit_typaufloesung(): void
    {
        $product = Product::factory()->create(['sku' => 'JSON-002']);
        $stringAttr = Attribute::factory()->create(['technical_name' => 'farbe']);
        $numberAttr = Attribute::factory()->numeric()->create(['technical_name' => 'gewicht-kg']);
        $unit = Unit::factory()->create(['abbreviation' => 'kg']);

        ProductAttributeValue::factory()->create([
            'product_id' => $product->id,
            'attribute_id' => $stringAttr->id,
            'value_string' => 'Rot',
            'language' => 'de',
        ]);
        ProductAttributeValue::factory()->create([
            'product_id' => $product->id,
            'attribute_id' => $numberAttr->id,
            'value_string' => null,
            'value_number' => 1.8,
            'unit_id' => $unit->id,
        ]);

        $data = $this->exportDecoded(['product_attribute_values']);

        $values = collect($data['product_attribute_values']);
        $this->assertCount(2, $values);

        $farbe = $values->firstWhere('attribute', 'farbe');
        $this->assertSame('Rot', $farbe['value']);
        $this->assertSame('de', $farbe['language']);

        $gewicht = $values->firstWhere('attribute', 'gewicht-kg');
        $this->assertEqualsWithDelta(1.8, (float) $gewicht['value'], 0.0001);
        $this->assertSame('kg', $gewicht['unit']);
        $this->assertSame('JSON-002', $gewicht['sku']);
    }

    public function test_preise_sektion(): void
    {
        $product = Product::factory()->create(['sku' => 'JSON-003']);
        $priceType = PriceType::factory()->create(['technical_name' => 'list_price']);

        ProductPrice::factory()->create([
            'product_id' => $product->id,
            'price_type_id' => $priceType->id,
            'amount' => 189.99,
            'currency' => 'EUR',
            'valid_from' => '2026-01-01',
        ]);

        $data = $this->exportDecoded(['prices']);

        $this->assertCount(1, $data['prices']);
        $price = $data['prices'][0];
        $this->assertSame('JSON-003', $price['sku']);
        $this->assertSame('list_price', $price['price_type']);
        $this->assertEqualsWithDelta(189.99, (float) $price['amount'], 0.001);
        $this->assertSame('EUR', $price['currency']);
        $this->assertSame('2026-01-01', $price['valid_from']);
    }

    public function test_varianten_sektion_mit_parent_sku(): void
    {
        $parent = Product::factory()->create(['sku' => 'PARENT-001']);
        Product::factory()->variant()->create([
            'sku' => 'VAR-001',
            'name' => 'Variante Rot',
            'parent_product_id' => $parent->id,
        ]);

        $data = $this->exportDecoded(['variants']);

        $this->assertCount(1, $data['variants']);
        $this->assertSame('PARENT-001', $data['variants'][0]['parent_sku']);
        $this->assertSame('VAR-001', $data['variants'][0]['sku']);
    }

    public function test_hierarchien_sektion_mit_lesbaren_pfaden(): void
    {
        $hierarchy = Hierarchy::factory()->create(['technical_name' => 'master']);
        $root = HierarchyNode::factory()->create([
            'hierarchy_id' => $hierarchy->id,
            'depth' => 0,
            'name_de' => 'Wurzel',
        ]);
        HierarchyNode::factory()->create([
            'hierarchy_id' => $hierarchy->id,
            'parent_node_id' => $root->id,
            'depth' => 1,
            'name_de' => 'Werkzeuge',
        ]);

        $data = $this->exportDecoded(['hierarchies']);

        $this->assertCount(1, $data['hierarchies']);
        $h = $data['hierarchies'][0];
        $this->assertSame('master', $h['technical_name']);
        // Wurzelknoten (depth 0) wird nicht exportiert, nur tiefere Knoten
        $this->assertCount(1, $h['nodes']);
        $this->assertSame('/Werkzeuge/', $h['nodes'][0]['path']);
    }

    // ─── Filter ─────────────────────────────────────────────────

    public function test_status_filter(): void
    {
        Product::factory()->active()->create(['sku' => 'AKTIV-001']);
        Product::factory()->create(['sku' => 'DRAFT-001', 'status' => 'draft']);

        $data = $this->exportDecoded(['products'], ['status' => 'active']);

        $this->assertCount(1, $data['products']);
        $this->assertSame('AKTIV-001', $data['products'][0]['sku']);
    }

    public function test_product_type_filter(): void
    {
        $typeA = ProductType::factory()->create(['technical_name' => 'typ-a']);
        $typeB = ProductType::factory()->create(['technical_name' => 'typ-b']);
        Product::factory()->create(['sku' => 'A-001', 'product_type_id' => $typeA->id]);
        Product::factory()->create(['sku' => 'B-001', 'product_type_id' => $typeB->id]);

        $data = $this->exportDecoded(['products'], ['product_type' => 'typ-a']);

        $this->assertCount(1, $data['products']);
        $this->assertSame('A-001', $data['products'][0]['sku']);
    }

    public function test_sku_filter_wirkt_auch_auf_abhaengige_sektionen(): void
    {
        $productA = Product::factory()->create(['sku' => 'SKU-A']);
        $productB = Product::factory()->create(['sku' => 'SKU-B']);
        $attribute = Attribute::factory()->create(['technical_name' => 'farbe']);

        ProductAttributeValue::factory()->create([
            'product_id' => $productA->id,
            'attribute_id' => $attribute->id,
            'value_string' => 'Rot',
        ]);
        ProductAttributeValue::factory()->create([
            'product_id' => $productB->id,
            'attribute_id' => $attribute->id,
            'value_string' => 'Blau',
        ]);

        $data = $this->exportDecoded(
            ['products', 'product_attribute_values'],
            ['skus' => ['SKU-A']],
        );

        $this->assertCount(1, $data['products']);
        $this->assertSame('SKU-A', $data['products'][0]['sku']);
        $this->assertCount(1, $data['product_attribute_values']);
        $this->assertSame('Rot', $data['product_attribute_values'][0]['value']);
    }

    // ─── Unicode & Streaming ────────────────────────────────────

    public function test_umlaute_werden_nicht_escaped(): void
    {
        Product::factory()->create(['sku' => 'UML-001', 'name' => 'Übergrößen-Schraubendreher äöüß']);

        $json = $this->exporter->export(['products']);

        // JSON_UNESCAPED_UNICODE: literal im Output
        $this->assertStringContainsString('Übergrößen-Schraubendreher äöüß', $json);
        $this->assertStringNotContainsString('\u00', $json);
    }

    public function test_export_to_file_streamed_erzeugt_aequivalente_struktur(): void
    {
        $product = Product::factory()->active()->create(['sku' => 'STREAM-001', 'name' => 'Größenmaß']);
        $attribute = Attribute::factory()->create(['technical_name' => 'farbe']);
        ProductAttributeValue::factory()->create([
            'product_id' => $product->id,
            'attribute_id' => $attribute->id,
            'value_string' => 'Rot',
        ]);

        $path = tempnam(sys_get_temp_dir(), 'jsonexp_') . '.json';

        try {
            $this->exporter->exportToFileStreamed($path, ['products', 'product_attribute_values']);

            $this->assertFileExists($path);
            $streamed = json_decode((string) file_get_contents($path), true);
            $this->assertSame(JSON_ERROR_NONE, json_last_error(), 'Streaming-Export muss valides JSON liefern');

            $this->assertSame('anypim-json', $streamed['_meta']['format']);
            $this->assertCount(1, $streamed['products']);
            $this->assertSame('STREAM-001', $streamed['products'][0]['sku']);
            $this->assertSame('Größenmaß', $streamed['products'][0]['name']);
            $this->assertCount(1, $streamed['product_attribute_values']);
            $this->assertSame('Rot', $streamed['product_attribute_values'][0]['value']);

            // Inhaltlich identisch zum In-Memory-Export.
            // Hinweis: Der Streaming-Pfad filtert null-Felder (array_filter),
            // der In-Memory-Pfad nicht → null-Felder vor dem Vergleich entfernen.
            $inMemory = $this->exportDecoded(['products', 'product_attribute_values']);
            $inMemoryProducts = array_map(
                fn (array $p) => array_filter($p, fn ($v) => $v !== null),
                $inMemory['products'],
            );
            $this->assertEquals($inMemoryProducts, $streamed['products']);
            $this->assertEquals($inMemory['product_attribute_values'], $streamed['product_attribute_values']);
        } finally {
            @unlink($path);
        }
    }
}

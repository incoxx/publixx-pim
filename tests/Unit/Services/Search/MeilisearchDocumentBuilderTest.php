<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Search;

use App\Models\Attribute;
use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\Unit;
use App\Models\UnitGroup;
use App\Models\ValueListEntry;
use App\Services\Search\MeilisearchDocumentBuilder;
use App\Services\Search\SearchSchemaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Unit-Tests für den MeilisearchDocumentBuilder.
 *
 * Quelle ist der denormalisierte products_search_index plus filterbare
 * attr_*-Werte aus der EAV-Tabelle. Das Schema wird vorgeladen übergeben
 * (SearchSchemaService::__construct($preloaded)), damit kein Cache/DB-Build nötig ist.
 */
class MeilisearchDocumentBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    /** Builder mit vorgeladenem Schema bauen. */
    private function builder(array $attributes = []): MeilisearchDocumentBuilder
    {
        return new MeilisearchDocumentBuilder(new SearchSchemaService([
            'attributes' => $attributes,
            'units' => [],
            'unit_attributes' => [],
            'concepts' => [],
        ]));
    }

    /** Produkt mit Suchindex-Zeile anlegen. */
    private function createIndexedProduct(array $productAttrs = [], array $indexAttrs = []): Product
    {
        $product = Product::factory()->active()->create($productAttrs);

        DB::table('products_search_index')->insert(array_merge([
            'product_id' => $product->id,
            'sku' => $product->sku,
            'ean' => $product->ean,
            'name_de' => $product->name,
            'status' => $product->status,
        ], $indexAttrs));

        return $product;
    }

    public function test_build_liefert_null_fuer_nicht_indexiertes_produkt(): void
    {
        $product = Product::factory()->create();

        $this->assertNull($this->builder()->build($product->id));
    }

    public function test_build_erzeugt_basis_dokument_aus_indexzeile(): void
    {
        $product = $this->createIndexedProduct(['name' => 'Akkubohrer'], [
            'description_de' => '<p>Starker <b>Bohrer</b></p>',
            'list_price' => '189.99',
            'attribute_completeness' => 80,
            'updated_at' => '2026-06-01 12:00:00',
        ]);

        $document = $this->builder()->build($product->id);

        $this->assertNotNull($document);
        $this->assertSame($product->id, $document['id']);
        $this->assertSame($product->sku, $document['sku']);
        $this->assertSame('active', $document['status']);
        $this->assertSame('Akkubohrer', $document['product_name']);
        // HTML wird entfernt, Preis und Vollständigkeit typisiert
        $this->assertSame('Starker Bohrer', $document['description_de']);
        $this->assertSame(189.99, $document['list_price']);
        $this->assertSame(80, $document['attribute_completeness']);
        // updated_at als Unix-Timestamp (sortierbar)
        $this->assertIsInt($document['updated_at']);
    }

    public function test_hierarchiepfad_wird_in_lesbare_namen_aufgeloest(): void
    {
        $hierarchy = Hierarchy::factory()->create();
        $root = HierarchyNode::factory()->create([
            'hierarchy_id' => $hierarchy->id,
            'name_de' => 'Kryotechnik',
        ]);
        $child = HierarchyNode::factory()->create([
            'hierarchy_id' => $hierarchy->id,
            'name_de' => 'Schläuche',
        ]);
        $uuidPath = '/' . $root->id . '/' . $child->id . '/';

        $product = $this->createIndexedProduct([], ['hierarchy_path' => $uuidPath]);

        $document = $this->builder()->build($product->id);

        $this->assertSame('Kryotechnik > Schläuche', $document['hierarchy_path']);
        // UUID-Pfad bleibt für exakte Filter erhalten
        $this->assertSame($uuidPath, $document['hierarchy_path_ids']);
    }

    public function test_zahlen_attribut_wird_in_basiseinheit_normalisiert(): void
    {
        $attribut = Attribute::factory()->numeric()->create(['technical_name' => 'gewicht']);
        $gruppe = UnitGroup::factory()->create();
        $gramm = Unit::factory()->create([
            'unit_group_id' => $gruppe->id,
            'abbreviation' => 'g',
            'conversion_factor' => 0.001,
            'is_base_unit' => false,
        ]);

        $product = $this->createIndexedProduct();
        ProductAttributeValue::factory()->create([
            'product_id' => $product->id,
            'attribute_id' => $attribut->id,
            'value_string' => null,
            'value_number' => 500,
            'unit_id' => $gramm->id,
        ]);

        $document = $this->builder([
            'gewicht' => ['field' => 'attr_gewicht', 'data_type' => 'Number', 'default_to_base' => 1.0],
        ])->build($product->id);

        // 500 g → 0.5 (Basiseinheit kg)
        $this->assertSame(0.5, $document['attr_gewicht']);
    }

    public function test_zahlen_attribut_ohne_einheit_nutzt_default_faktor(): void
    {
        $attribut = Attribute::factory()->numeric()->create(['technical_name' => 'breite']);
        $product = $this->createIndexedProduct();
        ProductAttributeValue::factory()->create([
            'product_id' => $product->id,
            'attribute_id' => $attribut->id,
            'value_string' => null,
            'value_number' => 250,
        ]);

        $document = $this->builder([
            'breite' => ['field' => 'attr_breite', 'data_type' => 'Number', 'default_to_base' => 0.01],
        ])->build($product->id);

        $this->assertSame(2.5, $document['attr_breite']);
    }

    public function test_flag_attribut_wird_boolesch(): void
    {
        $attribut = Attribute::factory()->create(['technical_name' => 'akku', 'data_type' => 'Flag']);
        $product = $this->createIndexedProduct();
        ProductAttributeValue::factory()->create([
            'product_id' => $product->id,
            'attribute_id' => $attribut->id,
            'value_string' => null,
            'value_flag' => true,
        ]);

        $document = $this->builder([
            'akku' => ['field' => 'attr_akku', 'data_type' => 'Flag'],
        ])->build($product->id);

        $this->assertTrue($document['attr_akku']);
    }

    public function test_selection_attribut_sammelt_anzeigewerte_als_array(): void
    {
        $attribut = Attribute::factory()->create(['technical_name' => 'farbe', 'data_type' => 'Selection']);
        $rot = ValueListEntry::factory()->create(['display_value_de' => 'Rot']);
        $blau = ValueListEntry::factory()->create(['display_value_de' => 'Blau']);

        $product = $this->createIndexedProduct();
        foreach ([[0, $rot], [1, $blau]] as [$index, $entry]) {
            ProductAttributeValue::factory()->create([
                'product_id' => $product->id,
                'attribute_id' => $attribut->id,
                'value_string' => null,
                'value_selection_id' => $entry->id,
                'multiplied_index' => $index,
            ]);
        }

        $document = $this->builder([
            'farbe' => ['field' => 'attr_farbe', 'data_type' => 'Selection'],
        ])->build($product->id);

        $this->assertSame(['Rot', 'Blau'], $document['attr_farbe']);
    }

    public function test_build_batch_erzeugt_dokumente_fuer_mehrere_produkte(): void
    {
        $a = $this->createIndexedProduct(['name' => 'Produkt A']);
        $b = $this->createIndexedProduct(['name' => 'Produkt B']);
        $nichtIndexiert = Product::factory()->create();

        $documents = $this->builder()->buildBatch([$a->id, $b->id, $nichtIndexiert->id]);

        $this->assertCount(2, $documents);
        $this->assertEqualsCanonicalizing(
            [$a->id, $b->id],
            array_column($documents, 'id'),
        );
    }

    public function test_build_batch_mit_leerer_liste_liefert_leeres_array(): void
    {
        $this->assertSame([], $this->builder()->buildBatch([]));
    }
}

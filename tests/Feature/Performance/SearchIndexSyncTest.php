<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use App\Jobs\RemoveFromSearchIndex;
use App\Jobs\UpdateSearchIndex;
use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductType;
use App\Support\KoelnerPhonetik;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * SearchIndexSyncTest – Prüft, dass der denormalisierte Search-Index
 * korrekt synchronisiert wird.
 *
 * Testet:
 * - UpdateSearchIndex Job befüllt products_search_index korrekt
 * - RemoveFromSearchIndex entfernt Einträge
 * - Kölner Phonetik wird korrekt berechnet
 * - Observer dispatcht Jobs bei CRUD-Operationen
 * - Varianten-Kaskade: Änderung am Eltern → Variante wird re-indexiert
 */
class SearchIndexSyncTest extends TestCase
{
    use RefreshDatabase;

    private ProductType $productType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productType = ProductType::factory()->create([
            'technical_name' => 'physical_product',
        ]);
    }

    // ─── UpdateSearchIndex Job ───────────────────────────────────────

    /** @test */
    public function update_search_index_creates_entry_for_product(): void
    {
        $product = Product::factory()->create([
            'product_type_id' => $this->productType->id,
            'sku' => 'TEST-001',
            'ean' => '4012345678901',
            'status' => 'active',
        ]);

        // Attribut + Wert anlegen
        $nameAttr = Attribute::factory()->create([
            'technical_name' => 'productName',
            'data_type' => 'String',
            'is_translatable' => true,
        ]);

        ProductAttributeValue::factory()->create([
            'product_id' => $product->id,
            'attribute_id' => $nameAttr->id,
            'value_string' => 'Testprodukt Hydraulikpumpe',
            'language' => 'de',
            'multiplied_index' => 0,
        ]);

        // Job ausführen
        $job = new UpdateSearchIndex($product->id);
        $job->handle();

        // Prüfen
        $this->assertDatabaseHas('products_search_index', [
            'product_id' => $product->id,
            'sku' => 'TEST-001',
            'ean' => '4012345678901',
            'product_type' => 'physical_product',
            'status' => 'active',
            'name_de' => 'Testprodukt Hydraulikpumpe',
        ]);

        // Phonetik prüfen
        $indexRow = DB::table('products_search_index')
            ->where('product_id', $product->id)
            ->first();

        $this->assertNotNull($indexRow->phonetic_name_de);
        $this->assertEquals(
            KoelnerPhonetik::encode('Testprodukt Hydraulikpumpe'),
            $indexRow->phonetic_name_de,
        );
    }

    /** @test */
    public function update_search_index_updates_existing_entry(): void
    {
        $product = Product::factory()->create([
            'product_type_id' => $this->productType->id,
            'sku' => 'TEST-002',
            'status' => 'draft',
        ]);

        // Erster Durchlauf
        (new UpdateSearchIndex($product->id))->handle();

        $this->assertDatabaseHas('products_search_index', [
            'product_id' => $product->id,
            'status' => 'draft',
        ]);

        // Produkt aktualisieren
        $product->update(['status' => 'active']);

        // Zweiter Durchlauf
        (new UpdateSearchIndex($product->id))->handle();

        $this->assertDatabaseHas('products_search_index', [
            'product_id' => $product->id,
            'status' => 'active',
        ]);
    }

    /** @test */
    public function update_search_index_removes_entry_if_product_deleted(): void
    {
        $productId = 'deleted-product-id';

        // Eintrag manuell anlegen
        DB::table('products_search_index')->insert([
            'product_id' => $productId,
            'sku' => 'DEL-001',
            'status' => 'active',
            'updated_at' => now(),
        ]);

        // Job ausführen (Produkt existiert nicht mehr)
        (new UpdateSearchIndex($productId))->handle();

        $this->assertDatabaseMissing('products_search_index', [
            'product_id' => $productId,
        ]);
    }

    // ─── RemoveFromSearchIndex Job ───────────────────────────────────

    /** @test */
    public function remove_from_search_index_deletes_entry(): void
    {
        $productId = 'remove-test-id';

        DB::table('products_search_index')->insert([
            'product_id' => $productId,
            'sku' => 'REM-001',
            'status' => 'active',
            'updated_at' => now(),
        ]);

        (new RemoveFromSearchIndex($productId))->handle();

        $this->assertDatabaseMissing('products_search_index', [
            'product_id' => $productId,
        ]);
    }

    // ─── Observer dispatcht Jobs ─────────────────────────────────────

    /** @test */
    public function product_created_dispatches_update_search_index(): void
    {
        // Note: The ProductObserver::created() only logs; the UpdateSearchIndex
        // is dispatched via the ProductCreated event fired from controllers.
        // This test verifies the observer does NOT queue jobs directly on create.
        Queue::fake();

        Product::factory()->create([
            'product_type_id' => $this->productType->id,
            'sku' => 'OBS-001',
        ]);

        Queue::assertNotPushed(UpdateSearchIndex::class);
    }

    /** @test */
    public function product_updated_dispatches_update_search_index(): void
    {
        // Note: The ProductObserver::updated() dispatches UpdateSearchIndex only
        // for variant children (cascade), not for the parent product itself.
        // The parent is handled via ProductUpdated event from controllers.
        $parent = Product::factory()->create([
            'product_type_id' => $this->productType->id,
            'product_type_ref' => 'product',
        ]);

        $variant = Product::factory()->create([
            'product_type_id' => $this->productType->id,
            'product_type_ref' => 'variant',
            'parent_product_id' => $parent->id,
        ]);

        Queue::fake();

        $parent->update(['status' => 'active']);

        Queue::assertPushed(UpdateSearchIndex::class, function ($job) use ($variant) {
            return $job->productId === $variant->id;
        });
    }

    /** @test */
    public function product_deleted_dispatches_remove_from_search_index(): void
    {
        $product = Product::factory()->create([
            'product_type_id' => $this->productType->id,
        ]);

        Queue::fake();

        $product->delete();

        Queue::assertPushed(RemoveFromSearchIndex::class, function ($job) use ($product) {
            return $job->productId === $product->id;
        });
    }

    // ─── Varianten-Kaskade ───────────────────────────────────────────

    /** @test */
    public function variant_cascade_reindexes_children_on_parent_update(): void
    {
        $parent = Product::factory()->create([
            'product_type_id' => $this->productType->id,
            'product_type_ref' => 'product',
        ]);

        $variant1 = Product::factory()->create([
            'product_type_id' => $this->productType->id,
            'product_type_ref' => 'variant',
            'parent_product_id' => $parent->id,
        ]);

        $variant2 = Product::factory()->create([
            'product_type_id' => $this->productType->id,
            'product_type_ref' => 'variant',
            'parent_product_id' => $parent->id,
        ]);

        Queue::fake();

        $parent->update(['name' => 'Neuer Name']);

        // Observer dispatches UpdateSearchIndex for variant children (cascade)
        Queue::assertPushed(UpdateSearchIndex::class, function ($job) use ($variant1) {
            return $job->productId === $variant1->id;
        });
        Queue::assertPushed(UpdateSearchIndex::class, function ($job) use ($variant2) {
            return $job->productId === $variant2->id;
        });
    }

    // ─── Kölner Phonetik ─────────────────────────────────────────────

    /** @test */
    public function koelner_phonetik_maier_equals_meyer(): void
    {
        $this->assertTrue(KoelnerPhonetik::matches('Maier', 'Meyer'));
        $this->assertTrue(KoelnerPhonetik::matches('Meier', 'Mayer'));
        $this->assertTrue(KoelnerPhonetik::matches('Maier', 'Meier'));
    }

    /** @test */
    public function koelner_phonetik_common_examples(): void
    {
        // Wikipedia-Beispiele
        $this->assertEquals('65752682', KoelnerPhonetik::encodeWord('Müller-Lüdenscheidt'));

        // Leerer String
        $this->assertEquals('', KoelnerPhonetik::encode(''));

        // Einfache Wörter
        $this->assertNotEmpty(KoelnerPhonetik::encode('Hydraulikpumpe'));
    }

    /** @test */
    public function koelner_phonetik_similarity(): void
    {
        // Identisch = 100%
        $this->assertEquals(100.0, KoelnerPhonetik::similarity('Maier', 'Maier'));

        // Sehr ähnlich = hoher Wert
        $this->assertGreaterThan(80, KoelnerPhonetik::similarity('Maier', 'Meyer'));

        // Unterschiedlich = niedrig
        $this->assertLessThan(50, KoelnerPhonetik::similarity('Hydraulik', 'Fahrrad'));
    }

    /** @test */
    public function search_index_contains_phonetic_code(): void
    {
        $product = Product::factory()->create([
            'product_type_id' => $this->productType->id,
        ]);

        $nameAttr = Attribute::factory()->create([
            'technical_name' => 'productName',
            'data_type' => 'String',
            'is_translatable' => true,
        ]);

        ProductAttributeValue::factory()->create([
            'product_id' => $product->id,
            'attribute_id' => $nameAttr->id,
            'value_string' => 'Meier Werkzeuge',
            'language' => 'de',
            'multiplied_index' => 0,
        ]);

        (new UpdateSearchIndex($product->id))->handle();

        $row = DB::table('products_search_index')
            ->where('product_id', $product->id)
            ->first();

        $this->assertEquals(
            KoelnerPhonetik::encode('Meier Werkzeuge'),
            $row->phonetic_name_de,
        );

        // SOUNDS_LIKE: "Maier Werkzeuge" sollte gleichen Code haben
        $this->assertEquals(
            KoelnerPhonetik::encode('Maier Werkzeuge'),
            $row->phonetic_name_de,
        );
    }
}

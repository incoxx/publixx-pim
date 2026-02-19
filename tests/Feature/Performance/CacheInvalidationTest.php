<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use App\Jobs\UpdateSearchIndex;
use App\Jobs\WarmupCache;
use App\Models\Attribute;
use App\Models\HierarchyNode;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * CacheInvalidationTest – Prüft, dass Redis-Caches korrekt
 * invalidiert werden bei Datenänderungen.
 *
 * Testet:
 * - ProductObserver invalidiert product:{id}:* Tags
 * - HierarchyNodeObserver invalidiert hierarchy:{id}:tree
 * - AttributeValueObserver invalidiert Produkt-Cache
 * - AttributeObserver invalidiert attributes:all
 * - Varianten-Kaskade bei Eltern-Änderung
 * - WarmupCache nach Import
 */
class CacheInvalidationTest extends TestCase
{
    use RefreshDatabase;

    private ProductType $productType;

    protected function setUp(): void
    {
        parent::setUp();

        // Redis-Store muss konfiguriert sein (oder array für Tests)
        $this->productType = ProductType::factory()->create([
            'technical_name' => 'physical_product',
        ]);
    }

    // ─── Product Cache Invalidation ──────────────────────────────────

    /** @test */
    public function product_update_invalidates_cache(): void
    {
        $product = Product::factory()->create([
            'product_type_id' => $this->productType->id,
        ]);

        // Cache setzen
        Cache::tags(['product:' . $product->id])->put(
            "product:{$product->id}:full",
            ['cached' => true],
            3600,
        );

        $this->assertTrue(
            Cache::tags(['product:' . $product->id])->has("product:{$product->id}:full"),
        );

        Queue::fake();

        // Produkt aktualisieren → Observer soll Cache flushen
        $product->update(['status' => 'active']);

        // Cache sollte jetzt leer sein
        $this->assertFalse(
            Cache::tags(['product:' . $product->id])->has("product:{$product->id}:full"),
        );
    }

    /** @test */
    public function product_delete_invalidates_cache(): void
    {
        $product = Product::factory()->create([
            'product_type_id' => $this->productType->id,
        ]);

        Cache::tags(['product:' . $product->id])->put(
            "product:{$product->id}:full",
            ['cached' => true],
            3600,
        );

        Queue::fake();

        $product->delete();

        $this->assertFalse(
            Cache::tags(['product:' . $product->id])->has("product:{$product->id}:full"),
        );
    }

    // ─── Hierarchy Cache Invalidation ────────────────────────────────

    /** @test */
    public function hierarchy_node_update_invalidates_tree_cache(): void
    {
        $node = HierarchyNode::factory()->create();

        // Baum-Cache setzen
        Cache::tags(['hierarchy:' . $node->hierarchy_id])->put(
            "hierarchy:{$node->hierarchy_id}:tree",
            ['tree' => 'cached'],
            21600,
        );

        $this->assertTrue(
            Cache::tags(['hierarchy:' . $node->hierarchy_id])
                ->has("hierarchy:{$node->hierarchy_id}:tree"),
        );

        Queue::fake();

        $node->update(['name_de' => 'Neuer Name']);

        $this->assertFalse(
            Cache::tags(['hierarchy:' . $node->hierarchy_id])
                ->has("hierarchy:{$node->hierarchy_id}:tree"),
        );
    }

    // ─── Attribute Value Cache Invalidation ──────────────────────────

    /** @test */
    public function attribute_value_change_invalidates_product_cache(): void
    {
        $product = Product::factory()->create([
            'product_type_id' => $this->productType->id,
        ]);

        $attribute = Attribute::factory()->create([
            'technical_name' => 'test_attr',
            'data_type' => 'String',
        ]);

        // Cache setzen
        Cache::tags(['product:' . $product->id])->put(
            "product:{$product->id}:full",
            ['cached' => true],
            3600,
        );

        Queue::fake();

        // Attributwert anlegen → Observer soll Cache flushen
        ProductAttributeValue::factory()->create([
            'product_id' => $product->id,
            'attribute_id' => $attribute->id,
            'value_string' => 'Test',
            'language' => 'de',
            'multiplied_index' => 0,
        ]);

        $this->assertFalse(
            Cache::tags(['product:' . $product->id])->has("product:{$product->id}:full"),
        );
    }

    // ─── Attribute Definition Cache Invalidation ─────────────────────

    /** @test */
    public function attribute_change_invalidates_attributes_all_cache(): void
    {
        // Cache setzen
        Cache::put('attributes:all', ['cached' => true], 3600);

        $this->assertTrue(Cache::has('attributes:all'));

        // Attribut anlegen → Observer soll Cache flushen
        Attribute::factory()->create([
            'technical_name' => 'new_attr',
            'data_type' => 'String',
        ]);

        $this->assertFalse(Cache::has('attributes:all'));
    }

    // ─── Varianten-Kaskade ───────────────────────────────────────────

    /** @test */
    public function variant_cache_invalidated_when_parent_changes(): void
    {
        $parent = Product::factory()->create([
            'product_type_id' => $this->productType->id,
            'product_type_ref' => 'product',
        ]);

        $variant = Product::factory()->create([
            'product_type_id' => $this->productType->id,
            'product_type_ref' => 'variant',
            'parent_product_id' => $parent->id,
        ]);

        // Variante cachen
        Cache::tags(['product:' . $variant->id])->put(
            "product:{$variant->id}:full",
            ['cached' => true],
            3600,
        );

        $this->assertTrue(
            Cache::tags(['product:' . $variant->id])->has("product:{$variant->id}:full"),
        );

        Queue::fake();

        // Elternprodukt aktualisieren → Variante soll auch invalidiert werden
        $parent->update(['name' => 'Geänderter Name']);

        $this->assertFalse(
            Cache::tags(['product:' . $variant->id])->has("product:{$variant->id}:full"),
        );
    }

    // ─── WarmupCache ─────────────────────────────────────────────────

    /** @test */
    public function warmup_cache_loads_products_into_cache(): void
    {
        $products = Product::factory()->count(3)->create([
            'product_type_id' => $this->productType->id,
            'status' => 'active',
        ]);

        $productIds = $products->pluck('id')->toArray();

        Queue::fake();

        // WarmupCache ausführen
        $job = new WarmupCache($productIds);
        $job->handle();

        // Produkte sollten jetzt im Cache sein
        foreach ($productIds as $productId) {
            $this->assertTrue(
                Cache::tags(['product:' . $productId])->has("product:{$productId}:full"),
                "Product {$productId} should be in cache after warmup",
            );
        }
    }

    /** @test */
    public function warmup_cache_dispatches_search_index_updates(): void
    {
        $product = Product::factory()->create([
            'product_type_id' => $this->productType->id,
        ]);

        Queue::fake();

        $job = new WarmupCache([$product->id]);
        $job->handle();

        Queue::assertPushed(UpdateSearchIndex::class, function ($job) use ($product) {
            return $job->productId === $product->id;
        });
    }

    // ─── Cache-Tag Isolation ─────────────────────────────────────────

    /** @test */
    public function flushing_one_product_does_not_affect_other(): void
    {
        $product1 = Product::factory()->create([
            'product_type_id' => $this->productType->id,
        ]);
        $product2 = Product::factory()->create([
            'product_type_id' => $this->productType->id,
        ]);

        // Beide cachen
        Cache::tags(['product:' . $product1->id])->put(
            "product:{$product1->id}:full",
            ['product' => 1],
            3600,
        );
        Cache::tags(['product:' . $product2->id])->put(
            "product:{$product2->id}:full",
            ['product' => 2],
            3600,
        );

        // Nur Produkt 1 flushen
        Cache::tags(['product:' . $product1->id])->flush();

        // Produkt 1 weg, Produkt 2 noch da
        $this->assertFalse(
            Cache::tags(['product:' . $product1->id])->has("product:{$product1->id}:full"),
        );
        $this->assertTrue(
            Cache::tags(['product:' . $product2->id])->has("product:{$product2->id}:full"),
        );
    }
}

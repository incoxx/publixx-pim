<?php

declare(strict_types=1);

namespace Tests\Feature\Products;

use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Setting;
use App\Services\ProductVersioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Vollständiger Snapshot: Inhalt, Restore-Roundtrip, Dedup und Retention.
 */
class ProductVersionSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ProductVersioningService
    {
        return app(ProductVersioningService::class);
    }

    public function test_snapshot_includes_prices_section(): void
    {
        $product = Product::factory()->create();
        $price = ProductPrice::factory()->create([
            'product_id' => $product->id,
            'amount' => 99.90,
            'currency' => 'EUR',
        ]);

        $version = $this->service()->createVersion($product->fresh());

        $this->assertArrayHasKey('prices', $version->snapshot);
        $this->assertNotEmpty($version->snapshot['prices']);

        $raw = collect($version->snapshot['prices'])->first()['raw'];
        $this->assertSame($price->price_type_id, $raw['price_type_id']);
        $this->assertSame('EUR', $raw['currency']);
    }

    public function test_dedup_returns_same_version_when_unchanged(): void
    {
        $product = Product::factory()->create();

        $v1 = $this->service()->createVersion($product->fresh());
        $v2 = $this->service()->createVersion($product->fresh());

        $this->assertSame($v1->id, $v2->id);
        $this->assertSame(1, $product->versions()->count());
    }

    public function test_restore_reinstates_deleted_price(): void
    {
        $product = Product::factory()->create();
        ProductPrice::factory()->create([
            'product_id' => $product->id,
            'amount' => 50,
            'currency' => 'EUR',
        ]);

        $version = $this->service()->createVersion($product->fresh());

        ProductPrice::where('product_id', $product->id)->delete();
        $this->assertSame(0, ProductPrice::where('product_id', $product->id)->count());

        $this->service()->activateVersion($version->fresh());

        $this->assertSame(1, ProductPrice::where('product_id', $product->id)->count());
    }

    public function test_retention_prunes_old_versions(): void
    {
        Setting::setPayload('product_versions', ['max_per_product' => 2]);
        $product = Product::factory()->create();

        for ($i = 1; $i <= 4; $i++) {
            $product->update(['name' => "Name {$i}"]); // Zustandsänderung → neue Version
            $this->service()->createVersion($product->fresh());
        }

        $this->assertLessThanOrEqual(2, $product->versions()->count());
    }
}

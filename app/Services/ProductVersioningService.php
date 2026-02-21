<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVersion;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ProductVersioningService
{
    private const VERSIONED_FIELDS = [
        'name',
        'sku',
        'ean',
        'status',
        'master_hierarchy_node_id',
    ];

    public function createVersion(Product $product, ?string $reason = null, ?string $userId = null): ProductVersion
    {
        $nextNumber = (int) $product->versions()->max('version_number') + 1;

        return ProductVersion::create([
            'product_id' => $product->id,
            'version_number' => $nextNumber,
            'status' => 'draft',
            'snapshot' => $this->createSnapshotFromProduct($product),
            'change_reason' => $reason,
            'created_by' => $userId,
            'created_at' => now(),
        ]);
    }

    public function activateVersion(ProductVersion $version): void
    {
        DB::transaction(function () use ($version) {
            // Archive the current active version
            ProductVersion::where('product_id', $version->product_id)
                ->where('status', 'active')
                ->update(['status' => 'archived']);

            // Apply snapshot to the product
            $product = $version->product;
            $product->update($version->snapshot);

            // Mark this version as active
            $version->update([
                'status' => 'active',
                'published_at' => now(),
            ]);

            // Fire existing event to trigger cache/search invalidation
            try {
                event(new \App\Events\ProductUpdated($product->fresh()));
            } catch (\Throwable) {
                // Don't break the activation
            }
        });
    }

    public function scheduleVersion(ProductVersion $version, Carbon $publishAt): void
    {
        // Cancel any other scheduled version for this product
        ProductVersion::where('product_id', $version->product_id)
            ->where('status', 'scheduled')
            ->where('id', '!=', $version->id)
            ->update(['status' => 'draft', 'publish_at' => null]);

        $version->update([
            'status' => 'scheduled',
            'publish_at' => $publishAt,
        ]);
    }

    public function cancelSchedule(ProductVersion $version): void
    {
        $version->update([
            'status' => 'draft',
            'publish_at' => null,
        ]);
    }

    public function revertToVersion(ProductVersion $version): ProductVersion
    {
        $product = $version->product;

        $newVersion = $this->createVersion(
            $product,
            "Wiederherstellung von Version {$version->version_number}",
            $version->created_by,
        );

        // Overwrite the snapshot with the old version's snapshot
        $newVersion->update(['snapshot' => $version->snapshot]);

        $this->activateVersion($newVersion->fresh());

        return $newVersion->fresh();
    }

    public function compareVersions(ProductVersion $v1, ProductVersion $v2): array
    {
        $snap1 = $v1->snapshot;
        $snap2 = $v2->snapshot;

        $fields = [];
        foreach (self::VERSIONED_FIELDS as $field) {
            $oldValue = $snap1[$field] ?? null;
            $newValue = $snap2[$field] ?? null;

            $fields[] = [
                'field' => $field,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'changed' => $oldValue !== $newValue,
            ];
        }

        return [
            'left' => [
                'version_number' => $v1->version_number,
                'status' => $v1->status,
                'created_at' => $v1->created_at,
                'created_by' => $v1->creator?->name,
                'change_reason' => $v1->change_reason,
            ],
            'right' => [
                'version_number' => $v2->version_number,
                'status' => $v2->status,
                'created_at' => $v2->created_at,
                'created_by' => $v2->creator?->name,
                'change_reason' => $v2->change_reason,
            ],
            'fields' => $fields,
        ];
    }

    private function createSnapshotFromProduct(Product $product): array
    {
        $snapshot = [];
        foreach (self::VERSIONED_FIELDS as $field) {
            $snapshot[$field] = $product->{$field};
        }

        return $snapshot;
    }
}

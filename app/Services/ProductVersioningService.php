<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Models\ProductAttributeValue;
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
        $diff = $this->compareSnapshots($v1->snapshot, $v2->snapshot);
        $diff['left'] = $this->buildVersionMeta($v1);
        $diff['right'] = $this->buildVersionMeta($v2);

        return $diff;
    }

    public function compareWithCurrent(ProductVersion $version, Product $product): array
    {
        $currentSnapshot = $this->createSnapshotFromProduct($product);
        $diff = $this->compareSnapshots($version->snapshot, $currentSnapshot);
        $diff['left'] = $this->buildVersionMeta($version);
        $diff['right'] = [
            'version_number' => null,
            'status' => 'current',
            'created_at' => now(),
            'created_by' => null,
            'change_reason' => null,
            'is_current' => true,
        ];

        return $diff;
    }

    private function compareSnapshots(array $snap1, array $snap2): array
    {
        $fields = [];
        foreach (self::VERSIONED_FIELDS as $field) {
            $oldValue = $snap1[$field] ?? null;
            $newValue = $snap2[$field] ?? null;

            $fields[] = [
                'field' => $field,
                'label' => $field,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'changed' => $oldValue !== $newValue,
                'type' => 'base',
            ];
        }

        // Compare attribute values stored in snapshots
        $oldAttrs = $snap1['attributes'] ?? [];
        $newAttrs = $snap2['attributes'] ?? [];
        $allAttrKeys = array_unique(array_merge(array_keys($oldAttrs), array_keys($newAttrs)));

        foreach ($allAttrKeys as $attrKey) {
            $oldValue = $oldAttrs[$attrKey]['value'] ?? null;
            $newValue = $newAttrs[$attrKey]['value'] ?? null;
            $label = $newAttrs[$attrKey]['label'] ?? $oldAttrs[$attrKey]['label'] ?? $attrKey;

            $fields[] = [
                'field' => "attr:{$attrKey}",
                'label' => $label,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'changed' => $oldValue !== $newValue,
                'type' => 'attribute',
            ];
        }

        return ['fields' => $fields];
    }

    private function buildVersionMeta(ProductVersion $version): array
    {
        return [
            'version_number' => $version->version_number,
            'status' => $version->status,
            'created_at' => $version->created_at,
            'created_by' => $version->creator?->name,
            'change_reason' => $version->change_reason,
        ];
    }

    public function createSnapshotFromProduct(Product $product): array
    {
        $snapshot = [];
        foreach (self::VERSIONED_FIELDS as $field) {
            $snapshot[$field] = $product->{$field};
        }

        // Include attribute values in the snapshot
        $attributeValues = ProductAttributeValue::where('product_id', $product->id)
            ->with(['attribute', 'valueListEntry'])
            ->get();

        $attrs = [];
        foreach ($attributeValues as $av) {
            $key = $av->attribute?->technical_name ?? $av->attribute_id;
            $lang = $av->language ? "_{$av->language}" : '';
            $multiIdx = $av->multiplied_index > 0 ? ":{$av->multiplied_index}" : '';
            $attrKey = "{$key}{$lang}{$multiIdx}";

            $value = $this->extractAttributeValue($av);

            $attrs[$attrKey] = [
                'label' => $av->attribute?->name_de ?? $key,
                'value' => $value,
            ];
        }
        $snapshot['attributes'] = $attrs;

        return $snapshot;
    }

    private function extractAttributeValue(ProductAttributeValue $av): mixed
    {
        $dataType = $av->attribute?->data_type;

        return match ($dataType) {
            'String' => $av->value_string,
            'Number', 'Float' => $av->value_number !== null
                ? rtrim(rtrim((string) $av->value_number, '0'), '.')
                : null,
            'Date' => $av->value_date?->format('Y-m-d'),
            'Flag' => $av->value_flag !== null
                ? ($av->value_flag ? 'Ja' : 'Nein')
                : null,
            'Selection', 'Dictionary' => $this->resolveSelectionLabel($av),
            default => $av->value_string
                ?? ($av->value_number !== null ? rtrim(rtrim((string) $av->value_number, '0'), '.') : null)
                ?? $av->value_date?->format('Y-m-d')
                ?? ($av->value_flag !== null ? ($av->value_flag ? 'Ja' : 'Nein') : null)
                ?? $this->resolveSelectionLabel($av),
        };
    }

    private function resolveSelectionLabel(ProductAttributeValue $av): ?string
    {
        $entry = $av->valueListEntry;
        if (!$entry) {
            return null;
        }

        return $entry->display_value_de;
    }
}

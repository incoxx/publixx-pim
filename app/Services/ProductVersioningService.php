<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductVersion;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProductVersioningService
{
    private const VERSIONED_FIELDS = [
        'name',
        'sku',
        'ean',
        'status',
        'master_hierarchy_node_id',
        'manufacturer_id',
        'workflow_status',
        'workflow_assignee_id',
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
            $snapshot = $version->snapshot;
            $baseFields = array_intersect_key($snapshot, array_flip(self::VERSIONED_FIELDS));
            $product->update($baseFields);

            // Restore attribute values from raw snapshot data
            $this->restoreAttributeValues($product, $snapshot);

            // Mark this version as active
            $version->update([
                'status' => 'active',
                'published_at' => now(),
            ]);

            // Fire existing event to trigger cache/search invalidation
            try {
                event(new \App\Events\ProductUpdated($product->fresh()));
            } catch (\Throwable $e) {
                Log::warning('ProductUpdated event failed during version activation', [
                    'product_id' => $product->id,
                    'version_id' => $version->id,
                    'error' => $e->getMessage(),
                ]);
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

    public function revertToVersion(ProductVersion $version, ?string $currentUserId = null): ProductVersion
    {
        $product = $version->product;

        $newVersion = $this->createVersion(
            $product,
            "Wiederherstellung von Version {$version->version_number}",
            $currentUserId ?? auth()->id(),
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
        try {
            $attributeValues = ProductAttributeValue::where('product_id', $product->id)
                ->with(['attribute', 'valueListEntry'])
                ->get();
        } catch (\Throwable $e) {
            Log::error('Failed to load attribute values for snapshot', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);
            $snapshot['attributes'] = [];
            return $snapshot;
        }

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
                'raw' => [
                    'attribute_id' => $av->attribute_id,
                    'value_string' => $av->value_string,
                    'value_number' => $av->value_number !== null ? (string) $av->value_number : null,
                    'value_date' => $av->value_date?->format('Y-m-d'),
                    'value_flag' => $av->value_flag,
                    'value_selection_id' => $av->value_selection_id,
                    'unit_id' => $av->unit_id,
                    'comparison_operator_id' => $av->comparison_operator_id,
                    'language' => $av->language,
                    'multiplied_index' => $av->multiplied_index ?? 0,
                    'output_hierarchy_id' => $av->output_hierarchy_id,
                ],
            ];
        }
        $snapshot['attributes'] = $attrs;

        return $snapshot;
    }

    private function restoreAttributeValues(Product $product, array $snapshot): void
    {
        $attributes = $snapshot['attributes'] ?? [];

        // Check if at least one entry has raw data (new format)
        $hasRawData = collect($attributes)->contains(fn ($attr) => isset($attr['raw']));
        if (! $hasRawData) {
            return; // Old snapshot without raw data — skip attribute restore
        }

        // Delete current attribute values (master + channel-specific)
        ProductAttributeValue::where('product_id', $product->id)->delete();

        // Rebuild attribute values from raw snapshot data
        $rows = [];
        $changedAttributeIds = [];

        foreach ($attributes as $attr) {
            if (! isset($attr['raw'])) {
                continue;
            }

            $raw = $attr['raw'];
            $changedAttributeIds[] = $raw['attribute_id'];

            $rows[] = [
                'id' => (string) Str::uuid(),
                'product_id' => $product->id,
                'attribute_id' => $raw['attribute_id'],
                'value_string' => $raw['value_string'],
                'value_number' => $raw['value_number'],
                'value_date' => $raw['value_date'],
                'value_flag' => $raw['value_flag'],
                'value_selection_id' => $raw['value_selection_id'],
                'unit_id' => $raw['unit_id'],
                'comparison_operator_id' => $raw['comparison_operator_id'],
                'language' => $raw['language'],
                'multiplied_index' => $raw['multiplied_index'] ?? 0,
                'is_inherited' => false,
                'inherited_from_node_id' => null,
                'inherited_from_product_id' => null,
                'output_hierarchy_id' => $raw['output_hierarchy_id'] ?? null,
            ];
        }

        // Bulk insert in chunks
        foreach (array_chunk($rows, 500) as $chunk) {
            ProductAttributeValue::insert($chunk);
        }

        // Fire event so search index / cache gets updated
        try {
            $uniqueIds = array_unique($changedAttributeIds);
            event(new \App\Events\AttributeValuesChanged($product->id, $uniqueIds));
        } catch (\Throwable $e) {
            Log::warning('AttributeValuesChanged event failed during version restore', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);
        }
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
            'Selection' => $this->resolveSelectionLabel($av),
            'Dictionary' => $this->resolveDictionaryLabel($av),
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

    private function resolveDictionaryLabel(ProductAttributeValue $av): ?string
    {
        $entry = $av->dictionaryEntry;
        if (!$entry) {
            return null;
        }

        return $entry->short_text_de;
    }
}

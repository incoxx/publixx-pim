<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\HierarchyNode;
use App\Models\Media;
use App\Models\OutputHierarchyProductAssignment;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductMediaAssignment;
use App\Models\ProductPrice;
use App\Models\ProductRelation;
use App\Models\ProductRelationAttributeValue;
use App\Models\ProductVersion;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProductVersioningService
{
    /** Fallback-Limit, falls keine Einstellung/Config gesetzt ist. */
    public const DEFAULT_MAX_VERSIONS = 50;

    public function __construct(
        private readonly ProductSnapshotSerializer $serializer,
    ) {
    }

    // --- Erstellen / Dedup / Retention ---------------------------------------

    public function createVersion(Product $product, ?string $reason = null, ?string $userId = null): ProductVersion
    {
        $snapshot = $this->serializer->snapshot($product);

        // Dedup: Wenn sich gegenüber der letzten Version nichts geändert hat,
        // keine neue Version anlegen (verhindert Versions-Flut bei No-op-Speichern).
        $latest = $product->versions()->orderByDesc('version_number')->first();
        if ($latest && $this->serializer->equals($latest->snapshot ?? [], $snapshot)) {
            return $latest;
        }

        return $this->persistVersion($product, $snapshot, $reason, $userId);
    }

    private function persistVersion(Product $product, array $snapshot, ?string $reason, ?string $userId): ProductVersion
    {
        $nextNumber = (int) $product->versions()->max('version_number') + 1;

        $version = ProductVersion::create([
            'product_id' => $product->id,
            'version_number' => $nextNumber,
            'status' => 'draft',
            'snapshot' => $snapshot,
            'change_reason' => $reason,
            'created_by' => $userId,
            'created_at' => now(),
        ]);

        $this->pruneVersions($product);

        return $version;
    }

    /**
     * Alte Versionen über dem konfigurierten Limit entfernen.
     * Die neuesten N Versionen bleiben erhalten; aktive und geplante Versionen
     * werden nie gelöscht. Limit <= 0 bedeutet "unbegrenzt".
     */
    private function pruneVersions(Product $product): void
    {
        $max = $this->maxVersionsPerProduct();
        if ($max <= 0) {
            return;
        }

        if ($product->versions()->count() <= $max) {
            return;
        }

        $keepIds = $product->versions()
            ->orderByDesc('version_number')
            ->limit($max)
            ->pluck('id');

        $product->versions()
            ->whereNotIn('id', $keepIds)
            ->whereNotIn('status', ['active', 'scheduled'])
            ->delete();
    }

    public function maxVersionsPerProduct(): int
    {
        $payload = Setting::getPayload('product_versions');
        $value = $payload['max_per_product']
            ?? config('pim.versions.max_per_product', self::DEFAULT_MAX_VERSIONS);

        return (int) $value;
    }

    // --- Aktivieren / Planen / Wiederherstellen ------------------------------

    public function activateVersion(ProductVersion $version): void
    {
        DB::transaction(function () use ($version) {
            // Aktuell aktive Version archivieren
            ProductVersion::where('product_id', $version->product_id)
                ->where('status', 'active')
                ->update(['status' => 'archived']);

            $product = $version->product;
            $snapshot = $version->snapshot ?? [];

            // Basisfelder anwenden
            $baseFields = array_intersect_key($snapshot, array_flip(ProductSnapshotSerializer::BASE_FIELDS));
            $product->update($baseFields);

            // Alle Sektionen aus dem Snapshot wiederherstellen
            $this->restoreSnapshot($product, $snapshot);

            $version->update([
                'status' => 'active',
                'published_at' => now(),
            ]);

            // Cache/Suchindex aktualisieren
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

        // Neue Version aus dem alten Snapshot anlegen (kein Dedup – die
        // Wiederherstellung soll immer als eigener Verlaufseintrag erscheinen).
        $newVersion = $this->persistVersion(
            $product,
            $version->snapshot ?? [],
            "Wiederherstellung von Version {$version->version_number}",
            $currentUserId ?? auth()->id(),
        );

        $this->activateVersion($newVersion->fresh());

        return $newVersion->fresh();
    }

    // --- Restore je Sektion ---------------------------------------------------

    private function restoreSnapshot(Product $product, array $snapshot): void
    {
        $this->restoreAttributeValues($product, $snapshot);
        $this->restorePrices($product, $snapshot);
        $this->restoreMedia($product, $snapshot);
        $this->restoreRelations($product, $snapshot);
        $this->restoreOutputHierarchies($product, $snapshot);
    }

    private function restoreAttributeValues(Product $product, array $snapshot): void
    {
        $attributes = $snapshot['attributes'] ?? [];

        ProductAttributeValue::where('product_id', $product->id)->delete();

        $rows = [];
        $changedAttributeIds = [];

        foreach ($attributes as $attr) {
            $raw = $attr['raw'] ?? null;
            if (! $raw) {
                continue;
            }

            $changedAttributeIds[] = $raw['attribute_id'];
            $rows[] = [
                'id' => (string) Str::uuid(),
                'product_id' => $product->id,
                'attribute_id' => $raw['attribute_id'],
                'value_string' => $raw['value_string'] ?? null,
                'value_number' => $raw['value_number'] ?? null,
                'value_date' => $raw['value_date'] ?? null,
                'value_flag' => $raw['value_flag'] ?? null,
                'value_selection_id' => $raw['value_selection_id'] ?? null,
                'unit_id' => $raw['unit_id'] ?? null,
                'comparison_operator_id' => $raw['comparison_operator_id'] ?? null,
                'language' => $raw['language'] ?? null,
                'multiplied_index' => $raw['multiplied_index'] ?? 0,
                'is_inherited' => false,
                'inherited_from_node_id' => null,
                'inherited_from_product_id' => null,
                'output_hierarchy_id' => $raw['output_hierarchy_id'] ?? null,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            ProductAttributeValue::insert($chunk);
        }

        try {
            event(new \App\Events\AttributeValuesChanged($product->id, array_unique($changedAttributeIds)));
        } catch (\Throwable $e) {
            Log::warning('AttributeValuesChanged event failed during version restore', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function restorePrices(Product $product, array $snapshot): void
    {
        ProductPrice::where('product_id', $product->id)->delete();

        foreach ($snapshot['prices'] ?? [] as $item) {
            $raw = $item['raw'] ?? null;
            if (! $raw) {
                continue;
            }

            ProductPrice::create([
                'product_id' => $product->id,
                'price_type_id' => $raw['price_type_id'],
                'amount' => $raw['amount'],
                'currency' => $raw['currency'],
                'valid_from' => $raw['valid_from'] ?? null,
                'valid_to' => $raw['valid_to'] ?? null,
                'price_region_id' => $raw['price_region_id'] ?? null,
                'scale_from' => $raw['scale_from'] ?? null,
                'scale_to' => $raw['scale_to'] ?? null,
            ]);
        }
    }

    private function restoreMedia(Product $product, array $snapshot): void
    {
        ProductMediaAssignment::where('product_id', $product->id)->delete();

        foreach ($snapshot['media'] ?? [] as $item) {
            $raw = $item['raw'] ?? null;
            if (! $raw) {
                continue;
            }

            // Fehlendes Medium tolerieren (kann zwischenzeitlich gelöscht sein)
            if (! Media::whereKey($raw['media_id'])->exists()) {
                Log::warning('Version restore: media missing, assignment skipped', [
                    'product_id' => $product->id,
                    'media_id' => $raw['media_id'],
                ]);
                continue;
            }

            ProductMediaAssignment::create([
                'product_id' => $product->id,
                'media_id' => $raw['media_id'],
                'motif_id' => $raw['motif_id'] ?? null,
                'usage_type_id' => $raw['usage_type_id'] ?? null,
                'sort_order' => $raw['sort_order'] ?? 0,
                'is_primary' => $raw['is_primary'] ?? false,
                'inherited_from_virtual_product_id' => $raw['inherited_from_virtual_product_id'] ?? null,
            ]);
        }
    }

    private function restoreRelations(Product $product, array $snapshot): void
    {
        // Löschen kaskadiert auf ProductRelationAttributeValue (FK ON DELETE CASCADE)
        ProductRelation::where('source_product_id', $product->id)->delete();

        foreach ($snapshot['relations'] ?? [] as $item) {
            $raw = $item['raw'] ?? null;
            if (! $raw) {
                continue;
            }

            // Fehlendes Zielprodukt tolerieren
            if (! Product::whereKey($raw['target_product_id'])->exists()) {
                Log::warning('Version restore: relation target missing, skipped', [
                    'product_id' => $product->id,
                    'target_product_id' => $raw['target_product_id'],
                ]);
                continue;
            }

            $relation = ProductRelation::create([
                'source_product_id' => $product->id,
                'target_product_id' => $raw['target_product_id'],
                'relation_type_id' => $raw['relation_type_id'],
                'sort_order' => $raw['sort_order'] ?? 0,
            ]);

            foreach ($raw['attributes'] ?? [] as $ra) {
                ProductRelationAttributeValue::create([
                    'product_relation_id' => $relation->id,
                    'attribute_id' => $ra['attribute_id'],
                    'value_string' => $ra['value_string'] ?? null,
                    'value_number' => $ra['value_number'] ?? null,
                    'value_date' => $ra['value_date'] ?? null,
                    'value_flag' => $ra['value_flag'] ?? null,
                    'value_selection_id' => $ra['value_selection_id'] ?? null,
                    'unit_id' => $ra['unit_id'] ?? null,
                    'language' => $ra['language'] ?? null,
                    'multiplied_index' => $ra['multiplied_index'] ?? 0,
                ]);
            }
        }
    }

    private function restoreOutputHierarchies(Product $product, array $snapshot): void
    {
        OutputHierarchyProductAssignment::where('product_id', $product->id)->delete();

        foreach ($snapshot['output_hierarchies'] ?? [] as $item) {
            $raw = $item['raw'] ?? null;
            if (! $raw) {
                continue;
            }

            if (! HierarchyNode::whereKey($raw['hierarchy_node_id'])->exists()) {
                Log::warning('Version restore: hierarchy node missing, assignment skipped', [
                    'product_id' => $product->id,
                    'hierarchy_node_id' => $raw['hierarchy_node_id'],
                ]);
                continue;
            }

            OutputHierarchyProductAssignment::create([
                'product_id' => $product->id,
                'hierarchy_node_id' => $raw['hierarchy_node_id'],
                'sort_order' => $raw['sort_order'] ?? 0,
            ]);
        }
    }

    // --- Vergleich ------------------------------------------------------------

    public function compareVersions(ProductVersion $v1, ProductVersion $v2): array
    {
        $diff = $this->serializer->compare($v1->snapshot ?? [], $v2->snapshot ?? []);
        $diff['left'] = $this->buildVersionMeta($v1);
        $diff['right'] = $this->buildVersionMeta($v2);

        return $diff;
    }

    public function compareWithCurrent(ProductVersion $version, Product $product): array
    {
        $currentSnapshot = $this->serializer->snapshot($product);
        $diff = $this->serializer->compare($version->snapshot ?? [], $currentSnapshot);
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
}

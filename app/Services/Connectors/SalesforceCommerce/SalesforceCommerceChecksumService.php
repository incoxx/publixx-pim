<?php

declare(strict_types=1);

namespace App\Services\Connectors\SalesforceCommerce;

use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductPrice;

/**
 * Berechnet deterministische Checksums fuer Produkt-Daten,
 * um Delta-Sync-Vergleiche zu ermoeglichen.
 */
class SalesforceCommerceChecksumService
{
    /**
     * Berechnet die Checksum fuer ein Produkt basierend auf den Sync-relevanten Daten.
     */
    public function computeChecksum(
        Product $product,
        array $profilePayload,
        array $sfccFields,
        string $language,
        ?array $allowedAttributeIds = null,
        array $customAttributeIds = [],
    ): string {
        $data = [];

        // 1. Basisdaten
        $data['base'] = [
            'sku'  => $product->sku,
            'name' => $product->name,
            'ean'  => $product->ean,
        ];

        // 2. SFCC-Feld-Konfiguration (damit Mapping-Aenderungen erkannt werden)
        $data['sfcc_fields'] = $this->normalizeSfccFields($sfccFields);

        // 3. Attributwerte
        $data['attributes'] = $this->collectAttributeFingerprint($product, $language, $allowedAttributeIds);

        // 4. Custom-Attribute
        if (!empty($customAttributeIds)) {
            $data['custom_attributes'] = $this->collectCustomAttributeFingerprint($product, $customAttributeIds);
        }

        // 5. Preise
        $data['prices'] = $this->collectPriceFingerprint($product, $profilePayload);

        // 6. Medien (Dateinamen + updated_at)
        $data['media'] = $this->collectMediaFingerprint($product);

        // 7. Kategorie-Zuordnung
        $data['category_node'] = $product->master_hierarchy_node_id;

        ksort($data);
        return hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Berechnet Checksums fuer mehrere Produkte in einem Batch (Performance-optimiert).
     *
     * @param  \Illuminate\Support\Collection<Product>  $products
     * @return array<string, string>  Map: product_id => checksum
     */
    public function computeChecksumsBatch(
        $products,
        array $profilePayload,
        array $sfccFields,
        string $language,
        ?array $allowedAttributeIds = null,
        array $customAttributeIds = [],
    ): array {
        $productIds = $products->pluck('id')->toArray();

        // Alle Attributwerte fuer den Batch vorladen
        $allAttributeValues = $this->batchLoadAttributeValues($productIds, $allowedAttributeIds);
        $allCustomValues = !empty($customAttributeIds)
            ? $this->batchLoadCustomValues($productIds, $customAttributeIds)
            : collect();

        $checksums = [];
        $normalizedFields = $this->normalizeSfccFields($sfccFields);

        foreach ($products as $product) {
            $data = [];

            $data['base'] = [
                'sku'  => $product->sku,
                'name' => $product->name,
                'ean'  => $product->ean,
            ];

            $data['sfcc_fields'] = $normalizedFields;

            $productAttrs = $allAttributeValues->get($product->id, collect());
            $data['attributes'] = $productAttrs->map(fn ($av) => [
                'attr' => $av->attribute_id,
                'val'  => $av->value_string ?? $av->value_number ?? $av->value_date?->toDateString() ?? $av->value_flag ?? $av->value_selection_id,
            ])->sortBy('attr')->values()->toArray();

            if (!empty($customAttributeIds)) {
                $productCustom = $allCustomValues->get($product->id, collect());
                $data['custom_attributes'] = $productCustom->map(fn ($av) => [
                    'attr'  => $av->attribute_id,
                    'val'   => $av->value_string ?? $av->value_number ?? $av->value_selection_id,
                ])->sortBy('attr')->values()->toArray();
            }

            $data['prices'] = $this->collectPriceFingerprint($product, $profilePayload);
            $data['media'] = $this->collectMediaFingerprint($product);
            $data['category_node'] = $product->master_hierarchy_node_id;

            ksort($data);
            $checksums[$product->id] = hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE));
        }

        return $checksums;
    }

    private function normalizeSfccFields(array $sfccFields): array
    {
        $excluded = ['_sync_media', '_image_view_types'];
        $normalized = array_diff_key($sfccFields, array_flip($excluded));
        ksort($normalized);

        return $normalized;
    }

    private function collectAttributeFingerprint(Product $product, string $language, ?array $allowedAttributeIds): array
    {
        $query = ProductAttributeValue::where('product_id', $product->id)
            ->select(['attribute_id', 'value_string', 'value_number', 'value_date', 'value_flag', 'value_selection_id']);

        if ($allowedAttributeIds !== null) {
            $query->whereIn('attribute_id', $allowedAttributeIds);
        }

        return $query->orderBy('attribute_id')
            ->get()
            ->map(fn ($av) => [
                'attr' => $av->attribute_id,
                'val'  => $av->value_string ?? $av->value_number ?? $av->value_date?->toDateString() ?? $av->value_flag ?? $av->value_selection_id,
            ])
            ->toArray();
    }

    private function collectCustomAttributeFingerprint(Product $product, array $customAttributeIds): array
    {
        return ProductAttributeValue::where('product_id', $product->id)
            ->whereIn('attribute_id', $customAttributeIds)
            ->orderBy('attribute_id')
            ->get()
            ->map(fn ($av) => [
                'attr' => $av->attribute_id,
                'val'  => $av->value_string ?? $av->value_number ?? $av->value_selection_id,
            ])
            ->toArray();
    }

    private function collectPriceFingerprint(Product $product, array $profilePayload): array
    {
        $priceTypeId = $profilePayload['card_price_type_id'] ?? null;

        $query = ProductPrice::where('product_id', $product->id)
            ->select(['price_type_id', 'amount', 'currency', 'price_region_id'])
            ->orderBy('price_type_id');

        if ($priceTypeId) {
            $query->where('price_type_id', $priceTypeId);
        }

        return $query->get()->map(fn ($p) => [
            'type'     => $p->price_type_id,
            'amount'   => (string) $p->amount,
            'currency' => $p->currency,
            'region'   => $p->price_region_id,
        ])->toArray();
    }

    private function collectMediaFingerprint(Product $product): array
    {
        $product->loadMissing('media');

        return $product->media
            ->sortBy('id')
            ->map(fn ($m) => [
                'id'         => $m->id,
                'filename'   => $m->file_name ?? $m->original_file_name,
                'updated_at' => $m->updated_at?->toIso8601String(),
            ])
            ->values()
            ->toArray();
    }

    private function batchLoadAttributeValues(array $productIds, ?array $allowedAttributeIds): \Illuminate\Support\Collection
    {
        $query = ProductAttributeValue::whereIn('product_id', $productIds)
            ->select(['product_id', 'attribute_id', 'value_string', 'value_number', 'value_date', 'value_flag', 'value_selection_id']);

        if ($allowedAttributeIds !== null) {
            $query->whereIn('attribute_id', $allowedAttributeIds);
        }

        return $query->get()->groupBy('product_id');
    }

    private function batchLoadCustomValues(array $productIds, array $customAttributeIds): \Illuminate\Support\Collection
    {
        return ProductAttributeValue::whereIn('product_id', $productIds)
            ->whereIn('attribute_id', $customAttributeIds)
            ->select(['product_id', 'attribute_id', 'value_string', 'value_number', 'value_selection_id'])
            ->get()
            ->groupBy('product_id');
    }
}

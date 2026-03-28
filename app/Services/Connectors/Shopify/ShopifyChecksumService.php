<?php

declare(strict_types=1);

namespace App\Services\Connectors\Shopify;

use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductPrice;

/**
 * Berechnet deterministische Checksums für Produkt-Daten,
 * um Delta-Sync-Vergleiche zu ermöglichen.
 *
 * Die Checksum basiert auf allen sync-relevanten Daten:
 * Basisdaten, Attribute, Preise, Medien, Metafields, Kategorie.
 */
class ShopifyChecksumService
{
    /**
     * Berechnet die Checksum für ein Produkt basierend auf den Sync-relevanten Daten.
     *
     * @param  array<string>|null  $allowedAttributeIds  Erlaubte Attribut-IDs aus dem Profil
     * @param  array<string>  $metafieldAttributeIds  Selection-Attribut-IDs für Metafields
     */
    public function computeChecksum(
        Product $product,
        array $profilePayload,
        array $shopifyFields,
        string $language,
        ?array $allowedAttributeIds = null,
        array $metafieldAttributeIds = [],
    ): string {
        $data = [];

        // 1. Basisdaten
        $data['base'] = [
            'sku'  => $product->sku,
            'name' => $product->name,
            'ean'  => $product->ean,
        ];

        // 2. Shopify-Feld-Mapping-Konfiguration (damit Mapping-Änderungen erkannt werden)
        $data['shopify_fields'] = $this->normalizeShopifyFields($shopifyFields);

        // 3. Attributwerte
        $data['attributes'] = $this->collectAttributeFingerprint($product, $language, $allowedAttributeIds);

        // 4. Metafield-Attribute (Selection-Attributwerte)
        if (!empty($metafieldAttributeIds)) {
            $data['metafields'] = $this->collectMetafieldFingerprint($product, $metafieldAttributeIds);
        }

        // 5. Preise
        $data['prices'] = $this->collectPriceFingerprint($product, $profilePayload, $shopifyFields);

        // 6. Medien (Dateinamen + updated_at als Fingerprint)
        $data['media'] = $this->collectMediaFingerprint($product);

        // 7. Kategorie-Zuordnung
        $data['category_node'] = $product->master_hierarchy_node_id;

        // Deterministisch sortieren und hashen
        ksort($data);
        return hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Berechnet Checksums für mehrere Produkte in einem Batch (Performance-optimiert).
     *
     * @param  \Illuminate\Support\Collection<Product>  $products
     * @return array<string, string>  Map: product_id → checksum
     */
    public function computeChecksumsBatch(
        $products,
        array $profilePayload,
        array $shopifyFields,
        string $language,
        ?array $allowedAttributeIds = null,
        array $metafieldAttributeIds = [],
    ): array {
        $productIds = $products->pluck('id')->toArray();

        // Alle Attributwerte für den Batch vorladen
        $allAttributeValues = $this->batchLoadAttributeValues($productIds, $allowedAttributeIds);
        $allMetafieldValues = !empty($metafieldAttributeIds)
            ? $this->batchLoadMetafieldValues($productIds, $metafieldAttributeIds)
            : collect();

        $checksums = [];
        $normalizedFields = $this->normalizeShopifyFields($shopifyFields);

        foreach ($products as $product) {
            $data = [];

            $data['base'] = [
                'sku'  => $product->sku,
                'name' => $product->name,
                'ean'  => $product->ean,
            ];

            $data['shopify_fields'] = $normalizedFields;

            // Attributwerte aus vorgeladenen Daten
            $productAttrs = $allAttributeValues->get($product->id, collect());
            $data['attributes'] = $productAttrs->map(fn ($av) => [
                'attr' => $av->attribute_id,
                'val'  => $av->value_string ?? $av->value_number ?? $av->value_date?->toDateString() ?? $av->value_flag ?? $av->value_selection_id,
            ])->sortBy('attr')->values()->toArray();

            // Metafield-Werte aus vorgeladenen Daten
            if (!empty($metafieldAttributeIds)) {
                $productMeta = $allMetafieldValues->get($product->id, collect());
                $data['metafields'] = $productMeta->map(fn ($av) => [
                    'attr'  => $av->attribute_id,
                    'entry' => $av->value_selection_id,
                ])->sortBy('attr')->values()->toArray();
            }

            $data['prices'] = $this->collectPriceFingerprint($product, $profilePayload, $shopifyFields);
            $data['media'] = $this->collectMediaFingerprint($product);
            $data['category_node'] = $product->master_hierarchy_node_id;

            ksort($data);
            $checksums[$product->id] = hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE));
        }

        return $checksums;
    }

    /**
     * Normalisiert die Shopify-Feld-Konfiguration für den Checksum-Vergleich.
     */
    private function normalizeShopifyFields(array $shopifyFields): array
    {
        // Interne Keys entfernen die keinen Einfluss auf den Produkt-Payload haben
        $excluded = ['_sync_media'];
        $normalized = array_diff_key($shopifyFields, array_flip($excluded));
        ksort($normalized);

        return $normalized;
    }

    /**
     * Sammelt einen Fingerprint aller Attributwerte eines Produkts.
     */
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

    /**
     * Sammelt einen Fingerprint der Metafield-Zuweisungen (Selection-Attribute).
     */
    private function collectMetafieldFingerprint(Product $product, array $metafieldAttributeIds): array
    {
        return ProductAttributeValue::where('product_id', $product->id)
            ->whereIn('attribute_id', $metafieldAttributeIds)
            ->whereNotNull('value_selection_id')
            ->orderBy('attribute_id')
            ->pluck('value_selection_id', 'attribute_id')
            ->toArray();
    }

    /**
     * Sammelt einen Fingerprint der Preise.
     */
    private function collectPriceFingerprint(Product $product, array $profilePayload, array $shopifyFields): array
    {
        $priceTypeId = $profilePayload['card_price_type_id'] ?? null;

        $query = ProductPrice::where('product_id', $product->id)
            ->select(['price_type_id', 'amount', 'currency', 'price_region_id', 'valid_from', 'valid_to'])
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

    /**
     * Sammelt einen Fingerprint der Medien (Dateinamen + Änderungsdatum).
     */
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

    /**
     * Lädt Attributwerte für mehrere Produkte in einem Query (Batch).
     */
    private function batchLoadAttributeValues(array $productIds, ?array $allowedAttributeIds): \Illuminate\Support\Collection
    {
        $query = ProductAttributeValue::whereIn('product_id', $productIds)
            ->select(['product_id', 'attribute_id', 'value_string', 'value_number', 'value_date', 'value_flag', 'value_selection_id']);

        if ($allowedAttributeIds !== null) {
            $query->whereIn('attribute_id', $allowedAttributeIds);
        }

        return $query->get()->groupBy('product_id');
    }

    /**
     * Lädt Metafield-Werte (Selection) für mehrere Produkte in einem Query (Batch).
     */
    private function batchLoadMetafieldValues(array $productIds, array $metafieldAttributeIds): \Illuminate\Support\Collection
    {
        return ProductAttributeValue::whereIn('product_id', $productIds)
            ->whereIn('attribute_id', $metafieldAttributeIds)
            ->whereNotNull('value_selection_id')
            ->select(['product_id', 'attribute_id', 'value_selection_id'])
            ->get()
            ->groupBy('product_id');
    }
}

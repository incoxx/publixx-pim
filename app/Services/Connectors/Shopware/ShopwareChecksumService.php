<?php

declare(strict_types=1);

namespace App\Services\Connectors\Shopware;

use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductPrice;

/**
 * Berechnet deterministische Checksums für Produkt-Daten,
 * um Delta-Sync-Vergleiche zu ermöglichen.
 *
 * Die Checksum basiert auf allen sync-relevanten Daten:
 * Basisdaten, Attribute, Preise, Medien, Properties, Kategorie.
 */
class ShopwareChecksumService
{
    /**
     * Berechnet die Checksum für ein Produkt basierend auf den Sync-relevanten Daten.
     *
     * @param  array<string>  $allowedAttributeIds  Erlaubte Attribut-IDs aus dem Profil
     * @param  array<string>  $propertyAttributeIds  Selection-Attribut-IDs für Properties
     */
    public function computeChecksum(
        Product $product,
        array $profilePayload,
        array $shopwareFields,
        string $language,
        ?array $allowedAttributeIds = null,
        array $propertyAttributeIds = [],
    ): string {
        $data = [];

        // 1. Basisdaten
        $data['base'] = [
            'sku'  => $product->sku,
            'name' => $product->name,
            'ean'  => $product->ean,
        ];

        // 2. Shopware-Feld-Mapping-Konfiguration (damit Mapping-Änderungen erkannt werden)
        $data['shopware_fields'] = $this->normalizeShopwareFields($shopwareFields);

        // 3. Attributwerte (customFields)
        $data['attributes'] = $this->collectAttributeFingerprint($product, $language, $allowedAttributeIds);

        // 4. Properties (Selection-Attributwerte)
        if (!empty($propertyAttributeIds)) {
            $data['properties'] = $this->collectPropertyFingerprint($product, $propertyAttributeIds);
        }

        // 5. Preise
        $data['prices'] = $this->collectPriceFingerprint($product, $profilePayload, $shopwareFields);

        // 6. Medien (Dateinamen + updated_at als Fingerprint)
        $data['media'] = $this->collectMediaFingerprint($product);

        // 7. Kategorie-Zuordnung
        $data['category_node'] = $product->master_hierarchy_node_id;

        // Deterministisch sortieren und hashen
        return hash('sha256', json_encode($data, JSON_SORT_KEYS | JSON_UNESCAPED_UNICODE));
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
        array $shopwareFields,
        string $language,
        ?array $allowedAttributeIds = null,
        array $propertyAttributeIds = [],
    ): array {
        $productIds = $products->pluck('id')->toArray();

        // Alle Attributwerte für den Batch vorladen
        $allAttributeValues = $this->batchLoadAttributeValues($productIds, $allowedAttributeIds);
        $allPropertyValues = !empty($propertyAttributeIds)
            ? $this->batchLoadPropertyValues($productIds, $propertyAttributeIds)
            : collect();

        $checksums = [];
        $normalizedFields = $this->normalizeShopwareFields($shopwareFields);

        foreach ($products as $product) {
            $data = [];

            $data['base'] = [
                'sku'  => $product->sku,
                'name' => $product->name,
                'ean'  => $product->ean,
            ];

            $data['shopware_fields'] = $normalizedFields;

            // Attributwerte aus vorgeladenen Daten
            $productAttrs = $allAttributeValues->get($product->id, collect());
            $data['attributes'] = $productAttrs->map(fn ($av) => [
                'attr' => $av->attribute_id,
                'val'  => $av->value_string ?? $av->value_number ?? $av->value_date?->toDateString() ?? $av->value_flag ?? $av->value_selection_id,
            ])->sortBy('attr')->values()->toArray();

            // Properties aus vorgeladenen Daten
            if (!empty($propertyAttributeIds)) {
                $productProps = $allPropertyValues->get($product->id, collect());
                $data['properties'] = $productProps->map(fn ($av) => [
                    'attr'  => $av->attribute_id,
                    'entry' => $av->value_selection_id,
                ])->sortBy('attr')->values()->toArray();
            }

            $data['prices'] = $this->collectPriceFingerprint($product, $profilePayload, $shopwareFields);
            $data['media'] = $this->collectMediaFingerprint($product);
            $data['category_node'] = $product->master_hierarchy_node_id;

            $checksums[$product->id] = hash('sha256', json_encode($data, JSON_SORT_KEYS | JSON_UNESCAPED_UNICODE));
        }

        return $checksums;
    }

    /**
     * Normalisiert die Shopware-Feld-Konfiguration für den Checksum-Vergleich.
     */
    private function normalizeShopwareFields(array $shopwareFields): array
    {
        // Interne Keys entfernen die keinen Einfluss auf den Produkt-Payload haben
        $excluded = ['_sales_channel_id'];
        $normalized = array_diff_key($shopwareFields, array_flip($excluded));
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
     * Sammelt einen Fingerprint der Property-Zuweisungen (Selection-Attribute).
     */
    private function collectPropertyFingerprint(Product $product, array $propertyAttributeIds): array
    {
        return ProductAttributeValue::where('product_id', $product->id)
            ->whereIn('attribute_id', $propertyAttributeIds)
            ->whereNotNull('value_selection_id')
            ->orderBy('attribute_id')
            ->pluck('value_selection_id', 'attribute_id')
            ->toArray();
    }

    /**
     * Sammelt einen Fingerprint der Preise.
     */
    private function collectPriceFingerprint(Product $product, array $profilePayload, array $shopwareFields): array
    {
        $priceTypeId = $profilePayload['card_price_type_id'] ?? null;
        $priceCountry = $profilePayload['card_price_country'] ?? null;

        $query = ProductPrice::where('product_id', $product->id)
            ->select(['price_type_id', 'amount', 'currency', 'country', 'valid_from', 'valid_to'])
            ->orderBy('price_type_id');

        if ($priceTypeId) {
            $query->where('price_type_id', $priceTypeId);
        }

        return $query->get()->map(fn ($p) => [
            'type'     => $p->price_type_id,
            'amount'   => (string) $p->amount,
            'currency' => $p->currency,
            'country'  => $p->country,
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
                'filename'   => $m->file_name ?? $m->original_filename,
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
     * Lädt Property-Werte (Selection) für mehrere Produkte in einem Query (Batch).
     */
    private function batchLoadPropertyValues(array $productIds, array $propertyAttributeIds): \Illuminate\Support\Collection
    {
        return ProductAttributeValue::whereIn('product_id', $productIds)
            ->whereIn('attribute_id', $propertyAttributeIds)
            ->whereNotNull('value_selection_id')
            ->select(['product_id', 'attribute_id', 'value_selection_id'])
            ->get()
            ->groupBy('product_id');
    }
}

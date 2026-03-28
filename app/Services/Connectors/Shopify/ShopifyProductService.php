<?php

declare(strict_types=1);

namespace App\Services\Connectors\Shopify;

use App\Models\Attribute;
use App\Models\AttributeViewAssignment;
use App\Models\ConnectorSyncLog;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductPrice;
use Illuminate\Http\Client\PendingRequest;

class ShopifyProductService
{
    private const API_VERSION = '2024-01';

    /**
     * Synchronisiert ein PIM-Produkt nach Shopify (Legacy-Pfad ohne Profil).
     */
    public function syncProduct(
        PendingRequest $http,
        string $shopUrl,
        Product $product,
        string $connectionId,
        string $language = 'de',
        array $options = [],
    ): array {
        $shopUrl = rtrim($shopUrl, '/');
        $productData = $this->collectProductData($product, $language, $options);

        // Prüfen ob Produkt bereits existiert (via Sync-Log)
        $existingId = $this->findExistingProductId($connectionId, $product->id);

        if ($existingId) {
            $response = $http->put(
                "{$shopUrl}/admin/api/" . self::API_VERSION . "/products/{$existingId}.json",
                ['product' => $productData],
            );
        } else {
            $response = $http->post(
                "{$shopUrl}/admin/api/" . self::API_VERSION . '/products.json',
                ['product' => $productData],
            );
        }

        $response->throw();
        $data = $response->json();
        $shopifyProductId = (string) ($data['product']['id'] ?? $existingId);

        return [
            'product_id'     => $shopifyProductId,
            'product_number' => $product->sku,
            'synced_data'    => array_keys($productData),
            'response'       => $data,
        ];
    }

    /**
     * Synchronisiert ein Produkt anhand des Export-Profils (WebsiteProfile + shopify_fields).
     *
     * @param  array  $metafields  Shopify-Metafields: [['namespace' => 'pim', 'key' => '...', ...], ...]
     * @param  string|null  $collectionId  Shopify-Collection-ID für die Zuordnung (separat via Collect)
     */
    public function syncProductFromProfile(
        PendingRequest $http,
        string $shopUrl,
        Product $product,
        string $connectionId,
        array $profilePayload,
        array $shopifyFields,
        string $language = 'de',
        array $metafields = [],
        ?string $collectionId = null,
    ): array {
        $shopUrl = rtrim($shopUrl, '/');
        $productData = $this->collectProfileProductData($product, $profilePayload, $shopifyFields, $language);

        if (!empty($metafields)) {
            $productData['metafields'] = $metafields;
        }

        // Prüfen ob Produkt bereits existiert
        $existingId = $this->findExistingProductId($connectionId, $product->id);

        if ($existingId) {
            // Bei Update: Metafields können nicht direkt im Product-Update gesetzt werden
            // Daher separat senden falls vorhanden
            $metafieldsForSeparateSync = $productData['metafields'] ?? [];
            unset($productData['metafields']);

            $response = $http->put(
                "{$shopUrl}/admin/api/" . self::API_VERSION . "/products/{$existingId}.json",
                ['product' => $productData],
            );
            $response->throw();

            // Metafields separat aktualisieren
            foreach ($metafieldsForSeparateSync as $metafield) {
                try {
                    $http->post(
                        "{$shopUrl}/admin/api/" . self::API_VERSION . "/products/{$existingId}/metafields.json",
                        ['metafield' => $metafield],
                    )->throw();
                } catch (\Throwable) {
                    // Einzelne Metafield-Fehler sind nicht kritisch
                }
            }
        } else {
            $response = $http->post(
                "{$shopUrl}/admin/api/" . self::API_VERSION . '/products.json',
                ['product' => $productData],
            );
            $response->throw();
        }

        $data = $response->json();
        $shopifyProductId = (string) ($data['product']['id'] ?? $existingId);

        // Collection-Zuordnung (Produkt → Custom Collection via Collect)
        if ($collectionId && $shopifyProductId) {
            $this->assignProductToCollection($http, $shopUrl, $shopifyProductId, $collectionId);
        }

        return [
            'product_id'     => $shopifyProductId,
            'product_number' => $product->sku,
            'synced_data'    => array_keys($productData),
            'response'       => $data,
        ];
    }

    /**
     * Erstellt eine Vorschau der Produktdaten ohne API-Aufruf (Dry Run).
     */
    public function previewProductData(
        Product $product,
        array $profilePayload,
        array $shopifyFields,
        string $language = 'de',
        array $metafields = [],
    ): array {
        $data = $this->collectProfileProductData($product, $profilePayload, $shopifyFields, $language);

        if (!empty($metafields)) {
            $data['metafields'] = $metafields;
        }

        return $data;
    }

    /**
     * Sammelt Produktdaten anhand des Export-Profils für Shopify.
     */
    private function collectProfileProductData(
        Product $product,
        array $profilePayload,
        array $shopifyFields,
        string $language,
    ): array {
        $data = [
            'title'  => $product->name,
            'status' => 'active',
        ];

        // Variante mit SKU, EAN, Preis, Stock
        $variant = [
            'sku' => $product->sku,
        ];

        if ($product->ean) {
            $variant['barcode'] = $product->ean;
        }

        // Status — steuerbar über shopify_fields['status']
        $statusMapping = $shopifyFields['status'] ?? ['mode' => 'default'];
        $statusMode = is_array($statusMapping) ? ($statusMapping['mode'] ?? 'default') : 'default';
        if ($statusMode === 'fixed') {
            $data['status'] = $statusMapping['value'] ?? 'active';
        }

        // Stock — steuerbar über shopify_fields['stock']
        $stockMapping = $shopifyFields['stock'] ?? ['mode' => 'default'];
        $stockMode = is_array($stockMapping) ? ($stockMapping['mode'] ?? 'default') : 'default';
        $stockValue = 999;

        if ($stockMode === 'default') {
            $stockValue = 999;
        } elseif ($stockMode === 'fixed') {
            $stockValue = (int) ($stockMapping['value'] ?? 0);
        } elseif ($stockMode === 'attribute') {
            $attrValue = $this->resolveFieldMapping($product, $stockMapping, $language);
            $stockValue = is_numeric($attrValue) ? (int) $attrValue : 0;
        }
        $variant['inventory_quantity'] = $stockValue;
        $variant['inventory_management'] = 'shopify';

        // Keys die intern sind und nicht als Shopify-Felder behandelt werden
        $internalKeys = ['_metafield_attribute_ids', '_sync_media', 'price', 'description',
            'compare_at_price', 'stock', 'status'];

        // Shopify-Felder auflösen (vendor, product_type, tags, etc.)
        foreach ($shopifyFields as $field => $mapping) {
            if (in_array($field, $internalKeys, true) || str_starts_with($field, '_')) {
                continue;
            }
            if (!is_array($mapping) || !isset($mapping['mode'])) {
                continue;
            }

            $value = $mapping['mode'] === 'default'
                ? null
                : $this->resolveFieldMapping($product, $mapping, $language);

            if ($value !== null && $value !== '') {
                $data[$field] = $value;
            }
        }

        // Beschreibung — steuerbar über shopify_fields['description']
        $descMapping = $shopifyFields['description'] ?? ['mode' => 'default'];
        $descMode = is_array($descMapping) ? ($descMapping['mode'] ?? 'default') : 'default';

        if ($descMode === 'default') {
            $descriptionAttrs = $profilePayload['description_attributes'] ?? [];
            if (!empty($descriptionAttrs)) {
                $descAttrIds = array_column($descriptionAttrs, 'attribute_id');
                $descParts = $this->resolveAttributeValues($product, $descAttrIds, $language);
                $descriptionHtml = collect($descParts)
                    ->map(fn ($part) => "<p><strong>{$part['label']}</strong>: {$part['value']}</p>")
                    ->implode("\n");
                if ($descriptionHtml) {
                    $data['body_html'] = $descriptionHtml;
                }
            }
        } elseif ($descMode === 'fixed') {
            $fixedDesc = $descMapping['value'] ?? '';
            if ($fixedDesc) {
                $data['body_html'] = $fixedDesc;
            }
        } elseif ($descMode === 'attributes') {
            $descAttrIds = $descMapping['attribute_ids'] ?? [];
            if (!empty($descAttrIds)) {
                $descParts = $this->resolveAttributeValues($product, $descAttrIds, $language);
                $descriptionHtml = collect($descParts)
                    ->map(fn ($part) => "<p><strong>{$part['label']}</strong>: {$part['value']}</p>")
                    ->implode("\n");
                if ($descriptionHtml) {
                    $data['body_html'] = $descriptionHtml;
                }
            }
        } elseif ($descMode === 'attribute') {
            $value = $this->resolveFieldMapping($product, $descMapping, $language);
            if ($value) {
                $data['body_html'] = $value;
            }
        }

        // Preis — steuerbar über shopify_fields['price']
        $priceMapping = $shopifyFields['price'] ?? ['mode' => 'default'];
        $priceMode = is_array($priceMapping) ? ($priceMapping['mode'] ?? 'default') : 'default';

        $price = null;
        if ($priceMode === 'default') {
            $priceTypeId = $profilePayload['card_price_type_id'] ?? null;
            $priceCountry = $profilePayload['card_price_country'] ?? null;
            if ($priceTypeId) {
                $price = $this->resolvePrice($product, $priceTypeId, $priceCountry);
            }
            if (!$price) {
                $firstPrice = $product->prices()->first();
                if ($firstPrice) {
                    $price = ['amount' => (float) $firstPrice->amount, 'currency' => $firstPrice->currency ?? 'EUR'];
                }
            }
        } elseif ($priceMode === 'fixed') {
            $fixedAmount = (float) ($priceMapping['value'] ?? 0);
            if ($fixedAmount > 0) {
                $price = ['amount' => $fixedAmount, 'currency' => 'EUR'];
            }
        } elseif ($priceMode === 'attribute') {
            $attrValue = $this->resolveFieldMapping($product, $priceMapping, $language);
            $amount = is_numeric($attrValue) ? (float) $attrValue : 0;
            if ($amount > 0) {
                $price = ['amount' => $amount, 'currency' => 'EUR'];
            }
        }

        if ($price) {
            $variant['price'] = number_format($price['amount'], 2, '.', '');

            // Vergleichspreis (UVP) — compare_at_price
            $compareMapping = $shopifyFields['compare_at_price'] ?? null;
            if (is_array($compareMapping) && isset($compareMapping['mode'])) {
                $compareAmount = $this->resolveNumericField($product, $compareMapping, $language);
                if ($compareAmount > 0) {
                    $variant['compare_at_price'] = number_format($compareAmount, 2, '.', '');
                }
            }
        }

        // Variante zum Produkt hinzufügen
        $data['variants'] = [$variant];

        return array_filter($data, fn ($v) => $v !== null);
    }

    /**
     * Weist ein Produkt einer Custom Collection zu via Collect.
     */
    private function assignProductToCollection(
        PendingRequest $http,
        string $shopUrl,
        string $productId,
        string $collectionId,
    ): void {
        try {
            $http->post(
                "{$shopUrl}/admin/api/" . self::API_VERSION . '/collects.json',
                [
                    'collect' => [
                        'product_id'    => (int) $productId,
                        'collection_id' => (int) $collectionId,
                    ],
                ],
            )->throw();
        } catch (\Illuminate\Http\Client\RequestException $e) {
            // 422 = bereits zugeordnet — ignorieren
            if ($e->response?->status() !== 422) {
                throw $e;
            }
        }
    }

    /**
     * Löst ein numerisches Feld auf (für Preise, Maße, etc.).
     */
    private function resolveNumericField(Product $product, array $mapping, string $language): float
    {
        $mode = $mapping['mode'] ?? 'fixed';
        if ($mode === 'fixed') {
            return (float) ($mapping['value'] ?? 0);
        }
        if ($mode === 'attribute') {
            $value = $this->resolveFieldMapping($product, $mapping, $language);
            return is_numeric($value) ? (float) $value : 0;
        }
        return 0;
    }

    /**
     * Löst ein shopify_fields-Mapping auf (fixed value oder Attribut-Wert).
     */
    private function resolveFieldMapping(Product $product, array $mapping, string $language): mixed
    {
        $mode = $mapping['mode'] ?? 'fixed';

        if ($mode === 'fixed') {
            return $mapping['value'] ?? null;
        }

        if ($mode !== 'attribute') {
            return null;
        }

        $attributeId = $mapping['attribute_id'] ?? null;
        if (!$attributeId) {
            return null;
        }

        $attrValue = ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', $attributeId)
            ->with(['attribute', 'valueListEntry', 'unit'])
            ->first();

        if (!$attrValue || !$attrValue->attribute) {
            return null;
        }

        return $this->resolveAttributeValueString($attrValue, $attrValue->attribute, $language);
    }

    /**
     * Löst mehrere Attribut-Werte für ein Produkt auf.
     */
    private function resolveAttributeValues(Product $product, array $attributeIds, string $language): array
    {
        if (empty($attributeIds)) {
            return [];
        }

        $values = ProductAttributeValue::where('product_id', $product->id)
            ->whereIn('attribute_id', $attributeIds)
            ->with(['attribute', 'valueListEntry', 'unit'])
            ->get();

        $result = [];
        $orderMap = array_flip($attributeIds);

        foreach ($values as $av) {
            $attr = $av->attribute;
            if (!$attr || $attr->is_internal) {
                continue;
            }
            $value = $this->resolveAttributeValueString($av, $attr, $language);
            if ($value === null || $value === '') {
                continue;
            }
            $unit = $av->unit?->abbreviation;
            $result[] = [
                'attribute_id'   => $attr->id,
                'technical_name' => $attr->technical_name,
                'label'          => $language === 'en' && $attr->name_en ? $attr->name_en : $attr->name_de,
                'value'          => $unit ? $value . ' ' . $unit : $value,
                'order'          => $orderMap[$attr->id] ?? 999,
            ];
        }

        usort($result, fn ($a, $b) => $a['order'] - $b['order']);

        return $result;
    }

    /**
     * Ermittelt den relevantesten Preis anhand PriceType + Country.
     */
    private function resolvePrice(Product $product, string $priceTypeId, ?string $country): ?array
    {
        $query = ProductPrice::where('product_id', $product->id)
            ->where('price_type_id', $priceTypeId)
            ->where(function ($q) {
                $q->whereNull('valid_to')
                   ->orWhere('valid_to', '>=', now()->toDateString());
            });

        if ($country) {
            $query->where(function ($q) use ($country) {
                $q->where('country', $country)
                   ->orWhereNull('country');
            })->orderByRaw('country IS NULL ASC');
        }

        $query->orderBy('valid_from', 'desc');

        $price = $query->first();
        if (!$price) {
            return null;
        }

        return [
            'amount'   => (float) $price->amount,
            'currency' => $price->currency ?? 'EUR',
        ];
    }

    /**
     * Attribut-Wert als String auflösen.
     */
    private function resolveAttributeValueString($attrValue, $attr, string $lang): ?string
    {
        return match ($attr->data_type) {
            'String' => $attrValue->value_string,
            'Number', 'Float' => $attrValue->value_number !== null
                ? rtrim(rtrim((string) $attrValue->value_number, '0'), '.')
                : null,
            'Date' => $attrValue->value_date?->format('Y-m-d'),
            'Flag' => $attrValue->value_flag !== null
                ? ($attrValue->value_flag ? 'true' : 'false')
                : null,
            'Selection' => $this->resolveSelectionValue($attrValue, $lang),
            'Dictionary' => $this->resolveExportDictionaryValue($attrValue, $lang),
            default => $attrValue->value_string,
        };
    }

    private function resolveSelectionValue($attrValue, string $lang): ?string
    {
        $entry = $attrValue->valueListEntry;
        if (!$entry) {
            return null;
        }
        return $lang === 'en' && $entry->display_value_en
            ? $entry->display_value_en
            : $entry->display_value_de;
    }

    private function resolveExportDictionaryValue($attrValue, string $lang): ?string
    {
        $entry = $attrValue->dictionaryEntry;
        if (!$entry) {
            return null;
        }
        return $lang === 'en' && $entry->short_text_en
            ? $entry->short_text_en
            : $entry->short_text_de;
    }

    /**
     * Ermittelt die erlaubten Attribut-IDs basierend auf Profil-Einstellungen.
     *
     * @return array<string>|null  null = alle Attribute erlaubt
     */
    public function resolveAllowedAttributeIds(Product $product, array $profilePayload): ?array
    {
        $attributeViewIds = $profilePayload['attribute_view_ids'] ?? [];

        if (empty($attributeViewIds)) {
            return null;
        }

        return AttributeViewAssignment::whereIn('attribute_view_id', $attributeViewIds)
            ->pluck('attribute_id')
            ->unique()
            ->toArray();
    }

    /**
     * Ermittelt die Selection-Attribut-IDs aus den erlaubten Attributen (für Metafields).
     */
    public function resolveMetafieldAttributeIds(Product $product, array $profilePayload): array
    {
        $allowedIds = $this->resolveAllowedAttributeIds($product, $profilePayload);

        $query = Attribute::where('data_type', 'Selection')
            ->whereNotNull('value_list_id')
            ->where('is_internal', false);

        if ($allowedIds !== null) {
            $query->whereIn('id', $allowedIds);
        }

        return $query->pluck('id')->toArray();
    }

    /**
     * Sucht die bestehende Shopify Product ID aus den Sync-Logs.
     */
    public function findExistingProductId(string $connectionId, string $pimProductId): ?string
    {
        return ConnectorSyncLog::where('connector_connection_id', $connectionId)
            ->whereIn('action', ['product_push', 'profile_sync', 'delta_sync'])
            ->where('entity_type', 'product')
            ->where('entity_id', $pimProductId)
            ->where('status', 'success')
            ->whereNotNull('external_id')
            ->latest()
            ->value('external_id');
    }

    /**
     * Legacy: Sammelt Produktdaten ohne Profil.
     */
    private function collectProductData(Product $product, string $language, array $options): array
    {
        $data = [
            'title'  => $product->name,
            'status' => 'active',
            'variants' => [
                [
                    'sku'                  => $product->sku,
                    'barcode'              => $product->ean,
                    'inventory_quantity'    => 0,
                    'inventory_management' => 'shopify',
                ],
            ],
        ];

        $attributeCodes = $options['attributes'] ?? null;
        $query = $product->attributeValues()
            ->with('attribute')
            ->where('language', $language)
            ->whereNotNull('value_string');

        if ($attributeCodes) {
            $query->whereHas('attribute', fn ($q) => $q->whereIn('technical_name', $attributeCodes));
        }

        $description = null;
        $metafields = [];

        foreach ($query->get() as $av) {
            $techName = $av->attribute->technical_name ?? null;
            if (!$techName) {
                continue;
            }

            if (in_array($techName, ['description', 'beschreibung', 'long_description'])) {
                $description = $av->value_string;
                continue;
            }

            $metafields[] = [
                'namespace' => 'pim',
                'key'       => $techName,
                'value'     => $av->value_string,
                'type'      => 'single_line_text_field',
            ];
        }

        if ($description) {
            $data['body_html'] = $description;
        }

        if (!empty($metafields)) {
            $data['metafields'] = $metafields;
        }

        if ($options['include_prices'] ?? true) {
            $prices = $product->prices()->get();
            if ($prices->isNotEmpty()) {
                $price = $prices->first();
                $data['variants'][0]['price'] = number_format((float) $price->amount, 2, '.', '');
            }
        }

        return array_filter($data, fn ($v) => $v !== null);
    }
}

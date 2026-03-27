<?php

declare(strict_types=1);

namespace App\Services\Connectors\Shopware;

use App\Models\Attribute;
use App\Models\AttributeViewAssignment;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductPrice;
use Illuminate\Http\Client\PendingRequest;

class ShopwareProductService
{
    /**
     * Synchronisiert ein PIM-Produkt nach Shopware 6 (Legacy-Pfad ohne Profil).
     */
    public function syncProduct(
        PendingRequest $http,
        string $shopUrl,
        Product $product,
        string $language = 'de',
        array $options = [],
    ): array {
        $shopUrl = rtrim($shopUrl, '/');
        $productData = $this->collectProductData($product, $language, $options);

        $response = $http->post("{$shopUrl}/api/_action/sync", [
            'write-product' => [
                'action'  => 'upsert',
                'entity'  => 'product',
                'payload' => [$productData],
            ],
        ]);

        $response->throw();

        return [
            'product_id'     => $productData['id'] ?? $product->sku,
            'product_number' => $productData['productNumber'],
            'synced_data'    => array_keys($productData),
            'response'       => $response->json(),
        ];
    }

    /**
     * Synchronisiert ein Produkt anhand des Export-Profils (WebsiteProfile + shopware_fields).
     *
     * @param  array       $properties  Shopware-Properties: [['id' => 'option-uuid'], ...]
     * @param  string|null $categoryId  Shopware-Kategorie-UUID für die Zuordnung
     */
    public function syncProductFromProfile(
        PendingRequest $http,
        string $shopUrl,
        Product $product,
        array $profilePayload,
        array $shopwareFields,
        string $language = 'de',
        array $properties = [],
        ?string $categoryId = null,
    ): array {
        $shopUrl = rtrim($shopUrl, '/');
        $productData = $this->collectProfileProductData($product, $profilePayload, $shopwareFields, $language);

        if (!empty($properties)) {
            $productData['properties'] = $properties;
        }

        if ($categoryId) {
            $productData['categories'] = [['id' => $categoryId]];
        }

        $response = $http->post("{$shopUrl}/api/_action/sync", [
            'write-product' => [
                'action'  => 'upsert',
                'entity'  => 'product',
                'payload' => [$productData],
            ],
        ]);

        $response->throw();

        return [
            'product_id'     => $productData['id'] ?? $product->sku,
            'product_number' => $productData['productNumber'],
            'synced_data'    => array_keys($productData),
            'response'       => $response->json(),
        ];
    }

    /**
     * Erstellt eine Vorschau der Produktdaten ohne API-Aufruf (Dry Run).
     */
    public function previewProductData(
        Product $product,
        array $profilePayload,
        array $shopwareFields,
        string $language = 'de',
        array $properties = [],
    ): array {
        $data = $this->collectProfileProductData($product, $profilePayload, $shopwareFields, $language);

        if (!empty($properties)) {
            $data['properties'] = $properties;
        }

        return $data;
    }

    /**
     * Sammelt Produktdaten anhand des Export-Profils.
     */
    private function collectProfileProductData(
        Product $product,
        array $profilePayload,
        array $shopwareFields,
        string $language,
    ): array {
        $shopwareProductId = str_replace('-', '', $product->id);

        // Stock: konfigurierbar über shopware_fields['stock'], Default = 999
        // (Shopware zeigt Produkte mit Stock=0 standardmäßig nicht im Frontend an)
        $stockMapping = $shopwareFields['stock'] ?? ['mode' => 'default'];
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

        $data = [
            'id'            => $shopwareProductId,
            'productNumber' => $product->sku,
            'name'          => $product->name,
            'active'        => true,
            'stock'         => $stockValue,
        ];

        // Standard-Defaults für Felder im "default"-Modus
        $fieldDefaults = [
            'name'   => $product->name,
            'ean'    => $product->ean,
        ];

        // Keys die intern sind und nicht als Shopware-Felder behandelt werden
        $internalKeys = ['_property_attribute_ids', '_sync_media', '_sales_channel_id', 'price', 'description', 'list_price', 'purchase_price', 'stock'];

        // Shopware-Pflichtfelder auflösen
        // Keys werden von snake_case in camelCase konvertiert (Shopware API erwartet camelCase)
        $fieldKeyMap = [
            'tax_id'           => 'taxId',
            'manufacturer_id'  => 'manufacturerId',
            'currency_id'      => 'currencyId',
            'meta_title'       => 'metaTitle',
            'meta_description' => 'metaDescription',
        ];

        foreach ($shopwareFields as $swField => $mapping) {
            // Interne Keys und separat behandelte Felder überspringen
            if (in_array($swField, $internalKeys, true) || str_starts_with($swField, '_')) {
                continue;
            }

            // Mapping muss ein Array mit 'mode' sein
            if (!is_array($mapping) || !isset($mapping['mode'])) {
                continue;
            }

            $mode = $mapping['mode'];

            if ($mode === 'default') {
                $value = $fieldDefaults[$swField] ?? null;
            } else {
                $value = $this->resolveFieldMapping($product, $mapping, $language);
            }

            if ($value !== null && $value !== '') {
                $apiField = $fieldKeyMap[$swField] ?? $swField;
                $data[$apiField] = $value;
            }
        }

        // Beschreibung — steuerbar über shopware_fields['description']
        $descMapping = $shopwareFields['description'] ?? ['mode' => 'default'];
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
                    $data['description'] = $descriptionHtml;
                }
            }
        } elseif ($descMode === 'fixed') {
            $fixedDesc = $descMapping['value'] ?? '';
            if ($fixedDesc) {
                $data['description'] = $fixedDesc;
            }
        } elseif ($descMode === 'attributes') {
            $descAttrIds = $descMapping['attribute_ids'] ?? [];
            if (!empty($descAttrIds)) {
                $descParts = $this->resolveAttributeValues($product, $descAttrIds, $language);
                $descriptionHtml = collect($descParts)
                    ->map(fn ($part) => "<p><strong>{$part['label']}</strong>: {$part['value']}</p>")
                    ->implode("\n");
                if ($descriptionHtml) {
                    $data['description'] = $descriptionHtml;
                }
            }
        } elseif ($descMode === 'attribute') {
            $value = $this->resolveFieldMapping($product, $descMapping, $language);
            if ($value) {
                $data['description'] = $value;
            }
        }

        // Attribute als customFields — basierend auf Profil-Einstellungen (Attribut-Sichten)
        $allowedAttributeIds = $this->resolveAllowedAttributeIds($product, $profilePayload);
        $customFieldQuery = ProductAttributeValue::where('product_id', $product->id)
            ->with(['attribute', 'valueListEntry', 'unit']);

        if ($allowedAttributeIds !== null) {
            $customFieldQuery->whereIn('attribute_id', $allowedAttributeIds);
        }

        $customFields = [];
        foreach ($customFieldQuery->get() as $av) {
            $attr = $av->attribute;
            if (!$attr || $attr->is_internal) {
                continue;
            }
            $value = $this->resolveAttributeValueString($av, $attr, $language);
            if ($value === null || $value === '') {
                continue;
            }
            $unit = $av->unit?->abbreviation;
            $key = 'pim_' . $attr->technical_name;
            $customFields[$key] = $unit ? $value . ' ' . $unit : $value;
        }
        if (!empty($customFields)) {
            $data['customFields'] = $customFields;
        }

        // Preis — steuerbar über shopware_fields['price']
        $priceMapping = $shopwareFields['price'] ?? ['mode' => 'default'];
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
                    $price = [
                        'amount'   => (float) $firstPrice->amount,
                        'currency' => $firstPrice->currency ?? 'EUR',
                    ];
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
            $taxRate = 19;
            $currencyMapping = $shopwareFields['currency_id'] ?? [];
            $currencyId = is_array($currencyMapping) ? ($currencyMapping['value'] ?? 'b7d2554b0ce847cd82f3ac9bd1c0dfca') : 'b7d2554b0ce847cd82f3ac9bd1c0dfca';

            $priceEntry = [
                'currencyId' => $currencyId,
                'gross'      => $price['amount'],
                'net'        => round($price['amount'] / (1 + $taxRate / 100), 2),
                'linked'     => true,
            ];

            // Listenpreis (UVP)
            $listPriceMapping = $shopwareFields['list_price'] ?? null;
            if (is_array($listPriceMapping) && isset($listPriceMapping['mode'])) {
                $listAmount = $this->resolveNumericField($product, $listPriceMapping, $language);
                if ($listAmount > 0) {
                    $priceEntry['listPrice'] = [
                        'currencyId' => $currencyId,
                        'gross'      => $listAmount,
                        'net'        => round($listAmount / (1 + $taxRate / 100), 2),
                        'linked'     => true,
                    ];
                }
            }

            $data['price'] = [$priceEntry];

            // Einkaufspreis
            $purchaseMapping = $shopwareFields['purchase_price'] ?? null;
            if (is_array($purchaseMapping) && isset($purchaseMapping['mode'])) {
                $purchaseAmount = $this->resolveNumericField($product, $purchaseMapping, $language);
                if ($purchaseAmount > 0) {
                    $data['purchasePrice'] = $purchaseAmount;
                }
            }
        }

        // Sales Channel Sichtbarkeit — ohne visibilities ist das Produkt im Frontend unsichtbar
        // Die ID muss deterministisch sein damit upsert funktioniert (kein Duplikat beim Re-Sync)
        $salesChannelId = $shopwareFields['_sales_channel_id']['value'] ?? null;
        if ($salesChannelId) {
            // Deterministische 32-Hex-Zeichen UUID aus Produkt + Sales Channel
            $visibilityId = substr(md5($shopwareProductId . $salesChannelId . 'visibility'), 0, 32);
            $data['visibilities'] = [
                [
                    'id'             => $visibilityId,
                    'productId'      => $shopwareProductId,
                    'salesChannelId' => $salesChannelId,
                    'visibility'     => 30,
                ],
            ];
        }

        return array_filter($data, fn ($v) => $v !== null);
    }

    /**
     * Holt die Standard-Sales-Channel-ID von Shopware.
     */
    public function fetchDefaultSalesChannelId(PendingRequest $http, string $shopUrl): ?string
    {
        $shopUrl = rtrim($shopUrl, '/');

        try {
            $response = $http->post("{$shopUrl}/api/search/sales-channel", [
                'limit'  => 1,
                'filter' => [
                    ['type' => 'equals', 'field' => 'active', 'value' => true],
                    ['type' => 'equals', 'field' => 'typeId', 'value' => '8a243080f92e4c719546314b577cf82b'],  // Storefront type
                ],
            ]);
            $response->throw();
            $data = $response->json();

            return $data['data'][0]['id'] ?? null;
        } catch (\Throwable) {
            // Fallback: jeden aktiven Sales Channel nehmen
            try {
                $response = $http->post("{$shopUrl}/api/search/sales-channel", [
                    'limit'  => 1,
                    'filter' => [
                        ['type' => 'equals', 'field' => 'active', 'value' => true],
                    ],
                ]);
                $response->throw();
                $data = $response->json();

                return $data['data'][0]['id'] ?? null;
            } catch (\Throwable) {
                return null;
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
     * Löst ein shopware_fields-Mapping auf (fixed value oder Attribut-Wert).
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
     * Holt die Standard-Steuer-ID von Shopware (erste verfügbare Tax).
     */
    public function fetchDefaultTaxId(PendingRequest $http, string $shopUrl): ?string
    {
        $shopUrl = rtrim($shopUrl, '/');

        try {
            $response = $http->post("{$shopUrl}/api/search/tax", [
                'limit' => 1,
                'sort'  => [['field' => 'position', 'order' => 'ASC']],
            ]);
            $response->throw();
            $data = $response->json();

            return $data['data'][0]['id'] ?? null;
        } catch (\Throwable) {
            return null;
        }
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
     * Ermittelt die Selection-Attribut-IDs aus den erlaubten Attributen (für Properties).
     */
    public function resolvePropertyAttributeIds(Product $product, array $profilePayload): array
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
     * Legacy: Sammelt Produktdaten ohne Profil.
     */
    private function collectProductData(Product $product, string $language, array $options): array
    {
        $data = [
            'id'            => str_replace('-', '', $product->id),
            'productNumber' => $product->sku,
            'name'          => $product->name,
            'active'        => true,
            'stock'         => 0,
            'taxId'         => $options['tax_id'] ?? $options['_default_tax_id'] ?? null,
        ];

        if ($product->ean) {
            $data['ean'] = $product->ean;
        }

        $attributeCodes = $options['attributes'] ?? null;
        $query = $product->attributeValues()
            ->with('attribute')
            ->where('language', $language)
            ->whereNotNull('value_string');

        if ($attributeCodes) {
            $query->whereHas('attribute', fn ($q) => $q->whereIn('technical_name', $attributeCodes));
        }

        $customFields = [];
        $description = null;

        foreach ($query->get() as $av) {
            $techName = $av->attribute->technical_name ?? null;
            if (!$techName) {
                continue;
            }

            if (in_array($techName, ['description', 'beschreibung', 'long_description'])) {
                $description = $av->value_string;
                continue;
            }

            $customFields["pim_{$techName}"] = $av->value_string;
        }

        if ($description) {
            $data['description'] = $description;
        }

        if (!empty($customFields)) {
            $data['customFields'] = $customFields;
        }

        if ($options['include_prices'] ?? true) {
            $prices = $product->prices()->get();

            if ($prices->isNotEmpty()) {
                $price = $prices->first();
                $data['price'] = [
                    [
                        'currencyId' => $options['currency_id'] ?? 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                        'gross'      => (float) $price->amount,
                        'net'        => round((float) $price->amount / 1.19, 2),
                        'linked'     => true,
                    ],
                ];
            }
        }

        return array_filter($data, fn ($v) => $v !== null);
    }
}

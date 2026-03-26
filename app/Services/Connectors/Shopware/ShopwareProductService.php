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
     * Synchronisiert ein PIM-Produkt nach Shopware 6.
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

        // Upsert via Shopware Sync API (requires named operations)
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
     */
    /**
     * @param  array  $properties  Shopware-Properties: [['id' => 'option-uuid'], ...]
     */
    public function syncProductFromProfile(
        PendingRequest $http,
        string $shopUrl,
        Product $product,
        array $profilePayload,
        array $shopwareFields,
        string $language = 'de',
        array $properties = [],
    ): array {
        $shopUrl = rtrim($shopUrl, '/');
        $productData = $this->collectProfileProductData($product, $profilePayload, $shopwareFields, $language);

        // Properties (aus ShopwarePropertyService)
        if (!empty($properties)) {
            $productData['properties'] = $properties;
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
     *
     * @return array  Das Shopware-Payload das gesendet würde
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
        // Deterministische Shopware-UUID aus PIM-Product-ID (ohne Bindestriche)
        $shopwareProductId = str_replace('-', '', $product->id);

        $data = [
            'id'            => $shopwareProductId,
            'productNumber' => $product->sku,
            'name'          => $product->name,
            'active'        => true,
            'stock'         => 0,
        ];

        // Standard-Defaults für Felder im "default"-Modus
        $fieldDefaults = [
            'name'   => $product->name,
            'ean'    => $product->ean,
        ];

        // Shopware-Pflichtfelder auflösen (name, tax_id, manufacturer_id, ean, etc.)
        // Keys werden von snake_case in camelCase konvertiert (Shopware API erwartet camelCase)
        $fieldKeyMap = [
            'tax_id'           => 'taxId',
            'manufacturer_id'  => 'manufacturerId',
            'currency_id'      => 'currencyId',
            'meta_title'       => 'metaTitle',
            'meta_description' => 'metaDescription',
            'purchase_price'   => 'purchasePrice',
        ];
        foreach ($shopwareFields as $swField => $mapping) {
            // price, description, list_price werden separat behandelt
            if (in_array($swField, ['price', 'description', 'list_price'], true)) {
                continue;
            }

            $mode = $mapping['mode'] ?? 'default';

            if ($mode === 'default') {
                // Standard-Wert aus PIM-Stammdaten verwenden
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
        $descMode = $descMapping['mode'] ?? 'default';

        if ($descMode === 'default') {
            // Standard: description_attributes aus Vorschau-Profil
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
            // Mehrere Attribute verketten
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
            // Einzelnes Attribut als Beschreibung
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
            // Nur Attribute aus den konfigurierten Sichten
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
        $priceMode = $priceMapping['mode'] ?? 'default';

        $price = null;
        if ($priceMode === 'default') {
            // Standard: Preis aus Vorschau-Profil, dann Fallback auf ersten Preis
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
            // Fester Preis
            $fixedAmount = (float) ($priceMapping['value'] ?? 0);
            if ($fixedAmount > 0) {
                $price = ['amount' => $fixedAmount, 'currency' => 'EUR'];
            }
        } elseif ($priceMode === 'attribute') {
            // Preis aus einem Attribut lesen
            $attrId = $priceMapping['attribute_id'] ?? null;
            if ($attrId) {
                $attrValue = $this->resolveFieldMapping($product, $priceMapping, $language);
                $amount = is_numeric($attrValue) ? (float) $attrValue : 0;
                if ($amount > 0) {
                    $price = ['amount' => $amount, 'currency' => 'EUR'];
                }
            }
        }

        if ($price) {
            $taxRate = 19; // Standard-MwSt
            $currencyId = $shopwareFields['currency_id']['value'] ?? 'b7d2554b0ce847cd82f3ac9bd1c0dfca';
            $priceEntry = [
                'currencyId' => $currencyId,
                'gross'      => $price['amount'],
                'net'        => round($price['amount'] / (1 + $taxRate / 100), 2),
                'linked'     => true,
            ];

            // Listenpreis (UVP) — optional
            $listPriceMapping = $shopwareFields['list_price'] ?? null;
            if ($listPriceMapping) {
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

            // Einkaufspreis — separates Feld auf dem Produkt, nicht im price-Array
            $purchaseMapping = $shopwareFields['purchase_price'] ?? null;
            if ($purchaseMapping) {
                $purchaseAmount = $this->resolveNumericField($product, $purchaseMapping, $language);
                if ($purchaseAmount > 0) {
                    $data['purchasePrice'] = $purchaseAmount;
                }
            }
        }

        return array_filter($data, fn ($v) => $v !== null);
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

        // mode === 'attribute'
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
     *
     * @return array<array{attribute_id: string, technical_name: string, label: string, value: string}>
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

        // Konfigurierte Reihenfolge beibehalten
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
     * Attribut-Wert als String auflösen (analog CatalogController::resolveExportAttributeValue).
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
     * Nutzt die gleiche Logik wie der Katalog: attribute_view_ids → Attribute aus diesen Sichten.
     * Wenn keine Views konfiguriert sind → alle Attribute (return null).
     *
     * @return array<string>|null  null = alle Attribute erlaubt
     */
    public function resolveAllowedAttributeIds(Product $product, array $profilePayload): ?array
    {
        $attributeViewIds = $profilePayload['attribute_view_ids'] ?? [];

        if (empty($attributeViewIds)) {
            return null; // Alle Attribute
        }

        $viewAttributeIds = AttributeViewAssignment::whereIn('attribute_view_id', $attributeViewIds)
            ->pluck('attribute_id')
            ->unique();

        return $viewAttributeIds->toArray();
    }

    /**
     * Ermittelt die Selection-Attribut-IDs aus den erlaubten Attributen (für Properties).
     *
     * @return array<string>  IDs der Selection-Attribute
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
     * Sammelt Produktdaten für das Shopware-Format (Legacy ohne Profil).
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

        // EAN
        if ($product->ean) {
            $data['ean'] = $product->ean;
        }

        // Attribute als customFields
        $attributeCodes = $options['attributes'] ?? null;
        $query = $product->attributeValues()
            ->with('attribute')
            ->where('language', $language)
            ->whereNotNull('value_string');

        if ($attributeCodes) {
            $query->whereHas('attribute', fn ($q) => $q->whereIn('code', $attributeCodes));
        }

        $customFields = [];
        $description = null;

        foreach ($query->get() as $av) {
            $code = $av->attribute->code ?? null;
            if (! $code) continue;

            if (in_array($code, ['description', 'beschreibung', 'long_description'])) {
                $description = $av->value_string;
                continue;
            }

            $customFields["pim_{$code}"] = $av->value_string;
        }

        if ($description) {
            $data['description'] = $description;
        }

        if (! empty($customFields)) {
            $data['customFields'] = $customFields;
        }

        // Preise
        if ($options['include_prices'] ?? true) {
            $prices = $product->prices()
                ->with(['priceType', 'priceRegion'])
                ->get();

            if ($prices->isNotEmpty()) {
                $price = $prices->first();
                $data['price'] = [
                    [
                        'currencyId' => $options['currency_id'] ?? 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                        'gross'      => $price->value,
                        'net'        => round($price->value / 1.19, 2),
                        'linked'     => true,
                    ],
                ];
            }
        }

        return array_filter($data, fn ($v) => $v !== null);
    }
}

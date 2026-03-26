<?php

declare(strict_types=1);

namespace App\Services\Connectors\Shopware;

use App\Models\Attribute;
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
    public function syncProductFromProfile(
        PendingRequest $http,
        string $shopUrl,
        Product $product,
        array $profilePayload,
        array $shopwareFields,
        string $language = 'de',
    ): array {
        $shopUrl = rtrim($shopUrl, '/');
        $productData = $this->collectProfileProductData($product, $profilePayload, $shopwareFields, $language);

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
     * Sammelt Produktdaten anhand des Export-Profils.
     */
    private function collectProfileProductData(
        Product $product,
        array $profilePayload,
        array $shopwareFields,
        string $language,
    ): array {
        $data = [
            'productNumber' => $product->sku,
            'name'          => $product->name,
            'active'        => true,
            'stock'         => 0,
        ];

        // Shopware-Pflichtfelder auflösen (tax_id, manufacturer_id, ean, etc.)
        foreach ($shopwareFields as $swField => $mapping) {
            $value = $this->resolveFieldMapping($product, $mapping, $language);
            if ($value !== null && $value !== '') {
                $data[$swField] = $value;
            }
        }

        // Beschreibung aus description_attributes des Profils
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

        // Card-Attribute als customFields
        $cardAttributeIds = $profilePayload['card_attribute_ids'] ?? [];
        if (!empty($cardAttributeIds)) {
            $cardParts = $this->resolveAttributeValues($product, $cardAttributeIds, $language);
            $customFields = [];
            foreach ($cardParts as $part) {
                $key = 'pim_' . ($part['technical_name'] ?? $part['attribute_id']);
                $customFields[$key] = $part['value'];
            }
            if (!empty($customFields)) {
                $data['customFields'] = $customFields;
            }
        }

        // Preis aus card_price_type_id des Profils
        $priceTypeId = $profilePayload['card_price_type_id'] ?? null;
        $priceCountry = $profilePayload['card_price_country'] ?? null;
        if ($priceTypeId) {
            $price = $this->resolvePrice($product, $priceTypeId, $priceCountry);
            if ($price) {
                // Steuer-ID aus shopware_fields für Brutto/Netto-Berechnung
                $taxRate = 19; // Standard-MwSt
                $currencyId = $shopwareFields['currency_id']['value'] ?? 'b7d2554b0ce847cd82f3ac9bd1c0dfca';
                $data['price'] = [
                    [
                        'currencyId' => $currencyId,
                        'gross'      => $price['amount'],
                        'net'        => round($price['amount'] / (1 + $taxRate / 100), 2),
                        'linked'     => true,
                    ],
                ];
            }
        }

        return array_filter($data, fn ($v) => $v !== null);
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
     * Sammelt Produktdaten für das Shopware-Format (Legacy ohne Profil).
     */
    private function collectProductData(Product $product, string $language, array $options): array
    {
        $data = [
            'productNumber' => $product->sku,
            'name'          => $product->name,
            'active'        => true,
            'stock'         => 0,
            'taxId'         => $options['tax_id'] ?? null,
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

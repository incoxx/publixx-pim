<?php

declare(strict_types=1);

namespace App\Services\Connectors\SalesforceCommerce;

use App\Models\Attribute;
use App\Models\ConnectorSyncLog;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductPrice;
use Illuminate\Http\Client\PendingRequest;

class SalesforceCommerceProductService
{
    private const API_VERSION = 'v24_5';

    /**
     * Synchronisiert ein PIM-Produkt nach SFCC (Legacy-Pfad ohne Profil).
     */
    public function syncProduct(
        PendingRequest $http,
        string $instanceUrl,
        Product $product,
        string $language = 'de',
        array $options = [],
    ): array {
        $instanceUrl = rtrim($instanceUrl, '/');
        $productId = $product->sku;
        $productData = $this->collectProductData($product, $language, $options);

        $response = $http->put(
            "{$instanceUrl}/s/-/dw/data/" . self::API_VERSION . "/products/{$productId}",
            $productData,
        );

        $response->throw();
        $data = $response->json();

        return [
            'product_id'     => $data['id'] ?? $productId,
            'product_number' => $product->sku,
            'synced_data'    => array_keys($productData),
        ];
    }

    /**
     * Synchronisiert ein Produkt anhand des Export-Profils (WebsiteProfile + sfcc_fields).
     */
    public function syncProductFromProfile(
        PendingRequest $http,
        string $instanceUrl,
        Product $product,
        array $profilePayload,
        array $sfccFields,
        string $language = 'de',
        ?string $categoryId = null,
    ): array {
        $instanceUrl = rtrim($instanceUrl, '/');
        $productId = $product->sku;
        $productData = $this->collectProfileProductData($product, $profilePayload, $sfccFields, $language);

        $response = $http->put(
            "{$instanceUrl}/s/-/dw/data/" . self::API_VERSION . "/products/{$productId}",
            $productData,
        );

        $response->throw();
        $data = $response->json();

        // Kategorie-Zuordnung (separat)
        if ($categoryId) {
            $this->assignProductToCategory($http, $instanceUrl, $productId, $categoryId, $sfccFields);
        }

        return [
            'product_id'     => $data['id'] ?? $productId,
            'product_number' => $product->sku,
            'synced_data'    => array_keys($productData),
        ];
    }

    /**
     * Sammelt Produktdaten fuer SFCC (Legacy-Pfad).
     */
    private function collectProductData(Product $product, string $language, array $options): array
    {
        $data = [
            'id'          => $product->sku,
            'name'        => [$language => $product->name],
            'online_flag' => true,
            'searchable'  => ['default' => true],
        ];

        // EAN
        if (!empty($product->ean)) {
            $data['ean'] = $product->ean;
        }

        // Beschreibung aus Attributen
        $description = $this->getAttributeValueByName($product, 'description-long', $language)
            ?: $this->getAttributeValueByName($product, 'description', $language);
        if ($description) {
            $data['long_description'] = [$language => $description];
        }

        $shortDesc = $this->getAttributeValueByName($product, 'description-short', $language)
            ?: $this->getAttributeValueByName($product, 'short-description', $language);
        if ($shortDesc) {
            $data['short_description'] = [$language => $shortDesc];
        }

        // Marke
        $brand = $this->getAttributeValueByName($product, 'brand', $language)
            ?: $this->getAttributeValueByName($product, 'marke', $language);
        if ($brand) {
            $data['brand'] = $brand;
        }

        return $data;
    }

    /**
     * Sammelt Produktdaten anhand des Profils und der sfcc_fields Konfiguration.
     */
    private function collectProfileProductData(
        Product $product,
        array $profilePayload,
        array $sfccFields,
        string $language,
    ): array {
        $data = [
            'id'   => $product->sku,
            'name' => [$language => $product->name],
        ];

        // Status (online_flag)
        $statusConfig = $sfccFields['status'] ?? ['mode' => 'default'];
        $data['online_flag'] = match ($statusConfig['mode'] ?? 'default') {
            'fixed'     => ($statusConfig['value'] ?? 'true') === 'true',
            'attribute' => $this->resolveAttributeBoolean($product, $statusConfig['attribute_id'] ?? ''),
            default     => true,
        };

        $data['searchable'] = ['default' => true];

        // EAN
        if (!empty($product->ean)) {
            $data['ean'] = $product->ean;
        }

        // Beschreibung
        $descConfig = $sfccFields['description'] ?? ['mode' => 'default'];
        $description = $this->resolveFieldValue($product, $descConfig, $language, 'description');
        if ($description) {
            $data['long_description'] = [$language => $description];
        }

        // Kurzbeschreibung
        $shortDescConfig = $sfccFields['short_description'] ?? ['mode' => 'default'];
        $shortDesc = $this->resolveFieldValue($product, $shortDescConfig, $language, 'short_description');
        if ($shortDesc) {
            $data['short_description'] = [$language => $shortDesc];
        }

        // Marke
        $brandConfig = $sfccFields['brand'] ?? ['mode' => 'default'];
        $brand = $this->resolveFieldValue($product, $brandConfig, $language, 'brand');
        if ($brand) {
            $data['brand'] = $brand;
        }

        // Custom Attributes (c_*)
        $customAttrIds = $sfccFields['_custom_attribute_ids'] ?? [];
        if (!empty($customAttrIds)) {
            $customAttrs = $this->resolveCustomAttributes($product, $customAttrIds, $language);
            if (!empty($customAttrs)) {
                $data['c_attributes'] = $customAttrs;
            }
        }

        return $data;
    }

    /**
     * Weist ein Produkt einer Kategorie im Katalog zu.
     */
    public function assignProductToCategory(
        PendingRequest $http,
        string $instanceUrl,
        string $productId,
        string $categoryId,
        array $sfccFields = [],
    ): void {
        $catalogId = $sfccFields['_catalog_id'] ?? config('connectors.salesforce_commerce.catalog_id', 'storefront-catalog');

        try {
            $http->put(
                "{$instanceUrl}/s/-/dw/data/" . self::API_VERSION
                . "/catalogs/{$catalogId}/categories/{$categoryId}/category_product_assignments/{$productId}",
                [
                    'product_id'  => $productId,
                    'category_id' => $categoryId,
                ],
            )->throw();
        } catch (\Illuminate\Http\Client\RequestException $e) {
            // 409 Conflict = bereits zugeordnet → ignorieren
            if ($e->response?->status() !== 409) {
                throw $e;
            }
        }
    }

    /**
     * Loescht ein Produkt aus SFCC.
     */
    public function deleteProduct(PendingRequest $http, string $instanceUrl, string $productId): bool
    {
        $instanceUrl = rtrim($instanceUrl, '/');

        try {
            $http->delete(
                "{$instanceUrl}/s/-/dw/data/" . self::API_VERSION . "/products/{$productId}",
            )->throw();
            return true;
        } catch (\Illuminate\Http\Client\RequestException $e) {
            if ($e->response?->status() === 404) {
                return true; // Bereits geloescht
            }
            throw $e;
        }
    }

    /**
     * Loest einen Feldwert basierend auf der Mode-Konfiguration auf.
     */
    private function resolveFieldValue(Product $product, array $config, string $language, string $defaultField): ?string
    {
        $mode = $config['mode'] ?? 'default';

        return match ($mode) {
            'fixed'     => $config['value'] ?? null,
            'attribute' => $this->resolveAttributeValue($product, $config['attribute_id'] ?? '', $language),
            default     => $this->getDefaultFieldValue($product, $defaultField, $language),
        };
    }

    /**
     * Loest den Standardwert fuer ein Feld auf (fallback Attribute durchsuchen).
     */
    private function getDefaultFieldValue(Product $product, string $field, string $language): ?string
    {
        $mappings = [
            'description'       => ['description-long', 'description', 'beschreibung'],
            'short_description' => ['description-short', 'short-description', 'kurzbeschreibung'],
            'brand'             => ['brand', 'marke', 'hersteller'],
        ];

        foreach ($mappings[$field] ?? [] as $attrName) {
            $value = $this->getAttributeValueByName($product, $attrName, $language);
            if ($value) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Loest einen Attributwert anhand der Attribut-ID auf.
     */
    private function resolveAttributeValue(Product $product, string $attributeId, string $language): ?string
    {
        if (empty($attributeId)) {
            return null;
        }

        $pav = ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', $attributeId)
            ->first();

        if (!$pav) {
            return null;
        }

        // Dictionary-Werte: Sprache beachten
        if ($pav->value_json) {
            $json = is_string($pav->value_json) ? json_decode($pav->value_json, true) : $pav->value_json;
            return $json[$language] ?? $json['de'] ?? reset($json) ?: null;
        }

        return $pav->value_string ?? ($pav->value_number !== null ? (string) $pav->value_number : null);
    }

    /**
     * Loest einen Boolean-Attributwert auf (fuer online_flag etc.).
     */
    private function resolveAttributeBoolean(Product $product, string $attributeId): bool
    {
        if (empty($attributeId)) {
            return true;
        }

        $pav = ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', $attributeId)
            ->first();

        if (!$pav) {
            return true;
        }

        return (bool) ($pav->value_flag ?? $pav->value_number ?? true);
    }

    /**
     * Loest Custom-Attribute fuer SFCC auf (c_* Felder).
     */
    private function resolveCustomAttributes(Product $product, array $attributeIds, string $language): array
    {
        $result = [];

        $pavs = ProductAttributeValue::where('product_id', $product->id)
            ->whereIn('attribute_id', $attributeIds)
            ->get();

        foreach ($pavs as $pav) {
            $attr = Attribute::find($pav->attribute_id);
            if (!$attr) {
                continue;
            }

            // SFCC Custom-Attribute-Key: c_ + technical_name (Bindestriche → Unterstriche)
            $key = 'c_' . str_replace('-', '_', $attr->technical_name);

            if ($pav->value_json) {
                $json = is_string($pav->value_json) ? json_decode($pav->value_json, true) : $pav->value_json;
                $result[$key] = $json[$language] ?? $json['de'] ?? reset($json) ?: null;
            } elseif ($pav->value_string !== null) {
                $result[$key] = $pav->value_string;
            } elseif ($pav->value_number !== null) {
                $result[$key] = $pav->value_number;
            } elseif ($pav->value_flag !== null) {
                $result[$key] = (bool) $pav->value_flag;
            }
        }

        return $result;
    }

    /**
     * Holt einen Attributwert anhand des technical_name.
     */
    private function getAttributeValueByName(Product $product, string $technicalName, string $language): ?string
    {
        $attr = Attribute::where('technical_name', $technicalName)->first();
        if (!$attr) {
            return null;
        }

        $pav = ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', $attr->id)
            ->first();

        if (!$pav) {
            return null;
        }

        if ($pav->value_json) {
            $json = is_string($pav->value_json) ? json_decode($pav->value_json, true) : $pav->value_json;
            return $json[$language] ?? $json['de'] ?? reset($json) ?: null;
        }

        return $pav->value_string;
    }
}

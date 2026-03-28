<?php

declare(strict_types=1);

namespace App\Services\Connectors\Shopify;

use App\Models\Attribute;
use App\Models\ConnectorSyncLog;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use Illuminate\Http\Client\PendingRequest;

/**
 * Synchronisiert PIM Selection-Attribute als Shopify Metafields.
 *
 * PIM                          → Shopify
 * Attribute (Selection)        → Metafield Definition (namespace: pim)
 * ValueListEntry               → Metafield Value
 * Produkt-Attributwert         → Product Metafield
 */
class ShopifyMetafieldService
{
    private const API_VERSION = '2025-01';

    /**
     * Erstellt die Metafield-Definition-Map für die gegebenen Attribute.
     *
     * Metafield Definitions können in Shopify nur via GraphQL erstellt werden.
     * Diese Methode baut nur die lokale Map auf — Metafields werden dann
     * direkt beim Produkt-Create/Update als Array mitgegeben.
     *
     * @param  string[]  $attributeIds  PIM-Attribut-IDs die als Metafields synchronisiert werden sollen
     * @return array<string, array{key: string, type: string, name: string}>
     *         Map: PIM-Attribut-ID → {key: technical_name, type: metafield_type, name: display_name}
     */
    public function syncMetafieldDefinitions(
        PendingRequest $http,
        string $shopUrl,
        string $connectionId,
        array $attributeIds,
        string $language = 'de',
    ): array {
        $definitionMap = [];

        $attributes = Attribute::whereIn('id', $attributeIds)
            ->where('is_internal', false)
            ->get();

        if ($attributes->isEmpty()) {
            return [];
        }

        foreach ($attributes as $attribute) {
            $metafieldType = $this->mapDataTypeToMetafieldType($attribute);
            $name = $language === 'en' && $attribute->name_en
                ? $attribute->name_en
                : ($attribute->name_de ?: $attribute->technical_name);

            $definitionMap[$attribute->id] = [
                'key'  => $attribute->technical_name,
                'type' => $metafieldType,
                'name' => $name,
            ];
        }

        return $definitionMap;
    }

    /**
     * Ermittelt die Metafield-Werte für ein Produkt basierend auf dessen Attributwerten.
     *
     * @param  array<string, array{key: string, type: string}>  $definitionMap
     * @return array<array{namespace: string, key: string, value: string, type: string}>
     */
    public function resolveProductMetafields(
        Product $product,
        array $definitionMap,
        string $language = 'de',
    ): array {
        if (empty($definitionMap)) {
            return [];
        }

        $attributeIds = array_keys($definitionMap);
        $metafields = [];

        $values = ProductAttributeValue::where('product_id', $product->id)
            ->whereIn('attribute_id', $attributeIds)
            ->with(['attribute', 'valueListEntry', 'unit'])
            ->get();

        foreach ($values as $av) {
            $attr = $av->attribute;
            if (!$attr || $attr->is_internal) {
                continue;
            }

            $def = $definitionMap[$attr->id] ?? null;
            if (!$def) {
                continue;
            }

            $value = $this->resolveMetafieldValue($av, $attr, $language);
            if ($value === null || $value === '') {
                continue;
            }

            $metafields[] = [
                'namespace' => 'pim',
                'key'       => $def['key'],
                'value'     => $value,
                'type'      => $def['type'],
            ];
        }

        return $metafields;
    }

    /**
     * Ermittelt die Metafield-Werte für mehrere Produkte in einem Batch (Performance).
     *
     * @param  array<string>  $productIds
     * @param  array<string, array{key: string, type: string}>  $definitionMap
     * @return array<string, array<array{namespace: string, key: string, value: string, type: string}>>
     */
    public function resolveProductMetafieldsBulk(
        array $productIds,
        array $definitionMap,
        string $language = 'de',
    ): array {
        if (empty($definitionMap) || empty($productIds)) {
            return [];
        }

        $attributeIds = array_keys($definitionMap);

        $values = ProductAttributeValue::whereIn('product_id', $productIds)
            ->whereIn('attribute_id', $attributeIds)
            ->with(['attribute', 'valueListEntry', 'unit'])
            ->get()
            ->groupBy('product_id');

        $result = [];
        foreach ($productIds as $productId) {
            $productValues = $values->get($productId, collect());
            $metafields = [];

            foreach ($productValues as $av) {
                $attr = $av->attribute;
                if (!$attr || $attr->is_internal) {
                    continue;
                }

                $def = $definitionMap[$attr->id] ?? null;
                if (!$def) {
                    continue;
                }

                $value = $this->resolveMetafieldValue($av, $attr, $language);
                if ($value === null || $value === '') {
                    continue;
                }

                $metafields[] = [
                    'namespace' => 'pim',
                    'key'       => $def['key'],
                    'value'     => $value,
                    'type'      => $def['type'],
                ];
            }

            $result[$productId] = $metafields;
        }

        return $result;
    }

    /**
     * Löst einen Attributwert als Metafield-Value auf.
     */
    private function resolveMetafieldValue($attrValue, $attr, string $lang): ?string
    {
        return match ($attr->data_type) {
            'String' => $attrValue->value_string,
            'Number' => $attrValue->value_number !== null
                ? (string) (int) $attrValue->value_number
                : null,
            'Float' => $attrValue->value_number !== null
                ? rtrim(rtrim((string) $attrValue->value_number, '0'), '.')
                : null,
            'Date' => $attrValue->value_date?->format('Y-m-d'),
            'Flag' => $attrValue->value_flag !== null
                ? ($attrValue->value_flag ? 'true' : 'false')
                : null,
            'Selection' => $this->resolveSelectionValue($attrValue, $lang),
            'Dictionary' => $this->resolveDictionaryValue($attrValue, $lang),
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

    private function resolveDictionaryValue($attrValue, string $lang): ?string
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
     * Mappt PIM-Datentypen auf Shopify Metafield-Typen.
     */
    private function mapDataTypeToMetafieldType(Attribute $attribute): string
    {
        return match ($attribute->data_type) {
            'Number'    => 'number_integer',
            'Float'     => 'number_decimal',
            'Date'      => 'date',
            'Flag'      => 'boolean',
            'Selection' => 'single_line_text_field',
            default     => 'single_line_text_field',
        };
    }
}

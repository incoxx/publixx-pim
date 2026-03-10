<?php

declare(strict_types=1);

namespace App\Services\Import;

/**
 * Element-Name-Mapping für BMEcat 1.2 vs 2005.
 *
 * BMEcat 1.2 und 2005 verwenden unterschiedliche XML-Elementnamen für die gleichen Konzepte.
 * Diese Klasse liefert ein assoziatives Array mit kanonischen Schlüsseln, das den Parser
 * von der konkreten Version entkoppelt.
 */
class BmecatElementMap
{
    /**
     * Liefert das Element-Mapping für die angegebene BMEcat-Version.
     *
     * @return array<string, string> Kanonischer Schlüssel → XML-Elementname
     */
    public static function forVersion(string $version): array
    {
        $shared = [
            // Header & Catalog
            'header' => 'HEADER',
            'catalog' => 'CATALOG',
            'language' => 'LANGUAGE',
            'catalog_id' => 'CATALOG_ID',
            'catalog_version' => 'CATALOG_VERSION',
            'catalog_name' => 'CATALOG_NAME',
            'currency' => 'CURRENCY',
            'supplier' => 'SUPPLIER',
            'supplier_name' => 'SUPPLIER_NAME',

            // Transaction
            't_new_catalog' => 'T_NEW_CATALOG',

            // Catalog Group System (Hierarchie)
            'catalog_group_system' => 'CATALOG_GROUP_SYSTEM',
            'group_system_id' => 'GROUP_SYSTEM_ID',
            'group_system_name' => 'GROUP_SYSTEM_NAME',
            'catalog_structure' => 'CATALOG_STRUCTURE',
            'group_id' => 'GROUP_ID',
            'group_name' => 'GROUP_NAME',
            'group_description' => 'GROUP_DESCRIPTION',
            'parent_id' => 'PARENT_ID',
            'group_order' => 'GROUP_ORDER',

            // Features
            'feature' => 'FEATURE',
            'fname' => 'FNAME',
            'fvalue' => 'FVALUE',
            'funit' => 'FUNIT',
            'forder' => 'FORDER',
            'fdescr' => 'FDESCR',
            'fvalue_type' => 'FVALUE_TYPE',
            'reference_feature_system_name' => 'REFERENCE_FEATURE_SYSTEM_NAME',
            'reference_feature_group_id' => 'REFERENCE_FEATURE_GROUP_ID',
            'reference_feature_group_name' => 'REFERENCE_FEATURE_GROUP_NAME',

            // Details (shared)
            'description_short' => 'DESCRIPTION_SHORT',
            'description_long' => 'DESCRIPTION_LONG',
            'ean' => 'EAN',
            'manufacturer_name' => 'MANUFACTURER_NAME',
            'manufacturer_pid' => 'MANUFACTURER_PID',
            'delivery_time' => 'DELIVERY_TIME',
            'keyword' => 'KEYWORD',

            // Order Details (shared)
            'order_unit' => 'ORDER_UNIT',
            'content_unit' => 'CONTENT_UNIT',
            'no_cu_per_ou' => 'NO_CU_PER_OU',
            'quantity_min' => 'QUANTITY_MIN',
            'quantity_interval' => 'QUANTITY_INTERVAL',
            'price_quantity' => 'PRICE_QUANTITY',

            // Price
            'price_amount' => 'PRICE_AMOUNT',
            'price_currency' => 'PRICE_CURRENCY',
            'tax' => 'TAX',
            'price_factor' => 'PRICE_FACTOR',
            'lower_bound' => 'LOWER_BOUND',
            'territory' => 'TERRITORY',
            'datetime' => 'DATETIME',
            'daily_price' => 'DAILY_PRICE',

            // MIME
            'mime_info' => 'MIME_INFO',
            'mime' => 'MIME',
            'mime_type' => 'MIME_TYPE',
            'mime_source' => 'MIME_SOURCE',
            'mime_descr' => 'MIME_DESCR',
            'mime_alt' => 'MIME_ALT',
            'mime_purpose' => 'MIME_PURPOSE',
            'mime_order' => 'MIME_ORDER',

            // Catalog Group Map
            'catalog_group_id' => 'CATALOG_GROUP_ID',
        ];

        if (self::isVersion12($version)) {
            return array_merge($shared, [
                'product' => 'ARTICLE',
                'supplier_pid' => 'SUPPLIER_AID',
                'product_details' => 'ARTICLE_DETAILS',
                'product_features' => 'ARTICLE_FEATURES',
                'product_order_details' => 'ARTICLE_ORDER_DETAILS',
                'product_price_details' => 'ARTICLE_PRICE_DETAILS',
                'product_price' => 'ARTICLE_PRICE',
                'product_reference' => 'ARTICLE_REFERENCE',
                'prod_id_to' => 'ART_ID_TO',
                'product_to_cataloggroup_map' => 'ARTICLE_TO_CATALOGGROUP_MAP',
                'prod_id' => 'ART_ID',
                'product_order' => 'ARTICLE_TO_CATALOGGROUP_MAP_ORDER',
                'supplier_alt_pid' => 'SUPPLIER_ALT_AID',
                'buyer_pid' => 'BUYER_AID',
            ]);
        }

        // BMEcat 2005 (default)
        return array_merge($shared, [
            'product' => 'PRODUCT',
            'supplier_pid' => 'SUPPLIER_PID',
            'product_details' => 'PRODUCT_DETAILS',
            'product_features' => 'PRODUCT_FEATURES',
            'product_order_details' => 'PRODUCT_ORDER_DETAILS',
            'product_price_details' => 'PRODUCT_PRICE_DETAILS',
            'product_price' => 'PRODUCT_PRICE',
            'product_reference' => 'PRODUCT_REFERENCE',
            'prod_id_to' => 'PROD_ID_TO',
            'product_to_cataloggroup_map' => 'PRODUCT_TO_CATALOGGROUP_MAP',
            'prod_id' => 'PROD_ID',
            'product_order' => 'PRODUCT_ORDER',
            'supplier_alt_pid' => 'SUPPLIER_ALT_PID',
            'buyer_pid' => 'BUYER_PID',
        ]);
    }

    /**
     * Prüft, ob die Version BMEcat 1.2 ist.
     */
    public static function isVersion12(string $version): bool
    {
        return str_starts_with($version, '1.2');
    }

    /**
     * Liefert alle unterstützten BMEcat-Versionen.
     *
     * @return string[]
     */
    public static function supportedVersions(): array
    {
        return ['1.2', '2005', '2005.1', '2005.2'];
    }
}

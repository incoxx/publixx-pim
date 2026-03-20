<?php

declare(strict_types=1);

namespace App\Services\Connectors\Shopware;

use App\Models\Product;
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
     * Sammelt Produktdaten für das Shopware-Format.
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

            // Beschreibung wird als description-Feld gemappt
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
                        'currencyId' => $options['currency_id'] ?? 'b7d2554b0ce847cd82f3ac9bd1c0dfca', // EUR default
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

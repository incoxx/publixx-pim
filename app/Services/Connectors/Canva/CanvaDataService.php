<?php

declare(strict_types=1);

namespace App\Services\Connectors\Canva;

use App\Models\Product;
use App\Models\ProductAttributeValue;
use Illuminate\Http\Client\PendingRequest;

class CanvaDataService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('connectors.canva.base_url');
    }

    /**
     * Sammelt Produktdaten für die Übertragung an Canva.
     *
     * @param  array  $options  ['language' => 'de', 'attributes' => [...], 'include_prices' => true, 'include_media' => true]
     */
    public function collectProductData(Product $product, array $options = []): array
    {
        $language = $options['language'] ?? 'de';
        $includeAttributes = $options['attributes'] ?? null;
        $includePrices = $options['include_prices'] ?? true;
        $includeMedia = $options['include_media'] ?? true;

        $data = [
            'sku'  => $product->sku,
            'ean'  => $product->ean,
            'name' => $product->name,
        ];

        // Attributwerte sammeln
        $query = $product->attributeValues()
            ->with('attribute')
            ->where('language', $language);

        if ($includeAttributes) {
            $query->whereHas('attribute', function ($q) use ($includeAttributes) {
                $q->whereIn('code', $includeAttributes);
            });
        }

        foreach ($query->get() as $attributeValue) {
            $code = $attributeValue->attribute->code ?? null;
            if ($code) {
                $data['attributes'][$code] = $this->resolveAttributeValue($attributeValue);
            }
        }

        // Preise
        if ($includePrices) {
            $data['prices'] = $product->prices()
                ->with(['priceType', 'priceRegion'])
                ->get()
                ->map(fn ($price) => [
                    'type'   => $price->priceType?->name,
                    'region' => $price->priceRegion?->name,
                    'value'  => $price->value,
                    'currency' => $price->priceRegion?->currency ?? 'EUR',
                ])
                ->toArray();
        }

        // Media-URLs
        if ($includeMedia) {
            $data['media'] = $product->media()
                ->orderByPivot('sort_order')
                ->get()
                ->map(fn ($media) => [
                    'id'        => $media->id,
                    'file_name' => $media->file_name,
                    'mime_type' => $media->mime_type,
                    'url'       => url("/api/v1/media/file/{$media->file_name}"),
                    'title'     => $media->{"title_{$language}"} ?? $media->title_de,
                ])
                ->toArray();
        }

        return $data;
    }

    /**
     * Erstellt ein Canva-Design mit Autofill-Daten aus dem Produkt.
     */
    public function createDesignFromProduct(PendingRequest $http, array $productData, ?string $brandTemplateId = null): array
    {
        $payload = [
            'title' => $productData['name'] ?? $productData['sku'] ?? 'PIM-Produkt',
        ];

        // Wenn Brand-Template vorhanden, Autofill nutzen
        if ($brandTemplateId) {
            $payload['brand_template_id'] = $brandTemplateId;
            $payload['data'] = $this->mapToCanvaAutofillData($productData);
        }

        $response = $http->post("{$this->baseUrl}/designs", $payload);
        $response->throw();

        return $response->json();
    }

    /**
     * Mapped Produktdaten auf Canva Autofill-Format.
     */
    private function mapToCanvaAutofillData(array $productData): array
    {
        $data = [];

        // Textfelder
        if (isset($productData['name'])) {
            $data['product_name'] = ['type' => 'text', 'text' => $productData['name']];
        }
        if (isset($productData['sku'])) {
            $data['product_sku'] = ['type' => 'text', 'text' => $productData['sku']];
        }

        // Attribute als Textfelder
        foreach ($productData['attributes'] ?? [] as $code => $value) {
            if (is_string($value) || is_numeric($value)) {
                $data["attr_{$code}"] = ['type' => 'text', 'text' => (string) $value];
            }
        }

        // Erster Preis
        if (! empty($productData['prices'])) {
            $price = $productData['prices'][0];
            $data['product_price'] = [
                'type' => 'text',
                'text' => number_format($price['value'], 2, ',', '.') . ' ' . ($price['currency'] ?? 'EUR'),
            ];
        }

        // Erstes Bild
        if (! empty($productData['media'])) {
            $data['product_image'] = [
                'type'      => 'image',
                'asset_url' => $productData['media'][0]['url'],
            ];
        }

        return $data;
    }

    /**
     * Löst den Wert eines ProductAttributeValue auf.
     */
    private function resolveAttributeValue(ProductAttributeValue $attributeValue): mixed
    {
        if ($attributeValue->value_string !== null) {
            return $attributeValue->value_string;
        }
        if ($attributeValue->value_number !== null) {
            return $attributeValue->value_number;
        }
        if ($attributeValue->value_date !== null) {
            return $attributeValue->value_date;
        }
        if ($attributeValue->value_flag !== null) {
            return (bool) $attributeValue->value_flag;
        }

        return null;
    }
}

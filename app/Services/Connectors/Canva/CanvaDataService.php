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

        // Attributwerte sammeln (inkl. Selection-Werte und Einheiten)
        $query = $product->attributeValues()
            ->with(['attribute', 'valueListEntry', 'unit'])
            ->where('language', $language);

        if ($includeAttributes) {
            $query->whereHas('attribute', function ($q) use ($includeAttributes) {
                // Unterstützt sowohl UUIDs (IDs) als auch technical_names
                $isUuid = preg_match('/^[0-9a-f]{8}-/', $includeAttributes[0] ?? '');
                $q->whereIn($isUuid ? 'id' : 'technical_name', $includeAttributes);
            });
        }

        foreach ($query->get() as $attributeValue) {
            $key = $attributeValue->attribute->technical_name ?? null;
            if ($key) {
                $data['attributes'][$key] = $this->resolveAttributeValue($attributeValue);
            }
        }

        // Preise (Spalte heißt 'amount', currency liegt auf ProductPrice)
        if ($includePrices) {
            $data['prices'] = $product->prices()
                ->with(['priceType', 'priceRegion'])
                ->get()
                ->map(fn ($price) => [
                    'type'     => $price->priceType?->name,
                    'region'   => $price->priceRegion?->name,
                    'value'    => (float) $price->amount,
                    'currency' => $price->currency ?? 'EUR',
                ])
                ->toArray();
        }

        // Media-URLs (nur title_de und title_en vorhanden)
        if ($includeMedia) {
            $titleField = in_array($language, ['de', 'en']) ? "title_{$language}" : 'title_de';
            $data['media'] = $product->media()
                ->orderByPivot('sort_order')
                ->get()
                ->map(fn ($media) => [
                    'id'        => $media->id,
                    'file_name' => $media->file_name,
                    'mime_type' => $media->mime_type,
                    'url'       => url("/api/v1/media/file/{$media->file_name}"),
                    'title'     => $media->{$titleField} ?? $media->title_de,
                ])
                ->toArray();
        }

        return $data;
    }

    /**
     * Erstellt ein Canva-Design mit Produktdaten.
     *
     * Ohne Brand-Template: Erstellt ein leeres Design (A4-Format).
     * Mit Brand-Template: Erstellt ein Autofill-Design (Enterprise).
     */
    public function createDesignFromProduct(PendingRequest $http, array $productData, ?string $brandTemplateId = null): array
    {
        $title = $productData['name'] ?? $productData['sku'] ?? 'PIM-Produkt';

        // Mit Brand-Template: Autofill-Endpunkt (Enterprise)
        if ($brandTemplateId) {
            return $this->createAutofillDesign($http, $brandTemplateId, $productData, $title);
        }

        // Ohne Template: Normales Design erstellen (A4 Hochformat)
        $response = $http->post("{$this->baseUrl}/designs", [
            'design_type' => [
                'type'   => 'custom',
                'width'  => 2480,
                'height' => 3508,
            ],
            'title' => $title,
        ]);
        $response->throw();

        return $response->json();
    }

    /**
     * Erstellt ein Design via Autofill-API (erfordert Canva Enterprise).
     */
    private function createAutofillDesign(PendingRequest $http, string $brandTemplateId, array $productData, string $title): array
    {
        $response = $http->post("{$this->baseUrl}/autofills", [
            'brand_template_id' => $brandTemplateId,
            'title'             => $title,
            'data'              => $this->mapToCanvaAutofillData($productData),
        ]);
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

        // Erstes Bild (erfordert eine vorherige Asset-Upload-ID aus Canva)
        if (! empty($productData['media'][0]['canva_asset_id'])) {
            $data['product_image'] = [
                'type'     => 'image',
                'asset_id' => $productData['media'][0]['canva_asset_id'],
            ];
        }

        return $data;
    }

    /**
     * Löst den Wert eines ProductAttributeValue auf.
     * Berücksichtigt Selection-Werte, Einheiten und alle Werttypen.
     */
    private function resolveAttributeValue(ProductAttributeValue $attributeValue): mixed
    {
        // Selection-Wert (Auswahlliste)
        if ($attributeValue->value_selection_id !== null) {
            return $attributeValue->valueListEntry?->value
                ?? $attributeValue->valueListEntry?->name
                ?? $attributeValue->value_selection_id;
        }

        // Zahlenwert mit optionaler Einheit
        if ($attributeValue->value_number !== null) {
            $unit = $attributeValue->unit?->abbreviation ?? $attributeValue->unit?->name;
            if ($unit) {
                return $attributeValue->value_number . ' ' . $unit;
            }
            return $attributeValue->value_number;
        }

        if ($attributeValue->value_string !== null) {
            return $attributeValue->value_string;
        }
        if ($attributeValue->value_date !== null) {
            return $attributeValue->value_date;
        }
        if ($attributeValue->value_flag !== null) {
            return (bool) $attributeValue->value_flag ? 'Ja' : 'Nein';
        }

        return null;
    }
}

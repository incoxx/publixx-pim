<?php

declare(strict_types=1);

namespace App\Services\Connectors\DeepL;

use App\Models\Product;
use App\Models\ProductAttributeValue;
use Illuminate\Support\Facades\Http;

class DeepLTranslationService
{
    /**
     * Übersetzt alle Text-Attribute eines Produkts.
     *
     * @param  string[]|null  $attributeCodes  Nur bestimmte Attribute übersetzen
     * @return array{translations: array, usage: array}
     */
    public function translateProduct(
        string $apiKey,
        Product $product,
        string $sourceLang,
        string $targetLang,
        ?array $attributeCodes = null,
    ): array {
        $query = $product->attributeValues()
            ->with('attribute')
            ->where('language', strtolower($sourceLang))
            ->whereNotNull('value_string');

        if ($attributeCodes) {
            $query->whereHas('attribute', fn ($q) => $q->whereIn('code', $attributeCodes));
        }

        $attributeValues = $query->get();
        $texts = [];
        $mapping = [];

        foreach ($attributeValues as $av) {
            $code = $av->attribute->code ?? null;
            if ($code && ! empty($av->value_string)) {
                $texts[] = $av->value_string;
                $mapping[] = [
                    'attribute_code' => $code,
                    'attribute_value_id' => $av->id,
                ];
            }
        }

        if (empty($texts)) {
            return ['translations' => [], 'usage' => ['character_count' => 0]];
        }

        $translated = $this->translateTexts($apiKey, $texts, $sourceLang, $targetLang);

        $results = [];
        foreach ($translated as $i => $translatedText) {
            $info = $mapping[$i];
            $results[] = [
                'attribute_code' => $info['attribute_code'],
                'original'       => $texts[$i],
                'translated'     => $translatedText,
                'target_language' => strtolower($targetLang),
            ];

            // Save translation as new attribute value in target language
            ProductAttributeValue::updateOrCreate(
                [
                    'product_id'   => $product->id,
                    'attribute_id' => $attributeValues[$i]->attribute_id,
                    'language'     => strtolower($targetLang),
                ],
                [
                    'value_string' => $translatedText,
                ],
            );
        }

        return [
            'translations' => $results,
            'usage'        => ['character_count' => array_sum(array_map('mb_strlen', $texts))],
        ];
    }

    /**
     * Übersetzt einen einzelnen Text.
     */
    public function translateText(string $apiKey, string $text, string $sourceLang, string $targetLang): string
    {
        $results = $this->translateTexts($apiKey, [$text], $sourceLang, $targetLang);

        return $results[0] ?? $text;
    }

    /**
     * Übersetzt mehrere Texte in einem API-Call.
     *
     * @return string[]
     */
    public function translateTexts(string $apiKey, array $texts, string $sourceLang, string $targetLang): array
    {
        $baseUrl = config('connectors.deepl.base_url', 'https://api-free.deepl.com/v2');

        $response = Http::withHeaders([
            'Authorization' => "DeepL-Auth-Key {$apiKey}",
        ])
            ->timeout(30)
            ->post("{$baseUrl}/translate", [
                'text'        => $texts,
                'source_lang' => $sourceLang,
                'target_lang' => $targetLang,
                'tag_handling' => 'html',
            ]);

        $response->throw();
        $data = $response->json();

        return array_map(
            fn ($t) => $t['text'],
            $data['translations'] ?? [],
        );
    }

    /**
     * Prüft den API-Verbrauch.
     */
    public function getUsage(string $apiKey): array
    {
        $baseUrl = config('connectors.deepl.base_url', 'https://api-free.deepl.com/v2');

        $response = Http::withHeaders([
            'Authorization' => "DeepL-Auth-Key {$apiKey}",
        ])
            ->timeout(10)
            ->get("{$baseUrl}/usage");

        $response->throw();

        return $response->json();
    }
}

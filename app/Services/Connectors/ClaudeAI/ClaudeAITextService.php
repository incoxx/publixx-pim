<?php

declare(strict_types=1);

namespace App\Services\Connectors\ClaudeAI;

use App\Models\Product;
use App\Models\ProductAttributeValue;
use Illuminate\Support\Facades\Http;

class ClaudeAITextService
{
    private const TASK_PROMPTS = [
        'description' => 'Erstelle eine professionelle Produktbeschreibung basierend auf den folgenden Produktdaten. Die Beschreibung soll informativ, ansprechend und für einen Online-Shop geeignet sein.',
        'seo'         => 'Erstelle einen SEO-optimierten Text für das folgende Produkt. Generiere: 1) Meta-Title (max 60 Zeichen), 2) Meta-Description (max 155 Zeichen), 3) SEO-Text (200-300 Wörter) mit relevanten Keywords.',
        'features'    => 'Erstelle eine strukturierte Auflistung der wichtigsten Produktmerkmale und Vorteile basierend auf den folgenden Produktdaten. Verwende kurze, prägnante Bullet-Points.',
        'marketing'   => 'Erstelle einen überzeugenden Marketing-Text für das folgende Produkt. Der Text soll emotional ansprechen und zum Kauf motivieren.',
    ];

    /**
     * Generiert KI-Text für ein Produkt.
     *
     * @return array{text: string, task: string, usage: array}
     */
    public function generateProductText(
        string $apiKey,
        Product $product,
        string $language,
        string $task = 'description',
        array $options = [],
    ): array {
        $productContext = $this->collectProductContext($product, $language);
        $prompt = $this->buildPrompt($productContext, $task, $language, $options);
        $model = $options['model'] ?? config('connectors.claude_ai.model', 'claude-sonnet-4-20250514');
        $maxTokens = $options['max_tokens'] ?? config('connectors.claude_ai.max_tokens', 1024);

        $response = Http::withHeaders([
            'x-api-key'         => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        ])
            ->timeout(60)
            ->post(config('connectors.claude_ai.base_url', 'https://api.anthropic.com/v1') . '/messages', [
                'model'      => $model,
                'max_tokens' => $maxTokens,
                'messages'   => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        $response->throw();
        $data = $response->json();

        $text = $data['content'][0]['text'] ?? '';

        // Optionally save generated text as attribute value
        if ($options['save_as_attribute'] ?? false) {
            $attributeCode = $options['target_attribute'] ?? "ai_{$task}";
            $this->saveGeneratedText($product, $language, $attributeCode, $text);
        }

        return [
            'text'  => $text,
            'task'  => $task,
            'model' => $model,
            'usage' => [
                'input_tokens'  => $data['usage']['input_tokens'] ?? 0,
                'output_tokens' => $data['usage']['output_tokens'] ?? 0,
            ],
        ];
    }

    /**
     * Sammelt Produktdaten als Kontext für die KI.
     */
    private function collectProductContext(Product $product, string $language): array
    {
        $context = [
            'name'  => $product->name,
            'sku'   => $product->sku,
            'ean'   => $product->ean,
        ];

        // Attribute sammeln
        $attributes = $product->attributeValues()
            ->with('attribute')
            ->where('language', $language)
            ->get();

        foreach ($attributes as $av) {
            $code = $av->attribute->code ?? null;
            if ($code) {
                $context['attributes'][$code] = $this->resolveValue($av);
            }
        }

        // Preise
        $prices = $product->prices()->with(['priceType', 'priceRegion'])->get();
        if ($prices->isNotEmpty()) {
            $context['prices'] = $prices->map(fn ($p) => [
                'type'     => $p->priceType?->name,
                'value'    => $p->value,
                'currency' => $p->priceRegion?->currency ?? 'EUR',
            ])->toArray();
        }

        // Hersteller
        if ($product->manufacturer) {
            $context['manufacturer'] = $product->manufacturer->name;
        }

        return $context;
    }

    private function buildPrompt(array $context, string $task, string $language, array $options): string
    {
        $taskPrompt = self::TASK_PROMPTS[$task] ?? self::TASK_PROMPTS['description'];

        if ($options['custom_prompt'] ?? null) {
            $taskPrompt = $options['custom_prompt'];
        }

        $langName = match ($language) {
            'de' => 'Deutsch',
            'en' => 'Englisch',
            'fr' => 'Französisch',
            'es' => 'Spanisch',
            'it' => 'Italienisch',
            default => $language,
        };

        $contextText = "Produktname: {$context['name']}\n";
        $contextText .= "SKU: {$context['sku']}\n";

        if (! empty($context['manufacturer'])) {
            $contextText .= "Hersteller: {$context['manufacturer']}\n";
        }

        if (! empty($context['attributes'])) {
            $contextText .= "\nAttribute:\n";
            foreach ($context['attributes'] as $code => $value) {
                if (is_string($value) || is_numeric($value)) {
                    $contextText .= "- {$code}: {$value}\n";
                }
            }
        }

        if (! empty($context['prices'])) {
            $price = $context['prices'][0];
            $contextText .= "\nPreis: {$price['value']} {$price['currency']}\n";
        }

        return "{$taskPrompt}\n\nSprache: {$langName}\n\nProduktdaten:\n{$contextText}";
    }

    private function saveGeneratedText(Product $product, string $language, string $attributeCode, string $text): void
    {
        $attribute = \App\Models\Attribute::where('code', $attributeCode)->first();
        if (! $attribute) {
            return;
        }

        ProductAttributeValue::updateOrCreate(
            [
                'product_id'   => $product->id,
                'attribute_id' => $attribute->id,
                'language'     => $language,
            ],
            [
                'value_string' => $text,
            ],
        );
    }

    private function resolveValue(ProductAttributeValue $av): mixed
    {
        return $av->value_string
            ?? $av->value_number
            ?? $av->value_date
            ?? ($av->value_flag !== null ? (bool) $av->value_flag : null);
    }
}

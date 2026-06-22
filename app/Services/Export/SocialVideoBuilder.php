<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Models\Product;
use App\Models\PublixxExportMapping;
use App\Services\Connectors\ClaudeAI\ClaudeAITextService;
use Illuminate\Support\Facades\Log;

/**
 * Erzeugt aus 1..n Produkten eine Reel-Definition für Social-Media-Videos.
 *
 * Datenfluss: Produkt → MappingResolver (media:/attribute:/prices:) → optional KI-Hook
 * (ClaudeAITextService) → Szenen (Hero, Feature, Preis, CTA). Die Reel-Definition ist
 * die Schnittstelle zur Video-Engine (video-engine/src/reel-cli.ts).
 */
class SocialVideoBuilder
{
    /** @var string[] Produkt-IDs (in Reihenfolge der Auswahl). */
    private array $productIds = [];

    private array $mappingRules = [];

    /** @var string[] */
    private array $languages = ['de'];

    private string $format = '9x16';

    private string $template = 'default';

    private bool $useAi = false;

    private array $voice = [
        'lang'     => 'de',
        'gender'   => 'female',
        'provider' => 'elevenlabs',
    ];

    /**
     * Look & Stil des Reels. brief/tonality steuern den KI-Text,
     * accent/background die Farben, transition den Szenen-Übergang.
     */
    private array $style = [
        'brief'      => '',
        'tonality'   => '',
        'accent'     => '#06b6d4',
        'background' => '#0f0f23',
        'transition' => 'fade', // fade | slide | zoom | cut
    ];

    public function __construct(
        private readonly MappingResolver $mappingResolver,
        private readonly ClaudeAITextService $claude,
    ) {
        $this->mappingRules = SocialVideoElementMap::defaultMappingRules();
    }

    // --- Setter ---

    /** @param string[] $ids */
    public function setProductIds(array $ids): void
    {
        $this->productIds = array_values(array_filter($ids));
    }

    public function setMappingRules(array $rules): void
    {
        if (! empty($rules)) {
            $this->mappingRules = $rules;
        }
    }

    public function setMappingId(string $id): void
    {
        $mapping = PublixxExportMapping::findOrFail($id);
        $rules = $mapping->mapping_rules['rules'] ?? $mapping->mapping_rules ?? [];
        $this->setMappingRules($rules);
        if (! empty($mapping->languages)) {
            $this->languages = $mapping->languages;
        }
    }

    /** @param string[] $languages */
    public function setLanguages(array $languages): void
    {
        if (! empty($languages)) {
            $this->languages = $languages;
            $this->voice['lang'] = $languages[0];
        }
    }

    public function setFormat(string $format): void { $this->format = $format; }
    public function setTemplate(string $template): void { $this->template = $template; }
    public function setUseAi(bool $useAi): void { $this->useAi = $useAi; }

    /**
     * Setzt das Stil-Briefing (nur bekannte Felder werden übernommen).
     */
    public function setStyle(array $style): void
    {
        foreach (['brief', 'tonality', 'accent', 'background', 'transition'] as $key) {
            if (array_key_exists($key, $style) && $style[$key] !== null && $style[$key] !== '') {
                $this->style[$key] = (string) $style[$key];
            }
        }
    }

    // --- Hauptmethode ---

    /**
     * Baut die vollständige Reel-Definition (ein Video für alle gewählten Produkte).
     */
    public function build(): array
    {
        $products = $this->loadProducts();
        $scenes = [];

        foreach ($products as $product) {
            foreach ($this->buildProductScenes($product) as $scene) {
                $scenes[] = $scene;
            }
        }

        // Abschluss-Szene (Call-to-Action)
        $scenes[] = [
            'type'     => 'cta',
            'headline' => SocialVideoElementMap::fieldDefaults()['cta'],
            'sprecher' => 'Jetzt entdecken auf incoxx.com.',
            'duration' => 2500,
        ];

        return [
            'meta' => [
                'id'        => 'reel-' . now()->format('Ymd-His'),
                'format'    => $this->format,
                'viewport'  => $this->viewportFor($this->format),
                'voice'     => $this->voice,
                'template'  => $this->template,
                'style'     => $this->style,
                'languages' => $this->languages,
                'product_count' => count($products),
            ],
            'scenes' => $scenes,
        ];
    }

    /**
     * Szenen für ein einzelnes Produkt: Hero → bis zu 2 Features → Preis.
     */
    private function buildProductScenes(Product $product): array
    {
        $mapped = $this->mappingResolver->resolve($this->mappingRules, $product, $this->languages);

        $headline = $mapped['headline'] ?? $product->name ?? $product->sku;
        $hero     = $mapped['hero_image'] ?? ($mapped['gallery'][0] ?? null);
        $hookText = $this->useAi ? $this->generateHook($product, $headline) : ($mapped['subline'] ?? $headline);

        $scenes = [[
            'type'     => 'hero',
            'image'    => $hero,
            'headline' => $headline,
            'subline'  => $mapped['subline'] ?? null,
            'sprecher' => $hookText,
            'duration' => 3000,
        ]];

        // Bis zu zwei Feature-Szenen aus den gemappten Merkmalen
        $features = array_values(array_filter([
            $mapped['feature_1'] ?? null,
            $mapped['feature_2'] ?? null,
            $mapped['feature_3'] ?? null,
        ]));
        $galleryImages = is_array($mapped['gallery'] ?? null) ? $mapped['gallery'] : [];

        foreach (array_slice($features, 0, 2) as $i => $feature) {
            $scenes[] = [
                'type'     => 'feature',
                'image'    => $galleryImages[$i] ?? $hero,
                'headline' => $feature,
                'sprecher' => $feature,
                'duration' => 2500,
            ];
        }

        // Preis-Szene (nur wenn ein Preis gemappt ist)
        if (isset($mapped['price']) && $mapped['price'] !== null) {
            $scenes[] = [
                'type'     => 'price',
                'value'    => (float) $mapped['price'],
                'currency' => 'EUR',
                'sprecher' => 'Jetzt für nur ' . number_format((float) $mapped['price'], 0, ',', '.') . ' Euro.',
                'duration' => 2500,
            ];
        }

        return $scenes;
    }

    /**
     * Kurzer, knackiger KI-Hook (ein Satz). Fällt bei Fehlern auf die Headline zurück.
     */
    private function generateHook(Product $product, string $fallback): string
    {
        $apiKey = (string) config('connectors.claude_ai.api_key', '');
        if ($apiKey === '') {
            return $fallback;
        }

        $prompt = 'Schreibe einen einzigen, knackigen Social-Media-Hook (max. 12 Wörter, '
            . 'ohne Hashtags, ohne Anführungszeichen) für ein Produktvideo zu diesem Produkt.';
        if (trim($this->style['brief']) !== '') {
            $prompt .= "\n\nKreativ-Briefing für das Video: " . trim($this->style['brief']);
        }
        $tonality = trim($this->style['tonality']) !== '' ? trim($this->style['tonality']) : 'jung, energiegeladen, direkt';

        try {
            $result = $this->claude->generateProductText($apiKey, $product, $this->languages[0], 'marketing', [
                'custom_prompt' => $prompt,
                'tonality'      => $tonality,
                'max_tokens'    => 80,
            ]);
            $text = trim((string) ($result['text'] ?? ''));

            return $text !== '' ? $this->firstLine($text) : $fallback;
        } catch (\Throwable $e) {
            Log::channel('export')->warning('Social-Video KI-Hook fehlgeschlagen, nutze Fallback', [
                'product_id' => $product->id,
                'error'      => $e->getMessage(),
            ]);

            return $fallback;
        }
    }

    private function firstLine(string $text): string
    {
        $line = trim(strtok($text, "\n"));

        return trim($line, " \"'");
    }

    /**
     * @return \Illuminate\Support\Collection<int, Product>
     */
    private function loadProducts()
    {
        if (empty($this->productIds)) {
            return collect();
        }

        $products = Product::query()
            ->whereIn('id', $this->productIds)
            ->with([
                'attributeValues.attribute',
                'attributeValues.valueListEntry',
                'attributeValues.unit',
                'mediaAssignments.media',
                'mediaAssignments.usageType',
                'prices.priceType',
            ])
            ->get()
            ->keyBy('id');

        // Reihenfolge der Auswahl beibehalten
        return collect($this->productIds)
            ->map(fn ($id) => $products->get($id))
            ->filter()
            ->values();
    }

    private function viewportFor(string $format): array
    {
        return match ($format) {
            '1x1'  => ['width' => 1080, 'height' => 1080],
            '16x9' => ['width' => 1920, 'height' => 1080],
            default => ['width' => 1080, 'height' => 1920], // 9x16
        };
    }
}

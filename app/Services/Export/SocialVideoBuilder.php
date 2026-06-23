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

    /** @var array<string,array{from:array,to:array}> Kamerafahrt je Produkt-ID. */
    private array $motions = [];

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
        'brief'           => '',
        'tonality'        => '',
        'accent'          => '#06b6d4',
        'background'      => '#0f0f23',
        'transition'      => 'fade',      // fade | slide | zoom | cut
        'media_animation' => 'kenburns',  // kenburns | zoom-in | zoom-out | fade-in | fade-out | pan | none
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
     * Überschreibt die Quell-Felder anhand der GUI-Auswahl (target => technical_name).
     * Erzeugt daraus Mapping-Regeln; ein leerer Wert entfernt das Feld bewusst.
     * Unbekannte Zielfelder werden ignoriert.
     *
     * @param array<string,string|null> $sources
     */
    public function setFieldSources(array $sources): void
    {
        $fields = [];
        foreach (SocialVideoElementMap::configurableFields() as $field) {
            $fields[$field['target']] = $field;
        }

        // Bestehende Regeln (Defaults) als Basis nach target indizieren.
        $byTarget = [];
        foreach ($this->mappingRules as $rule) {
            if (isset($rule['target'])) {
                $byTarget[$rule['target']] = $rule;
            }
        }

        foreach ($sources as $target => $value) {
            if (! isset($fields[$target])) {
                continue;
            }
            $value = is_string($value) ? trim($value) : '';
            if ($value === '') {
                unset($byTarget[$target]); // bewusst leer gelassen → Feld weglassen
                continue;
            }
            $field = $fields[$target];
            $byTarget[$target] = [
                'source' => SocialVideoElementMap::prefixForKind($field['kind']) . $value,
                'target' => $target,
                'type'   => $field['type'],
            ];
        }

        $this->mappingRules = array_values($byTarget);
    }

    /**
     * Setzt die Kamerafahrten je Produkt-ID. Werte werden defensiv geklemmt
     * (x/y 0–100 %, zoom 1–3), damit nur valide Fahrten ins Reel gelangen.
     *
     * @param array<string,array{from?:array,to?:array}> $motions
     */
    public function setMotions(array $motions): void
    {
        $clean = [];
        foreach ($motions as $productId => $motion) {
            if (! is_array($motion) || ! isset($motion['from'], $motion['to'])) {
                continue;
            }
            $clean[(string) $productId] = [
                'from' => $this->clampPoint($motion['from']),
                'to'   => $this->clampPoint($motion['to']),
            ];
        }
        $this->motions = $clean;
    }

    /**
     * @param mixed $point
     * @return array{x:float,y:float,zoom:float}
     */
    private function clampPoint($point): array
    {
        $x = is_array($point) ? (float) ($point['x'] ?? 50) : 50.0;
        $y = is_array($point) ? (float) ($point['y'] ?? 50) : 50.0;
        $zoom = is_array($point) ? (float) ($point['zoom'] ?? 1) : 1.0;

        return [
            'x'    => max(0.0, min(100.0, $x)),
            'y'    => max(0.0, min(100.0, $y)),
            'zoom' => max(1.0, min(3.0, $zoom)),
        ];
    }

    /**
     * Setzt das Stil-Briefing (nur bekannte Felder werden übernommen).
     */
    public function setStyle(array $style): void
    {
        foreach (['brief', 'tonality', 'accent', 'background', 'transition', 'media_animation'] as $key) {
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

        // Abschluss-Szene (Call-to-Action) – Sprechertext optional von der KI.
        $scenes[] = [
            'type'     => 'cta',
            'headline' => SocialVideoElementMap::fieldDefaults()['cta'],
            'sprecher' => $this->generateCtaLine($products->first()),
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

        // hero_image/gallery liefern vom MappingResolver einen Storage-Pfad
        // (z.B. "media/abc.jpg"). Daraus eine öffentlich abrufbare URL machen,
        // sonst kann die Video-Engine (und die Vorschau) das Bild nicht laden.
        $galleryImages = array_values(array_filter(array_map(
            fn ($p) => $this->mediaUrl($p),
            is_array($mapped['gallery'] ?? null) ? $mapped['gallery'] : [],
        )));
        $hero = $this->mediaUrl($mapped['hero_image'] ?? null) ?? ($galleryImages[0] ?? null);

        // Fallback: kein teaser/gallery gemappt → erstes beliebiges Produktbild nutzen,
        // damit auch ohne passende Usage-Type-Benennung ein Bild erscheint.
        if ($hero === null) {
            $hero = $this->firstProductImage($product);
        }

        // Szenen-Gerüst mit Default-Sprechertexten (greifen, wenn keine KI aktiv ist
        // oder der KI-Aufruf fehlschlägt). Parallel dazu Szenen-Deskriptoren für die KI.
        $scenes = [[
            'type'     => 'hero',
            'image'    => $hero,
            'headline' => $headline,
            'subline'  => $mapped['subline'] ?? null,
            'sprecher' => $mapped['subline'] ?? $headline,
            'duration' => 3000,
        ]];
        $descriptors = [['role' => 'hook', 'text' => (string) $headline]];

        // Bis zu zwei Feature-Szenen: Attributname (Label) + Wert + Einheit,
        // statt nur des rohen Werts (z.B. "Bildschirmdiagonale" / "9 Zoll").
        $featureScenes = [];
        foreach (['feature_1', 'feature_2', 'feature_3'] as $target) {
            $feature = $this->buildFeature($product, $target, $mapped[$target] ?? null);
            if ($feature !== null) {
                $featureScenes[] = $feature;
            }
        }

        foreach (array_slice($featureScenes, 0, 2) as $i => $feature) {
            $scenes[] = [
                'type'     => 'feature',
                'image'    => $galleryImages[$i] ?? $hero,
                'badge'    => $feature['label'],
                'headline' => $feature['text'],
                'sprecher' => $feature['label'] . ': ' . $feature['text'],
                'duration' => 2500,
            ];
            $descriptors[] = ['role' => 'feature', 'text' => $feature['label'] . ': ' . $feature['text']];
        }

        // Preis-Szene (nur wenn ein Preis gemappt ist)
        if (isset($mapped['price']) && $mapped['price'] !== null) {
            $priceValue = (float) $mapped['price'];
            $scenes[] = [
                'type'     => 'price',
                'value'    => $priceValue,
                'currency' => 'EUR',
                'sprecher' => 'Jetzt für nur ' . number_format($priceValue, 0, ',', '.') . ' Euro.',
                'duration' => 2500,
            ];
            $descriptors[] = ['role' => 'price', 'text' => number_format($priceValue, 0, ',', '.') . ' EUR'];
        }

        // KI: ein zusammenhängendes Sprecher-Skript über alle Szenen dieses Produkts.
        // Bei Fehler/leerer Zeile bleibt der jeweilige Default-Sprechertext erhalten.
        if ($this->useAi) {
            foreach ($this->generateSceneScript($product, $descriptors) as $i => $line) {
                if (isset($scenes[$i]) && trim((string) $line) !== '') {
                    $scenes[$i]['sprecher'] = $this->firstLine((string) $line);
                }
            }
        }

        // Kamerafahrt (Fokuspunkt-Zoom) auf alle bildtragenden Szenen dieses Produkts legen.
        $motion = $this->motions[(string) $product->id] ?? null;
        if ($motion !== null) {
            foreach ($scenes as &$scene) {
                if (! empty($scene['image'])) {
                    $scene['motion'] = $motion;
                }
            }
            unset($scene);
        }

        return $scenes;
    }

    /**
     * Löst je Produkt das Aufmacherbild (gemäß aktuellem Mapping) auf – für den
     * Kamerafahrt-Editor im Frontend, damit dort dasselbe Bild gezeigt wird.
     *
     * @param  string[]  $productIds
     * @return array<int,array{product_id:string,sku:?string,name:?string,image:?string}>
     */
    public function resolveProductImages(array $productIds): array
    {
        $this->setProductIds($productIds);
        $out = [];
        foreach ($this->loadProducts() as $product) {
            $mapped = $this->mappingResolver->resolve($this->mappingRules, $product, $this->languages);
            $gallery = array_values(array_filter(array_map(
                fn ($p) => $this->mediaUrl($p),
                is_array($mapped['gallery'] ?? null) ? $mapped['gallery'] : [],
            )));
            $hero = $this->mediaUrl($mapped['hero_image'] ?? null)
                ?? ($gallery[0] ?? null)
                ?? $this->firstProductImage($product);

            $out[] = [
                'product_id' => (string) $product->id,
                'sku'        => $product->sku,
                'name'       => $product->name,
                'image'      => $hero,
            ];
        }

        return $out;
    }

    /**
     * Wandelt einen Media-Storage-Pfad in eine öffentlich abrufbare URL um.
     * Bereits absolute URLs (externe Importe) bleiben unverändert. Die Route
     * media/file/{file_name} liefert die Datei ohne Auth (für <img>/Engine).
     */
    private function mediaUrl(?string $filePath): ?string
    {
        if ($filePath === null || trim($filePath) === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $filePath) === 1) {
            return $filePath;
        }

        return url('/api/v1/media/file/' . rawurlencode(basename($filePath)));
    }

    /**
     * Liefert die URL des ersten verfügbaren Produktbildes (beliebiger Usage-Type),
     * sortiert nach sort_order – als Fallback, wenn kein teaser/gallery gemappt ist.
     */
    private function firstProductImage(Product $product): ?string
    {
        $assignment = $product->mediaAssignments
            ->sortBy('sort_order')
            ->first(fn ($a) => ! empty($a->media?->file_path));

        return $this->mediaUrl($assignment?->media?->file_path);
    }

    /**
     * Baut einen Feature-Eintrag: Attribut-Label + formatierter Wert (+ Einheit).
     * Beispiel: Bildschirmdiagonale → ['label' => 'Bildschirmdiagonale', 'text' => '9 Zoll'].
     * Der vom Resolver gelieferte Rohwert wird genutzt (deckt Auswahllisten ab),
     * nur Zahlen werden schön formatiert und die Einheit angehängt.
     *
     * @return array{label:string,text:string}|null
     */
    private function buildFeature(Product $product, string $target, mixed $rawValue): ?array
    {
        if ($rawValue === null || $rawValue === '' || is_array($rawValue)) {
            return null;
        }

        $techName = $this->sourceTechName($target);
        $attributeValue = $techName !== null
            ? $product->attributeValues->first(fn ($v) => $v->attribute?->technical_name === $techName)
            : null;

        $label = $attributeValue?->attribute?->name_de ?? $techName ?? '';

        $value = (string) $rawValue;
        if (is_numeric($value)) {
            $value = $this->formatNumber((float) $value);
        }

        $unit = $attributeValue?->unit?->abbreviation;
        $text = trim($value . ($unit ? ' ' . $unit : ''));

        if ($text === '') {
            return null;
        }

        return ['label' => $label, 'text' => $text];
    }

    /**
     * Ermittelt den technischen Quell-Namen (ohne Präfix) für ein Zielfeld aus den Mapping-Regeln.
     */
    private function sourceTechName(string $target): ?string
    {
        foreach ($this->mappingRules as $rule) {
            if (($rule['target'] ?? null) === $target) {
                $source = (string) ($rule['source'] ?? '');
                $pos = strpos($source, ':');

                return $pos !== false ? substr($source, $pos + 1) : $source;
            }
        }

        return null;
    }

    /**
     * Formatiert eine Zahl menschenlesbar (9.0000 → "9", 1.8 → "1,8") im DE-Format.
     */
    private function formatNumber(float $number): string
    {
        if (floor($number) === $number) {
            return number_format($number, 0, ',', '.');
        }

        return rtrim(rtrim(number_format($number, 2, ',', '.'), '0'), ',');
    }

    /**
     * Erzeugt ein zusammenhängendes Sprecher-Skript: genau ein gesprochener Satz
     * pro Szene, in Reihenfolge der Deskriptoren. Liefert bei Fehlern ein leeres
     * Array (→ Default-Sprechertexte bleiben erhalten).
     *
     * @param  list<array{role:string,text:string}>  $descriptors
     * @return list<string>
     */
    private function generateSceneScript(Product $product, array $descriptors): array
    {
        $apiKey = (string) config('connectors.claude_ai.api_key', '');
        if ($apiKey === '' || $descriptors === []) {
            return [];
        }

        $count = count($descriptors);
        $sceneList = '';
        foreach ($descriptors as $i => $descriptor) {
            $role = match ($descriptor['role']) {
                'hook'    => 'Aufmacher/Hook',
                'feature' => 'Produktmerkmal',
                'price'   => 'Preis-Überleitung',
                default   => 'Szene',
            };
            $sceneList .= ($i + 1) . ". {$role}: " . $descriptor['text'] . "\n";
        }

        $prompt = "Du schreibst das gesprochene Voiceover für ein kurzes Social-Media-Produktvideo.\n"
            . "Erzeuge für jede der folgenden {$count} Szenen genau einen gesprochenen Satz – flüssig, "
            . "natürlich und als zusammenhängendes Skript (max. ca. 14 Wörter je Satz, keine Hashtags, "
            . "keine Anführungszeichen, keine Szenen-Nummern im Text).\n"
            . "Antworte AUSSCHLIESSLICH mit einem JSON-Array aus genau {$count} Strings in dieser Reihenfolge.\n\n"
            . "Szenen:\n" . $sceneList;
        if (trim($this->style['brief']) !== '') {
            $prompt .= "\nKreativ-Briefing für das Video: " . trim($this->style['brief']);
        }
        $tonality = trim($this->style['tonality']) !== '' ? trim($this->style['tonality']) : 'jung, energiegeladen, direkt';

        try {
            $result = $this->claude->generateProductText($apiKey, $product, $this->languages[0], 'marketing', [
                'custom_prompt' => $prompt,
                'tonality'      => $tonality,
                'max_tokens'    => 60 * $count + 80,
            ]);

            return $this->parseScriptLines((string) ($result['text'] ?? ''));
        } catch (\Throwable $e) {
            Log::channel('export')->warning('Social-Video KI-Skript fehlgeschlagen, nutze Default-Sprechertexte', [
                'product_id' => $product->id,
                'error'      => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Liefert einen kurzen, gesprochenen Call-to-Action (optional von der KI),
     * sonst den Standard-Abschluss.
     */
    private function generateCtaLine(?Product $product): string
    {
        $default = 'Jetzt entdecken auf incoxx.com.';
        $apiKey = (string) config('connectors.claude_ai.api_key', '');
        if (! $this->useAi || $apiKey === '' || $product === null) {
            return $default;
        }

        $prompt = 'Schreibe einen kurzen, gesprochenen Call-to-Action als Abschluss eines '
            . 'Produktvideos (max. 10 Wörter, keine Hashtags, keine Anführungszeichen).';
        if (trim($this->style['brief']) !== '') {
            $prompt .= "\nKreativ-Briefing für das Video: " . trim($this->style['brief']);
        }
        $tonality = trim($this->style['tonality']) !== '' ? trim($this->style['tonality']) : 'jung, energiegeladen, direkt';

        try {
            $result = $this->claude->generateProductText($apiKey, $product, $this->languages[0], 'marketing', [
                'custom_prompt' => $prompt,
                'tonality'      => $tonality,
                'max_tokens'    => 60,
            ]);
            $text = trim((string) ($result['text'] ?? ''));

            return $text !== '' ? $this->firstLine($text) : $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    /**
     * Extrahiert die Sprechertexte aus der KI-Antwort. Bevorzugt ein JSON-Array,
     * fällt sonst auf zeilenweises Parsen (inkl. Entfernen von Nummerierung) zurück.
     *
     * @return list<string>
     */
    private function parseScriptLines(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        // JSON-Array bevorzugt (auch wenn von Fließtext umgeben).
        if (preg_match('/\[.*\]/s', $text, $matches) === 1) {
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded)) {
                return array_values(array_map(fn ($s) => trim((string) $s), $decoded));
            }
        }

        // Fallback: zeilenweise, führende Nummerierung/Aufzählung entfernen.
        $lines = [];
        foreach (preg_split('/\r?\n/', $text) ?: [] as $line) {
            $line = preg_replace('/^\s*\d+[.)]\s*/', '', trim($line)) ?? '';
            $line = trim($line, " \"'-•\t");
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return $lines;
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

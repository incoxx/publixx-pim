<?php

declare(strict_types=1);

namespace App\Services\Preview;

use App\Models\ContentPage;
use App\Models\ContentSection;
use App\Models\HierarchyNode;
use App\Models\Media;
use App\Models\Navigation;
use App\Models\NavigationNode;
use App\Models\Product;
use App\Models\ProductWidget;
use App\Services\Export\MappingResolver;

/**
 * Baut die gerenderte Website-Struktur (Sitemap + Seiten) als JSON.
 * Liefert Struktur + aufgelöste Produktdaten (headless) — das Aussehen
 * macht das Theme im Frontend.
 */
class WebsitePreviewService
{
    /**
     * Standard-Website-Theme. Wird mit navigations.theme_json überschrieben.
     * Die Vorschau setzt diese Werte als CSS-Variablen (--site-*).
     */
    public const DEFAULT_THEME = [
        'primary' => '#e11d48',       // Header / CTA-Hintergrund
        'on_primary' => '#ffffff',    // Text auf primary
        'accent' => '#3b82f6',        // Buttons / Links
        'text' => '#111827',          // Fließtext
        'muted' => '#6b7280',         // sekundärer Text
        'background' => '#f8fafc',    // Seitenhintergrund
        'surface' => '#ffffff',       // Karten / Flächen
        'border' => '#e5e7eb',        // Rahmen
        'radius' => '1rem',           // Eckenradius
        'font' => 'system-ui, sans-serif',
    ];

    /** @var array<string, ProductWidget|null> */
    private array $widgetCache = [];

    public function __construct(
        private readonly MappingResolver $mappingResolver,
    ) {}

    /**
     * Kompletter Navigationsbaum als verschachtelte Sitemap.
     */
    public function buildSitemap(Navigation $navigation, string $lang = 'de'): array
    {
        $roots = NavigationNode::where('navigation_id', $navigation->id)
            ->whereNull('parent_node_id')
            ->orderBy('sort_order')
            ->get();

        return [
            'navigation' => [
                'id' => $navigation->id,
                'technical_name' => $navigation->technical_name,
                'name' => $this->localized($navigation->name_de, $navigation->name_en, $lang),
            ],
            'theme' => array_merge(self::DEFAULT_THEME, $navigation->theme_json ?? []),
            'nodes' => $this->mapNodes($roots, $lang),
        ];
    }

    /**
     * Eine Content-Seite mit aufgelösten Sektionen.
     */
    public function buildPage(ContentPage $page, string $lang = 'de'): array
    {
        $sections = $page->sections()
            ->whereNull('parent_section_id')
            ->with('sectionType', 'childSections.sectionType')
            ->orderBy('sort_order')
            ->get();

        return [
            'id' => $page->id,
            'title' => $page->title,
            'slug' => $page->slug,
            'status' => $page->status,
            'is_currently_valid' => $page->isCurrentlyValid(),
            'seo' => [
                'title' => $this->fromJson($page->seo_title_json, $lang) ?? $page->title,
                'description' => $this->fromJson($page->seo_description_json, $lang),
            ],
            'sections' => $sections->map(fn (ContentSection $s) => $this->resolveSection($s, $lang))
                ->filter(fn ($s) => $s['is_visible'])
                ->values()
                ->all(),
        ];
    }

    /**
     * Produkt-Detailseite (PDP) — ein Produkt als native Seite gerendert:
     * Hero (Bild/Preis/Name/CTA), technische Daten, Beschreibung und
     * passendes Zubehör (Produktrelationen). Demonstriert „Produkt = Seite".
     */
    public function buildProductPage(string $productId, string $lang = 'de'): ?array
    {
        $product = Product::with([
            'prices.priceType', 'mediaAssignments.media', 'mediaAssignments.usageType',
            'attributeValues.attribute', 'attributeValues.unit', 'attributeValues.valueListEntry',
            'relations',
        ])->find($productId);

        if (!$product) {
            return null;
        }

        $summary = $this->productSummary($product->id, $lang);
        $short = $this->mappingResolver->resolveRule('attribute:description_short', 'text', $product, $lang)
            ?? $this->mappingResolver->resolveRule('attribute:product-description-str', 'text', $product, $lang);
        $long = $this->mappingResolver->resolveRule('attribute:description_long', 'text', $product, $lang);

        // Technische Daten: Attributwerte (ohne reine Textfelder), max. 12.
        $skip = ['description_short', 'description_long', 'product-description-str', 'product-name-str'];
        $specs = [];
        $seen = [];
        foreach ($product->attributeValues as $av) {
            $tn = $av->attribute?->technical_name;
            if (!$tn || isset($seen[$tn]) || in_array($tn, $skip, true)) {
                continue;
            }
            $seen[$tn] = true;
            $value = $this->mappingResolver->resolveRule('attribute:' . $tn, 'text', $product, $lang);
            if ($value === null || $value === '' || is_array($value)) {
                continue;
            }
            $specs[] = ['label' => $av->attribute?->name_de ?? $tn, 'value' => (string) $value];
            if (count($specs) >= 12) {
                break;
            }
        }

        // Zubehör/Cross-Selling aus Produktrelationen.
        $accessoryIds = $product->relations->pluck('target_product_id')->filter()->unique()->values()->all();
        $accessories = $this->productSummaries($accessoryIds, $lang, 'card');

        $sections = [];
        $sections[] = [
            'id' => 'pdp-hero', 'type' => 'hero', 'is_visible' => true, 'settings' => [],
            'values' => [
                'eyebrow' => $product->sku,
                'headline' => $product->name,
                'subline' => is_string($short) ? $short : null,
                'cta_label' => 'In den Warenkorb',
                'cta_url' => '#',
            ],
            'fields' => [[
                'key' => 'product', 'label' => 'Produkt', 'type' => 'product_ref',
                'products' => array_values(array_filter([$summary])),
            ]],
        ];
        if ($specs) {
            $sections[] = [
                'id' => 'pdp-specs', 'type' => 'product-specs', 'is_visible' => true,
                'settings' => [], 'values' => ['headline' => 'Technische Daten'], 'fields' => [], 'specs' => $specs,
            ];
        }
        if (is_string($long) && $long !== '') {
            $sections[] = [
                'id' => 'pdp-desc', 'type' => 'text', 'is_visible' => true, 'settings' => [],
                'values' => ['body' => $long],
                'fields' => [['key' => 'body', 'label' => 'Text', 'type' => 'RichText', 'value' => $long]],
            ];
        }
        if ($accessories) {
            $sections[] = [
                'id' => 'pdp-accessories', 'type' => 'product-gallery', 'is_visible' => true, 'settings' => [],
                'values' => ['headline' => 'Passendes Zubehör', 'columns' => '4'],
                'fields' => [], 'products' => $accessories,
            ];
        }

        return [
            'id' => $product->id,
            'title' => $product->name,
            'slug' => 'product/' . $product->id,
            'status' => 'active',
            'is_currently_valid' => true,
            'seo' => ['title' => $product->name, 'description' => is_string($short) ? $short : null],
            'sections' => $sections,
        ];
    }

    /**
     * Seite anhand eines Slugs innerhalb einer Navigation finden
     * (über einen Navigationsknoten oder direkt per Seiten-Slug).
     */
    public function resolvePageBySlug(Navigation $navigation, string $slug, string $lang = 'de'): ?array
    {
        $node = NavigationNode::where('navigation_id', $navigation->id)
            ->where('slug', $slug)
            ->where('target_type', 'content_page')
            ->first();

        $page = $node && $node->content_page_id
            ? ContentPage::find($node->content_page_id)
            : ContentPage::where('slug', $slug)->first();

        // Öffentliche Vorschau: nur aktuell gültige Seiten ausliefern
        // (kein Leak von Entwürfen/abgelaufenen Seiten über die No-Auth-Route).
        if (!$page || !$page->isCurrentlyValid()) {
            return null;
        }

        return $this->buildPage($page, $lang);
    }

    // ─── Navigation ────────────────────────────────────────────────

    private function mapNodes($nodes, string $lang): array
    {
        return $nodes
            // Verwaiste Ziel-Knoten (Seite/Produkt/Kategorie gelöscht) nicht
            // ausliefern — sie würden ins Leere zeigen.
            ->reject(fn (NavigationNode $node) => $this->isOrphan($node))
            ->map(function (NavigationNode $node) use ($lang) {
            $children = $node->children()->orderBy('sort_order')->get();

            return [
                'id' => $node->id,
                'label' => $this->localized($node->label_de, $node->label_en, $lang),
                'slug' => $node->slug,
                'icon' => $node->icon,
                'is_visible' => (bool) $node->is_visible,
                'target_type' => $node->target_type,
                'product_id' => $node->product_id,
                'hierarchy_node_id' => $node->hierarchy_node_id,
                'href' => $this->nodeHref($node),
                'auto_expand' => (bool) $node->auto_expand,
                'children' => $this->mapNodes($children, $lang),
            ];
        })->all();
    }

    /** Knoten zeigt auf ein gelöschtes Ziel (FK auf NULL gesetzt) → Artefakt. */
    private function isOrphan(NavigationNode $node): bool
    {
        return match ($node->target_type) {
            'content_page' => $node->content_page_id === null,
            'product' => $node->product_id === null,
            'product_category' => $node->hierarchy_node_id === null,
            default => false,
        };
    }

    private function nodeHref(NavigationNode $node): ?string
    {
        return match ($node->target_type) {
            'content_page' => '/' . ($node->slug ?: $node->content_page_id),
            'product_category' => '/category/' . $node->hierarchy_node_id,
            'product' => '/product/' . $node->product_id,
            'external_url' => $node->external_url,
            default => null,
        };
    }

    // ─── Sektionen ─────────────────────────────────────────────────

    private function resolveSection(ContentSection $section, string $lang): array
    {
        $type = $section->sectionType?->technical_name ?? 'unknown';
        $values = $this->flattenValues($section->values_json, $lang);

        $resolved = [
            'id' => $section->id,
            'type' => $type,
            'is_visible' => (bool) $section->is_visible,
            'settings' => $section->settings_json ?? [],
            'values' => $values,
            // Schema-getriebene, generische Feldauflösung (rendert AUCH eigene
            // Sektionstypen: Media→URL, product_ref→Produktkarten, etc.)
            'fields' => $this->resolveFields($section, $values, $lang),
        ];

        // Commerce-Sektionen: Produkt-/Kategoriedaten nativ auflösen.
        $widget = $values['widget'] ?? null;

        switch ($type) {
            case 'product-teaser':
                $resolved['product'] = $this->productSummary($values['product'] ?? null, $lang, $widget);
                break;
            case 'product-gallery':
                $resolved['products'] = $this->productSummaries($values['products'] ?? [], $lang, $widget);
                break;
            case 'category-teaser':
                $resolved['category'] = $this->categorySummary($values['hierarchy_node'] ?? null, $lang);
                break;
            case 'category-grid':
                $resolved['categories'] = collect($values['categories'] ?? [])
                    ->map(fn ($id) => $this->categorySummary($id, $lang))
                    ->filter()->values()->all();
                break;
            case 'promo-carousel':
                $resolved['slides'] = $section->childSections
                    ->map(fn (ContentSection $c) => $this->resolveSection($c, $lang))->all();
                break;
        }

        return $resolved;
    }

    /**
     * Generische Feldauflösung über das Schema des Sektionstyps — funktioniert
     * für eingebaute UND eigene Sektionstypen. Liefert pro Feld einen Eintrag
     * mit aufgelöstem Wert (Text), Media-URL(s), Produktkarten oder Kategorien.
     */
    private function resolveFields(ContentSection $section, array $values, string $lang): array
    {
        $schemaFields = $section->sectionType?->schema['fields'] ?? [];
        $widget = $values['widget'] ?? null;
        $out = [];

        foreach ($schemaFields as $f) {
            $key = $f['key'] ?? null;
            if (!$key) {
                continue;
            }
            $ftype = $f['type'] ?? 'String';
            $raw = $values[$key] ?? null;
            $entry = ['key' => $key, 'label' => $f['label'] ?? $key, 'type' => $ftype];

            switch ($ftype) {
                case 'Media':
                    $entry['media'] = is_array($raw)
                        ? array_values(array_filter(array_map(fn ($id) => $this->mediaUrl($id), $raw)))
                        : $this->mediaUrl($raw);
                    break;
                case 'product_ref':
                    $entry['products'] = is_array($raw)
                        ? $this->productSummaries($raw, $lang, $widget)
                        : array_values(array_filter([$this->productSummary($raw, $lang, $widget)]));
                    break;
                case 'hierarchy_node_ref':
                    $ids = is_array($raw) ? $raw : ($raw ? [$raw] : []);
                    $entry['categories'] = collect($ids)
                        ->map(fn ($id) => $this->categorySummary($id, $lang))
                        ->filter()->values()->all();
                    break;
                case 'product_widget_ref':
                    continue 2; // reine Konfiguration — nicht anzeigen
                case 'pql':
                    $entry['value'] = null; // Live-Auflösung folgt
                    break;
                default:
                    $entry['value'] = $raw; // String/RichText/Textarea/Link/Selection/Number/Flag/Date
            }

            $out[] = $entry;
        }

        return $out;
    }

    /** Speicherpfad/Dateiname → öffentliche Datei-URL (idempotent). */
    private function normalizeMediaUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }
        if (str_starts_with($path, 'http') || str_starts_with($path, '/api/')) {
            return $path;
        }

        return '/api/v1/media/file/' . rawurlencode(basename($path));
    }

    private function mediaUrl(mixed $id): ?string
    {
        if (!$id) {
            return null;
        }
        if (is_string($id) && (str_starts_with($id, '/') || str_starts_with($id, 'http'))) {
            return $id;
        }
        $media = Media::find($id);

        return $media ? '/api/v1/media/file/' . $media->file_name : null;
    }

    private function productSummaries(array $ids, string $lang, ?string $widget = null): array
    {
        return collect($ids)
            ->map(fn ($id) => $this->productSummary($id, $lang, $widget))
            ->filter()->values()->all();
    }

    private function productSummary(?string $id, string $lang, ?string $widget = null): ?array
    {
        if (!$id) {
            return null;
        }

        $product = Product::with([
            'prices.priceType',
            'mediaAssignments.media',
            'mediaAssignments.usageType',
            'attributeValues.attribute',
            'attributeValues.unit',
            'attributeValues.valueListEntry',
        ])->find($id);

        if (!$product) {
            return null;
        }

        $primary = $product->mediaAssignments->firstWhere('is_primary', true)
            ?? $product->mediaAssignments->first();
        $price = $product->prices->sortBy('amount')->first();

        // Basis-Zusammenfassung (immer vorhanden)
        $summary = [
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'price' => $price?->amount,
            'currency' => $price?->currency,
            'image' => $primary?->media ? '/api/v1/media/file/' . $primary->media->file_name : null,
            'href' => '/product/' . $product->id,
        ];

        // Widget-Anzeigedefinition anwenden (rollenbasierte Felder)
        $def = $this->resolveWidget($widget);
        if ($def) {
            $summary['widget'] = $widget;
            $summary['display'] = $this->applyWidget($product, $def->config ?? [], $lang);
        }

        return $summary;
    }

    /**
     * Felder eines Produkts gemäß Widget-Definition rollenbasiert auflösen.
     * Quellen nutzen die MappingResolver-Namespaces; product:* sind Basisfelder.
     */
    private function applyWidget(Product $product, array $config, string $lang): array
    {
        $fields = [];

        foreach ($config['fields'] ?? [] as $f) {
            $source = $f['source'] ?? '';
            $type = $f['type'] ?? 'text';

            if (str_starts_with($source, 'product:')) {
                $value = $product->{substr($source, 8)} ?? null;
            } else {
                $value = $this->mappingResolver->resolveRule($source, $type, $product, $lang);
            }

            // Medien liefert der Resolver als Speicherpfad (für Exporte). Für die
            // Web-Vorschau in die öffentliche Datei-URL umschreiben.
            if ($type === 'media_url' && is_string($value)) {
                $value = $this->normalizeMediaUrl($value);
            } elseif ($type === 'media_array' && is_array($value)) {
                $value = array_values(array_filter(array_map(fn ($v) => $this->normalizeMediaUrl((string) $v), $value)));
            }

            $fields[] = [
                'role' => $f['role'] ?? 'attribute',
                'source' => $source,
                'value' => $value,
            ];
        }

        return [
            'fields' => $fields,
            'show_sku' => (bool) ($config['show_sku'] ?? false),
            'image_ratio' => $config['image_ratio'] ?? null,
            'cta' => $config['cta'] ?? null,
        ];
    }

    private function resolveWidget(?string $technicalName): ?ProductWidget
    {
        if (!$technicalName) {
            return null;
        }

        return $this->widgetCache[$technicalName] ??= ProductWidget::where('technical_name', $technicalName)
            ->where('is_active', true)
            ->first();
    }

    private function categorySummary(?string $nodeId, string $lang): ?array
    {
        if (!$nodeId) {
            return null;
        }

        $node = HierarchyNode::withCount('products')->find($nodeId);
        if (!$node) {
            return null;
        }

        return [
            'id' => $node->id,
            'name' => $this->localized($node->name_de, $node->name_en, $lang),
            'product_count' => $node->products_count,
            'href' => '/category/' . $node->id,
        ];
    }

    // ─── Helpers ──────────────────────────────────────────────────

    /**
     * values_json (Buckets "_" + Sprache) zu einer flachen Map zusammenführen.
     */
    private function flattenValues(?array $valuesJson, string $lang): array
    {
        $valuesJson ??= [];
        $neutral = $valuesJson['_'] ?? [];
        $translated = $valuesJson[$lang] ?? [];

        return array_merge($neutral, $translated);
    }

    private function fromJson(?array $json, string $lang): ?string
    {
        if (!$json) {
            return null;
        }

        return $json[$lang] ?? $json['de'] ?? null;
    }

    private function localized(?string $de, ?string $en, string $lang): ?string
    {
        return $lang === 'en' && $en ? $en : $de;
    }
}

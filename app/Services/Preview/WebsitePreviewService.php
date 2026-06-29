<?php

declare(strict_types=1);

namespace App\Services\Preview;

use App\Models\ContentPage;
use App\Models\ContentSection;
use App\Models\HierarchyNode;
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

        return $page ? $this->buildPage($page, $lang) : null;
    }

    // ─── Navigation ────────────────────────────────────────────────

    private function mapNodes($nodes, string $lang): array
    {
        return $nodes->map(function (NavigationNode $node) use ($lang) {
            $children = $node->children()->orderBy('sort_order')->get();

            return [
                'id' => $node->id,
                'label' => $this->localized($node->label_de, $node->label_en, $lang),
                'slug' => $node->slug,
                'icon' => $node->icon,
                'is_visible' => (bool) $node->is_visible,
                'target_type' => $node->target_type,
                'href' => $this->nodeHref($node),
                'auto_expand' => (bool) $node->auto_expand,
                'children' => $this->mapNodes($children, $lang),
            ];
        })->all();
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

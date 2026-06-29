<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Models\Navigation;
use App\Models\NavigationNode;
use App\Services\Preview\WebsitePreviewService;

/**
 * Rendert die wichtigsten Content-Ausgaben einer Navigation vor (Warming),
 * sodass die erste anonyme Anfrage bereits ein Cache-HIT ist.
 */
class ContentCacheWarmer
{
    public function __construct(
        private readonly WebsitePreviewService $preview,
    ) {}

    /**
     * Eine Navigation vorrendern: Sitemap + alle verlinkten Content-Seiten und
     * Produktseiten je Sprache. Gibt die Anzahl gewärmter Ausgaben zurück.
     *
     * @param  array<string>  $langs
     */
    public function warmNavigation(Navigation $navigation, array $langs = ['de']): int
    {
        $count = 0;

        $nodes = NavigationNode::where('navigation_id', $navigation->id)->get();

        foreach ($langs as $lang) {
            $this->preview->buildSitemap($navigation, $lang, true);
            $count++;

            foreach ($nodes as $node) {
                if ($node->target_type === 'content_page' && $node->slug) {
                    if ($this->preview->resolvePageBySlug($navigation, $node->slug, $lang, true) !== null) {
                        $count++;
                    }
                } elseif ($node->target_type === 'product' && $node->product_id) {
                    if ($this->preview->buildProductPage($node->product_id, $lang, true) !== null) {
                        $count++;
                    }
                }
            }
        }

        return $count;
    }
}

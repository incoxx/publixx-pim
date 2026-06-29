<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Navigation;
use App\Services\Preview\WebsitePreviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Öffentliche Website-Vorschau (ohne Auth, analog Public-Catalog).
 * Liefert Sitemap und gerenderte Seiten als JSON für das Theme/Frontend.
 */
class PublicSiteController extends Controller
{
    public function __construct(
        private readonly WebsitePreviewService $preview,
    ) {}

    public function sitemap(Request $request, string $navigation): JsonResponse
    {
        $nav = $this->resolveNavigation($navigation);
        $lang = (string) $request->query('lang', 'de');

        return response()->json(['data' => $this->preview->buildSitemap($nav, $lang)]);
    }

    public function page(Request $request, string $navigation, string $slug): JsonResponse
    {
        $nav = $this->resolveNavigation($navigation);
        $lang = (string) $request->query('lang', 'de');

        $page = $this->preview->resolvePageBySlug($nav, $slug, $lang);

        if ($page === null) {
            return response()->json(['message' => 'Seite nicht gefunden.'], 404);
        }

        return response()->json(['data' => $page]);
    }

    /**
     * SEO-Sitemap als XML (Knoten mit eigener URL).
     */
    public function sitemapXml(Request $request, string $navigation): Response
    {
        $nav = $this->resolveNavigation($navigation);
        $sitemap = $this->preview->buildSitemap($nav, (string) $request->query('lang', 'de'));

        $urls = [];
        $collect = function ($nodes) use (&$collect, &$urls) {
            foreach ($nodes as $node) {
                if (!empty($node['href']) && $node['is_visible']) {
                    $urls[] = $node['href'];
                }
                $collect($node['children'] ?? []);
            }
        };
        $collect($sitemap['nodes']);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $href) {
            $xml .= '  <url><loc>' . htmlspecialchars($href, ENT_XML1) . '</loc></url>' . "\n";
        }
        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    private function resolveNavigation(string $navigation): Navigation
    {
        return Navigation::where('technical_name', $navigation)
            ->orWhere('id', $navigation)
            ->firstOrFail();
    }
}

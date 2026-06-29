<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Navigation;
use App\Services\Content\ContentCache;
use App\Services\Preview\WebsitePreviewService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Öffentliche Website-Vorschau (ohne Auth, analog Public-Catalog).
 * Liefert Sitemap und gerenderte Seiten als JSON für das Theme/Frontend.
 */
class PublicSiteController extends Controller
{
    public function __construct(
        private readonly WebsitePreviewService $preview,
        private readonly ContentCache $cache,
    ) {}

    /**
     * Cache nur für anonyme Besucher nutzen. Eingeloggte Redakteure (Admin-
     * Vorschau) und ?nocache=1 rendern immer live → sofortige Kontrolle.
     */
    private function useCache(Request $request): bool
    {
        return ! (Auth::check() || $request->boolean('nocache'));
    }

    /**
     * Antwort mit HTTP-Caching (Phase 2): ETag + Cache-Control für anonyme
     * Besucher, `304 Not Modified` bei passendem If-None-Match. Eingeloggte/
     * nocache-Anfragen: private, no-store. X-Cache spiegelt Server-Cache.
     */
    private function cachedJson(Request $request, array $data): Response
    {
        $useCache = $this->useCache($request);
        $headers = [
            'X-Cache' => $useCache ? ($this->cache->lastHit ? 'HIT' : 'MISS') : 'BYPASS',
        ];

        if (! $useCache) {
            $headers['Cache-Control'] = 'private, no-store';

            return response()->json(['data' => $data], 200, $headers);
        }

        $etag = '"' . md5(json_encode(['data' => $data])) . '"';
        $maxAge = (int) config('content.cache.http_max_age', 300);
        $headers['Cache-Control'] = "public, max-age={$maxAge}, stale-while-revalidate=" . ($maxAge * 2);
        $headers['ETag'] = $etag;

        if (trim((string) $request->headers->get('If-None-Match')) === $etag) {
            return response('', 304, $headers);
        }

        return response()->json(['data' => $data], 200, $headers);
    }

    public function sitemap(Request $request, string $navigation): Response
    {
        $nav = $this->resolveNavigation($navigation);
        $lang = (string) $request->query('lang', 'de');

        $data = $this->preview->buildSitemap($nav, $lang, $this->useCache($request));

        return $this->cachedJson($request, $data);
    }

    public function page(Request $request, string $navigation, string $slug): Response
    {
        $nav = $this->resolveNavigation($navigation);
        $lang = (string) $request->query('lang', 'de');

        $page = $this->preview->resolvePageBySlug($nav, $slug, $lang, $this->useCache($request));

        if ($page === null) {
            return response()->json(['message' => 'Seite nicht gefunden.'], 404);
        }

        return $this->cachedJson($request, $page);
    }

    public function productPage(Request $request, string $navigation, string $product): Response
    {
        $this->resolveNavigation($navigation); // Navigation muss existieren (Scope)
        $lang = (string) $request->query('lang', 'de');

        $page = $this->preview->buildProductPage($product, $lang, $this->useCache($request));

        if ($page === null) {
            return response()->json(['message' => 'Produkt nicht gefunden.'], 404);
        }

        return $this->cachedJson($request, $page);
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
        $nav = Navigation::where('technical_name', $navigation)
            ->orWhere('id', $navigation)
            ->firstOrFail();

        // Zugriffssteuerung: 'login'-Navigationen nur für authentifizierte Nutzer.
        if (($nav->access_mode ?? 'public') === 'login' && ! Auth::check()) {
            throw new HttpResponseException(response()->json([
                'message' => 'Diese Seite erfordert eine Anmeldung.',
                'requires_login' => true,
            ], 401));
        }

        return $nav;
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * TTL-Caching für die öffentliche, rein lesende Katalog-API (Produkte, Kategorien,
 * Facetten, Settings). Cached die komplette HTTP-Antwort (Body + Pagination-/
 * Facetten-Header) unter einem Schlüssel aus vollständiger URL inkl. Query-String.
 *
 * Bewusst einfach gehalten: reine zeitbasierte Ablauf (TTL), keine Invalidierung
 * bei Produkt-/Preisänderungen — Änderungen im PIM sind spätestens nach Ablauf
 * der TTL sichtbar. Reduziert DB-Last bei vielen gleichzeitigen Aufrufen (z.B.
 * eingebettete Kataloge auf stark frequentierten CMS-Seiten, siehe
 * Integration > Typo 3).
 *
 * Nutzt den bereits in config/cache.php reservierten, bislang ungenutzten
 * "product_list"-TTL (Default 300s, per CACHE_TTL_PRODUCT_LIST überschreibbar).
 */
class CacheCatalogResponse
{
    /** Nur diese Header werden mitgecached — alles Weitere setzt Laravel/Symfony ohnehin neu. */
    private const CACHEABLE_HEADERS = [
        'Content-Type', 'X-Total-Count', 'X-Current-Page', 'X-Last-Page',
        'X-Per-Page', 'X-Category-Counts', 'X-Skipped-Count',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->isMethod('GET')) {
            return $next($request);
        }

        $key = 'catalog_cache:' . md5($request->fullUrl());
        $cached = Cache::get($key);

        if ($cached !== null) {
            return response($cached['content'], $cached['status'], $cached['headers'])
                ->header('X-Catalog-Cache', 'HIT');
        }

        $response = $next($request);

        // Nur erfolgreiche Antworten cachen (kein 401/404/500/429 etc.)
        if ($response->isSuccessful()) {
            $headers = [];
            foreach (self::CACHEABLE_HEADERS as $name) {
                if ($response->headers->has($name)) {
                    $headers[$name] = $response->headers->get($name);
                }
            }

            Cache::put($key, [
                'content' => $response->getContent(),
                'status' => $response->getStatusCode(),
                'headers' => $headers,
            ], (int) config('cache.ttl.product_list', 300));
        }

        $response->headers->set('X-Catalog-Cache', 'MISS');

        return $response;
    }
}

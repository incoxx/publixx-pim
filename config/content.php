<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Content-Caching (Website-Vorschau & öffentliche Seiten)
    |--------------------------------------------------------------------------
    | Siehe docs/architecture/23-content-caching.md.
    | enabled=false → vollständiger Bypass (Live-Render wie ohne Cache).
    */
    'cache' => [
        'enabled' => env('CONTENT_CACHE_ENABLED', true),
        'ttl' => (int) env('CONTENT_CACHE_TTL', 3600),
        // Phase 2: HTTP-Caching (Browser/CDN) für anonyme Anfragen.
        'http_max_age' => (int) env('CONTENT_CACHE_HTTP_MAX_AGE', 300),
        // Phase 3: nach „Veröffentlichen" einer Seite die Navigation(en) vorrendern.
        'warm_on_publish' => env('CONTENT_CACHE_WARM', false),
    ],
];

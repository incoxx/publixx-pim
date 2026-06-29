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
    ],
];

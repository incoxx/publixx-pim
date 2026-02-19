<?php

declare(strict_types=1);

/**
 * Publixx PIM – Cache-Konfiguration
 *
 * Redis-Instanzen:
 * - cache:    Produkt-Cache, Hierarchie-Cache, PQL-Cache, Attribut-Cache
 * - queue:    Laravel Horizon Queue (getrennt von Cache)
 * - session:  Session-Speicher (getrennt)
 *
 * Erfordert Redis 7+ mit Cache-Tags-Support.
 *
 * .env Variablen:
 * CACHE_STORE=redis
 * REDIS_HOST=127.0.0.1
 * REDIS_PASSWORD=null
 * REDIS_PORT=6379
 * REDIS_CACHE_DB=1
 * REDIS_QUEUE_DB=2
 * REDIS_SESSION_DB=3
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Default Cache Store
    |--------------------------------------------------------------------------
    */

    'default' => env('CACHE_STORE', 'redis'),

    /*
    |--------------------------------------------------------------------------
    | Cache Stores
    |--------------------------------------------------------------------------
    */

    'stores' => [

        /*
         * Redis Cache Store (Hauptstore)
         *
         * Unterstützt Cache-Tags für selektive Invalidierung:
         * - Cache::tags(['product:uuid'])->put(...)
         * - Cache::tags(['product:uuid'])->flush()
         * - Cache::tags(['hierarchy:uuid'])->flush()
         * - Cache::tags(['attributes'])->flush()
         */
        'redis' => [
            'driver' => 'redis',
            'connection' => 'cache',
            'lock_connection' => 'cache',
            'prefix' => env('CACHE_PREFIX', 'pim'),
        ],

        /*
         * Array Cache (Testing)
         */
        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],

        /*
         * File Cache (Fallback)
         */
        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
            'lock_path' => storage_path('framework/cache/data'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    */

    'prefix' => env('CACHE_PREFIX', 'pim'),

    /*
    |--------------------------------------------------------------------------
    | Redis Connections
    |--------------------------------------------------------------------------
    |
    | Getrennte Redis-Datenbanken für Cache, Queue und Session.
    | Verhindert, dass ein FLUSHDB die Queues leert.
    |
    | Hinweis: Diese Konfiguration wird in config/database.php unter
    | 'redis' referenziert. Hier dokumentiert für Vollständigkeit.
    |
    | config/database.php → redis:
    |
    |   'cache' => [
    |       'url' => env('REDIS_URL'),
    |       'host' => env('REDIS_HOST', '127.0.0.1'),
    |       'username' => env('REDIS_USERNAME'),
    |       'password' => env('REDIS_PASSWORD'),
    |       'port' => env('REDIS_PORT', '6379'),
    |       'database' => env('REDIS_CACHE_DB', '1'),
    |   ],
    |
    |   'queue' => [
    |       'url' => env('REDIS_URL'),
    |       'host' => env('REDIS_HOST', '127.0.0.1'),
    |       'username' => env('REDIS_USERNAME'),
    |       'password' => env('REDIS_PASSWORD'),
    |       'port' => env('REDIS_PORT', '6379'),
    |       'database' => env('REDIS_QUEUE_DB', '2'),
    |   ],
    |
    |   'session' => [
    |       'url' => env('REDIS_URL'),
    |       'host' => env('REDIS_HOST', '127.0.0.1'),
    |       'username' => env('REDIS_USERNAME'),
    |       'password' => env('REDIS_PASSWORD'),
    |       'port' => env('REDIS_PORT', '6379'),
    |       'database' => env('REDIS_SESSION_DB', '3'),
    |   ],
    |
    */

    /*
    |--------------------------------------------------------------------------
    | PIM-spezifische TTL-Defaults (Sekunden)
    |--------------------------------------------------------------------------
    |
    | Referenz für Cache-Schichten:
    |
    | Key-Pattern                          | TTL
    | product:{id}:full                    | 3600    (1h)
    | product:{id}:lang:{lang}             | 3600    (1h)
    | hierarchy:{id}:tree                  | 21600   (6h)
    | hierarchy:{id}:node:{nid}:attrs      | 21600   (6h)
    | pql:hash:{sha256}                    | 900     (15min)
    | products:list:hash:{params}          | 300     (5min)
    | attributes:all                       | 3600    (1h)
    | export:mapping:{id}:product:{pid}    | 1800    (30min)
    */

    'ttl' => [
        'product_full' => (int) env('CACHE_TTL_PRODUCT_FULL', 3600),
        'product_lang' => (int) env('CACHE_TTL_PRODUCT_LANG', 3600),
        'hierarchy_tree' => (int) env('CACHE_TTL_HIERARCHY_TREE', 21600),
        'hierarchy_node_attrs' => (int) env('CACHE_TTL_HIERARCHY_NODE_ATTRS', 21600),
        'pql_result' => (int) env('CACHE_TTL_PQL_RESULT', 900),
        'product_list' => (int) env('CACHE_TTL_PRODUCT_LIST', 300),
        'attributes_all' => (int) env('CACHE_TTL_ATTRIBUTES_ALL', 3600),
        'export_mapping' => (int) env('CACHE_TTL_EXPORT_MAPPING', 1800),
    ],

];

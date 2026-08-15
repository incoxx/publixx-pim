<?php

return [
    'default' => env('DB_CONNECTION', 'mysql'),
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'tms'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
        ],
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => env('DB_DATABASE', database_path('tms.sqlite')),
            'prefix' => '',
        ],
    ],
    'migrations' => [
        'table' => 'migrations',
    ],
    'redis' => [
        'client' => env('REDIS_CLIENT', 'predis'),
        'default' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', 6379),
            'database' => env('REDIS_CACHE_DB', 4),
        ],

        // Der TMS hat keine eigene config/cache.php, es greift also Laravels
        // Standard — und dessen Redis-Store nutzt die Verbindung 'cache'
        // (REDIS_CACHE_CONNECTION). Fehlte sie, starb jeder Prozess, der den
        // Cache anfasst, sofort mit "Redis connection [cache] not configured".
        // Im Betrieb traf das den Queue Worker: er liest im Daemon-Betrieb bei
        // jedem Durchlauf das Restart-Signal aus dem Cache und beendete sich
        // deshalb direkt nach dem Start (Supervisor: "Exited too quickly").
        // Damit kam der Ingest zwar im TMS an, wurde aber nie verarbeitet.
        //
        // Dieselbe Datenbank wie 'default': das PIM belegt 1 (Cache), 2 (Queue)
        // und 3 (Session), der TMS bleibt vollstaendig auf 4.
        'cache' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', 6379),
            'database' => env('REDIS_CACHE_DB', 4),
        ],
    ],
];

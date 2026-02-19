<?php

declare(strict_types=1);

use Illuminate\Support\Str;

/**
 * Publixx PIM – Laravel Horizon Konfiguration
 *
 * Queue-Architektur:
 * - default:   Allgemeine Jobs
 * - indexing:   UpdateSearchIndex, RemoveFromSearchIndex
 * - cache:      Cache-Invalidierung (InvalidateProductCacheListener etc.)
 * - warmup:     WarmupCache nach Imports
 * - import:     Import-Verarbeitung (Agent 6)
 * - export:     Export-Generierung (Agent 7)
 *
 * Skalierung:
 * - indexing hat höchste Priorität (Daten müssen aktuell sein)
 * - warmup hat niedrige Priorität (kann warten)
 * - Auto-Scaling basiert auf Queue-Länge
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Horizon Domain
    |--------------------------------------------------------------------------
    */

    'domain' => env('HORIZON_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Path
    |--------------------------------------------------------------------------
    */

    'path' => env('HORIZON_PATH', 'horizon'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Connection
    |--------------------------------------------------------------------------
    */

    'use' => 'queue',

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Prefix
    |--------------------------------------------------------------------------
    */

    'prefix' => env('HORIZON_PREFIX', Str::slug(env('APP_NAME', 'pim'), '_') . '_horizon:'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Route Middleware
    |--------------------------------------------------------------------------
    */

    'middleware' => ['web', 'auth'],

    /*
    |--------------------------------------------------------------------------
    | Queue Worker Configuration
    |--------------------------------------------------------------------------
    */

    'defaults' => [
        'supervisor-indexing' => [
            'connection' => 'redis',
            'queue' => ['indexing'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 4,
            'minProcesses' => 1,
            'maxTime' => 3600,
            'maxJobs' => 1000,
            'memory' => 256,
            'tries' => 3,
            'timeout' => 120,
            'nice' => 0,
        ],
        'supervisor-cache' => [
            'connection' => 'redis',
            'queue' => ['cache'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 2,
            'minProcesses' => 1,
            'maxTime' => 3600,
            'maxJobs' => 1000,
            'memory' => 128,
            'tries' => 2,
            'timeout' => 60,
            'nice' => 0,
        ],
        'supervisor-warmup' => [
            'connection' => 'redis',
            'queue' => ['warmup'],
            'balance' => 'simple',
            'maxProcesses' => 2,
            'minProcesses' => 1,
            'maxTime' => 3600,
            'maxJobs' => 100,
            'memory' => 512,
            'tries' => 2,
            'timeout' => 600,
            'nice' => 10, // Niedrige Priorität
        ],
        'supervisor-default' => [
            'connection' => 'redis',
            'queue' => ['default', 'import', 'export'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 4,
            'minProcesses' => 1,
            'maxTime' => 3600,
            'maxJobs' => 500,
            'memory' => 256,
            'tries' => 3,
            'timeout' => 300,
            'nice' => 0,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Environments
    |--------------------------------------------------------------------------
    */

    'environments' => [
        'production' => [
            'supervisor-indexing' => [
                'maxProcesses' => 6,
                'minProcesses' => 2,
                'balanceMaxShift' => 2,
                'balanceCooldown' => 3,
            ],
            'supervisor-cache' => [
                'maxProcesses' => 3,
                'minProcesses' => 1,
            ],
            'supervisor-warmup' => [
                'maxProcesses' => 3,
                'minProcesses' => 1,
            ],
            'supervisor-default' => [
                'maxProcesses' => 6,
                'minProcesses' => 2,
                'balanceMaxShift' => 2,
                'balanceCooldown' => 3,
            ],
        ],

        'staging' => [
            'supervisor-indexing' => [
                'maxProcesses' => 2,
                'minProcesses' => 1,
            ],
            'supervisor-cache' => [
                'maxProcesses' => 1,
            ],
            'supervisor-warmup' => [
                'maxProcesses' => 1,
            ],
            'supervisor-default' => [
                'maxProcesses' => 2,
                'minProcesses' => 1,
            ],
        ],

        'local' => [
            'supervisor-indexing' => [
                'maxProcesses' => 2,
                'minProcesses' => 1,
            ],
            'supervisor-cache' => [
                'maxProcesses' => 1,
            ],
            'supervisor-warmup' => [
                'maxProcesses' => 1,
            ],
            'supervisor-default' => [
                'maxProcesses' => 2,
                'minProcesses' => 1,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Waits
    |--------------------------------------------------------------------------
    |
    | Monitoring: Benachrichtigung wenn eine Queue zu lang wartet.
    | indexing: max 30s Wartezeit (Datenaktualität kritisch)
    | warmup: max 5min Wartezeit (weniger kritisch)
    */

    'waits' => [
        'redis:indexing' => 30,
        'redis:cache' => 60,
        'redis:warmup' => 300,
        'redis:default' => 120,
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Trimming Times (Minuten)
    |--------------------------------------------------------------------------
    */

    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080, // 7 Tage
        'failed' => 10080,
        'monitored' => 10080,
    ],

    /*
    |--------------------------------------------------------------------------
    | Silenced Jobs
    |--------------------------------------------------------------------------
    */

    'silenced' => [],

    /*
    |--------------------------------------------------------------------------
    | Metrics
    |--------------------------------------------------------------------------
    */

    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fast Termination
    |--------------------------------------------------------------------------
    */

    'fast_termination' => false,

    /*
    |--------------------------------------------------------------------------
    | Memory Limit (MB)
    |--------------------------------------------------------------------------
    */

    'memory_limit' => 128,

];

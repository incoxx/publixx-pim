<?php

return [
    'api_key' => env('TMS_API_KEY', ''),
    'target_languages' => explode(',', env('TMS_TARGET_LANGUAGES', 'en,fr,es,it,nl')),

    // MT Provider configuration
    'providers' => [
        'deepl' => [
            'api_key' => env('DEEPL_API_KEY', ''),
            'api_url' => env('DEEPL_API_URL', 'https://api-free.deepl.com/v2'),
        ],
        'google' => [
            'api_key' => env('GOOGLE_TRANSLATE_API_KEY', ''),
        ],
        'claude' => [
            'api_key' => env('ANTHROPIC_API_KEY', ''),
            'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-6'),
        ],
    ],

    // Provider priority (first = primary, rest = fallback)
    'provider_chain' => explode(',', env('TMS_PROVIDER_CHAIN', 'deepl,google')),

    // Redis cache settings
    'cache_ttl' => (int) env('TMS_CACHE_TTL', 86400), // 24h
    'cache_prefix' => 'tms:t:',
];

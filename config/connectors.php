<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Connector-Konfigurationen
    |--------------------------------------------------------------------------
    |
    | Jeder Connector wird hier mit seinen API-Credentials und Einstellungen
    | konfiguriert. Credentials kommen aus der .env-Datei.
    |
    */

    'canva' => [
        'client_id'     => env('CANVA_CLIENT_ID', ''),
        'client_secret' => env('CANVA_CLIENT_SECRET', ''),
        'redirect_uri'  => env('CANVA_REDIRECT_URI', ''),
        'base_url'      => env('CANVA_API_BASE_URL', 'https://api.canva.com/rest/v1'),
        'auth_url'      => env('CANVA_AUTH_URL', 'https://www.canva.com/api/oauth/authorize'),
        'token_url'     => env('CANVA_TOKEN_URL', 'https://api.canva.com/rest/v1/oauth/token'),
        'scopes'        => ['asset:write', 'asset:read', 'design:content:read', 'design:content:write'],
    ],

    'deepl' => [
        'api_key'  => env('DEEPL_API_KEY', ''),
        'base_url' => env('DEEPL_BASE_URL', 'https://api-free.deepl.com/v2'),
    ],

    'shopware' => [
        'shop_url'      => env('SHOPWARE_SHOP_URL', ''),
        'client_id'     => env('SHOPWARE_CLIENT_ID', ''),
        'client_secret' => env('SHOPWARE_CLIENT_SECRET', ''),
    ],

    'cloudinary' => [
        'cloud_name' => env('CLOUDINARY_CLOUD_NAME', ''),
        'api_key'    => env('CLOUDINARY_API_KEY', ''),
        'api_secret' => env('CLOUDINARY_API_SECRET', ''),
    ],

    'claude_ai' => [
        'api_key'    => env('CLAUDE_AI_API_KEY', ''),
        'base_url'   => env('CLAUDE_AI_BASE_URL', 'https://api.anthropic.com/v1'),
        'model'      => env('CLAUDE_AI_MODEL', 'claude-sonnet-4-20250514'),
        'max_tokens' => env('CLAUDE_AI_MAX_TOKENS', 1024),
    ],

];

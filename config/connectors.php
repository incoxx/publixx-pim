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

    'shopify' => [
        'shop_url'      => env('SHOPIFY_SHOP_URL', ''),        // z.B. https://mein-shop.myshopify.com
        'access_token'  => env('SHOPIFY_ACCESS_TOKEN', ''),     // Legacy: statischer Admin API Access Token
        'client_id'     => env('SHOPIFY_CLIENT_ID', ''),        // Neu (ab 2026): Client ID
        'client_secret' => env('SHOPIFY_CLIENT_SECRET', ''),    // Neu (ab 2026): Client Secret
    ],

    'cloudinary' => [
        'cloud_name' => env('CLOUDINARY_CLOUD_NAME', ''),
        'api_key'    => env('CLOUDINARY_API_KEY', ''),
        'api_secret' => env('CLOUDINARY_API_SECRET', ''),
    ],

    'claude_ai' => [
        'api_key'    => env('CLAUDE_AI_API_KEY', ''),
        'base_url'   => env('CLAUDE_AI_BASE_URL', 'https://api.anthropic.com/v1'),
        'model'      => env('CLAUDE_AI_MODEL', 'claude-sonnet-4-5-20250929'),
        'max_tokens' => env('CLAUDE_AI_MAX_TOKENS', 1024),
    ],

    'openai' => [
        'api_key'    => env('OPENAI_API_KEY', ''),
        'base_url'   => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'model'      => env('OPENAI_MODEL', 'gpt-4o'),
        'max_tokens' => env('OPENAI_MAX_TOKENS', 4096),
    ],

];

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

];

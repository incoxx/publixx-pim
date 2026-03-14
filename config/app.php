<?php

return [
    'name' => env('APP_NAME', 'anyPIM'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'timezone' => 'Europe/Berlin',
    'locale' => 'de',
    'fallback_locale' => 'en',
    'faker_locale' => 'de_DE',
    'cipher' => 'AES-256-CBC',
    'key' => env('APP_KEY'),
    'maintenance' => [
        'driver' => 'file',
    ],

    /*
    |--------------------------------------------------------------------------
    | Deploy User
    |--------------------------------------------------------------------------
    |
    | System-User für Deployment-Befehle (git, composer, npm).
    | Wenn gesetzt, werden Befehle mit "sudo -u {deploy_user}" ausgeführt.
    | Nur nötig, wenn PHP (www-data) nicht der Datei-Eigentümer ist.
    |
    */
    'deploy_user' => env('DEPLOY_USER'),
];

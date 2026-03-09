<?php

return [
    'name' => env('APP_NAME', 'TMS'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost:8001'),
    'timezone' => 'Europe/Berlin',
    'locale' => 'de',
    'key' => env('APP_KEY'),
    'cipher' => 'AES-256-CBC',
];

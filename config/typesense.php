<?php

declare(strict_types=1);

return [
    'host' => env('TYPESENSE_HOST', 'localhost'),
    'port' => env('TYPESENSE_PORT', '8108'),
    'protocol' => env('TYPESENSE_PROTOCOL', 'http'),
    'api_key' => env('TYPESENSE_API_KEY', ''),
];

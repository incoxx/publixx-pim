<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:3000')],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => ['X-Total-Count', 'X-Current-Page', 'X-Last-Page', 'X-Per-Page', 'X-Category-Counts'],
    'max_age' => 0,
    'supports_credentials' => true,
];

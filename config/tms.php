<?php

return [
    'base_url' => env('TMS_BASE_URL', 'http://localhost:8001/api'),
    'api_key' => env('TMS_API_KEY', ''),
    'timeout' => (int) env('TMS_TIMEOUT', 2),
    'enabled' => (bool) env('TMS_ENABLED', false),
    'target_languages' => array_filter(explode(',', env('TMS_TARGET_LANGUAGES', 'en,fr,es,it,nl'))),
];

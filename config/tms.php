<?php

return [
    'base_url' => env('TMS_BASE_URL', 'http://127.0.0.1:8001/api'),
    'api_key' => env('TMS_API_KEY', ''),
    'timeout' => (int) env('TMS_TIMEOUT', 5),

    // Standardmaessig aktiv: das Translation Memory ist fuer jede Installation
    // relevant, und setup.sh/update.sh richten den Dienst bei jedem Lauf ein.
    // Wer es ausdruecklich nicht will, setzt TMS_ENABLED=false in der .env —
    // eine vorhandene Festlegung wird von den Skripten nie ueberschrieben.
    'enabled' => (bool) env('TMS_ENABLED', true),

    // Nur noch Rueckfallebene. Massgeblich ist die Tabelle `languages`
    // (App\Models\Language), gepflegt unter Uebersetzungen -> Sprachen.
    'target_languages' => array_filter(array_map(
        'trim',
        explode(',', (string) env('TMS_TARGET_LANGUAGES', 'en,fr,es,it,nl')),
    )),
];

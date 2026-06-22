<?php

declare(strict_types=1);

/**
 * Konfiguration der Video-Engine (Social-Video-Rendering).
 *
 * WICHTIG: Diese Werte werden über config() gelesen, NICHT über env() im Code.
 * Grund: Bei gecachter Config (`php artisan config:cache`, in Produktion üblich)
 * lädt Laravel die .env zur Laufzeit nicht mehr – direkte env()-Aufrufe liefern
 * dann null. Über eine Config-Datei werden die Werte beim Cachen eingefroren und
 * bleiben verfügbar.
 */
return [
    // Basis-URL, die die Engine für Playwright-Aufnahmen anspricht.
    'base_url' => env('VIDEO_ENGINE_BASE_URL'),

    // Aufnahme-Parameter
    'display' => env('VIDEO_ENGINE_DISPLAY', ':99'),
    'fps'     => env('VIDEO_ENGINE_FPS', '30'),
    'quality' => env('VIDEO_ENGINE_QUALITY', 'high'),

    // Binär-Pfade. Leer lassen → die Engine löst ffmpeg (PATH) und Chromium
    // (PLAYWRIGHT_BROWSERS_PATH bzw. Playwright-Standard) selbst auf.
    'ffmpeg_path'   => env('VIDEO_ENGINE_FFMPEG_PATH'),
    'chromium_path' => env('VIDEO_ENGINE_CHROMIUM_PATH'),

    // Verzeichnis der vorinstallierten Playwright-Browser. Muss für den
    // ausführenden User (Webserver/Queue-Worker) lesbar sein.
    'browsers_path' => env('PLAYWRIGHT_BROWSERS_PATH'),

    // Sprachsynthese (ElevenLabs, optional)
    'elevenlabs' => [
        'api_key'  => env('ELEVENLABS_API_KEY'),
        'fallback' => env('ELEVENLABS_FALLBACK', 'gtts'),
    ],
];

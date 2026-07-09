<?php

use App\Http\Controllers\CatalogEmbedController;
use App\Http\Controllers\CollectionShareLinkPublicController;
use App\Http\Controllers\PortalEmbedController;
use Illuminate\Support\Facades\Route;

// ── Catalog Embed Templates ──
Route::get('/catalog-embed', [CatalogEmbedController::class, 'index']);
Route::get('/catalog-embed/{template}', [CatalogEmbedController::class, 'show']);
Route::get('/catalog-embed-assets/{file}', [CatalogEmbedController::class, 'asset'])
    ->where('file', '.+');

// ── Portal Embed (Vorschaltseiten) ──
Route::get('/portal/{slug}', [PortalEmbedController::class, 'show']);
Route::get('/portal-embed-assets/{file}', [PortalEmbedController::class, 'asset'])
    ->where('file', '.+');

// ── Collection Freigabe-Links (passwortgeschuetzte Angebots-Ansicht) ──
// Rate-Limit auf den Passwort-Check, um Brute-Force gegen das manuell vergebene Passwort zu bremsen.
Route::get('/shared/collections/{token}', [CollectionShareLinkPublicController::class, 'show']);
Route::post('/shared/collections/{token}', [CollectionShareLinkPublicController::class, 'unlock'])
    ->middleware('throttle:10,1');

// Serve SPA for all non-API routes
Route::get('/{any?}', function () {
    return file_get_contents(public_path('spa.html'));
})->where('any', '^(?!api|horizon|up|docs|web/help|catalog-embed|catalog-embed-assets|portal|portal-embed-assets|shared).*$');

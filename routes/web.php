<?php

use App\Http\Controllers\CatalogEmbedController;
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

// Serve SPA for all non-API routes
Route::get('/{any?}', function () {
    return file_get_contents(public_path('spa.html'));
})->where('any', '^(?!api|horizon|up|docs|web/help|catalog-embed|catalog-embed-assets|portal|portal-embed-assets).*$');

<?php

use App\Http\Controllers\CatalogEmbedController;
use Illuminate\Support\Facades\Route;

// ── Catalog Embed Templates ──
// Serves customizable HTML catalog templates under /catalog-embed/
// No auth on the HTML page itself — the catalog API endpoints it calls
// are already protected by the catalog.access middleware.
Route::get('/catalog-embed', [CatalogEmbedController::class, 'index']);
Route::get('/catalog-embed/{template}', [CatalogEmbedController::class, 'show']);

// Serve SPA for all non-API routes
Route::get('/{any?}', function () {
    return file_get_contents(public_path('spa.html'));
})->where('any', '^(?!api|horizon|up|docs|catalog-embed).*$');

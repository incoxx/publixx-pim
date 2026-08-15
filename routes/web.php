<?php

use App\Http\Controllers\CatalogEmbedController;
use App\Http\Controllers\CollectionShareLinkPublicController;
use Illuminate\Support\Facades\Route;

// ── robots.txt ──
// Muss VOR dem SPA-Catch-all stehen (Laravel matcht in Registrierungsreihenfolge).
// Als Route statt statischer Datei, weil die "Sitemap:"-Zeile die absolute URL inkl.
// Domain braucht -- die darf nirgends hardcodiert sein und wird daher zur Laufzeit
// aus app.url abgeleitet.
Route::get('/robots.txt', function () {
    $appUrl = rtrim((string) config('app.url'), '/');
    $basePath = rtrim((string) parse_url($appUrl, PHP_URL_PATH), '/');

    // Achtung, Asymmetrie (identisch zu setup.sh/update.sh): Im Root-Modus laeuft die App
    // unter "/", die Doku aber per fixer Konvention unter "/web/help/". Im Unterverzeichnis-
    // Modus liegt die Doku unter "${WEB_PATH}/help/" -- und ${WEB_PATH} steckt bereits in
    // app.url. Deshalb wird an $appUrl einmal "/web/help" und einmal nur "/help" angehaengt.
    $docsSuffix = $basePath === '' ? '/web/help' : '/help';
    $docsPath = $basePath.$docsSuffix;

    // Restriktiv: nur der Hilfe-Bereich ist indexierbar. Sperrt damit auch die
    // Freigabe-Links unter /shared/collections/{token}, deren Token nicht in einen
    // Suchindex gehoert.
    $body = "User-agent: *\n"
        ."Disallow: /\n"
        ."Allow: {$docsPath}/\n"
        ."\n"
        ."Sitemap: {$appUrl}{$docsSuffix}/sitemap.xml\n";

    return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
});

// ── Catalog Embed Templates ──
Route::get('/catalog-embed', [CatalogEmbedController::class, 'index']);
Route::get('/catalog-embed/{template}', [CatalogEmbedController::class, 'show']);
Route::get('/catalog-embed-assets/{file}', [CatalogEmbedController::class, 'asset'])
    ->where('file', '.+');

// ── Collection Freigabe-Links (passwortgeschuetzte Angebots-Ansicht) ──
// Rate-Limit auf den Passwort-Check, um Brute-Force gegen das manuell vergebene Passwort zu bremsen.
Route::get('/shared/collections/{token}', [CollectionShareLinkPublicController::class, 'show']);
Route::post('/shared/collections/{token}', [CollectionShareLinkPublicController::class, 'unlock'])
    ->middleware('throttle:10,1');

// Serve SPA for all non-API routes
Route::get('/{any?}', function () {
    return file_get_contents(public_path('spa.html'));
})->where('any', '^(?!api|horizon|up|docs|web/help|catalog-embed|catalog-embed-assets|shared).*$');

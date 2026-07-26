<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sperrt Routen für Besucher, die den Katalog nur über einen Freigabelink geöffnet
 * haben (Attribut catalog_share_access, gesetzt von CatalogAccessControl). Damit kann
 * ein Freigabelink den Katalog zwar durchstöbern, aber keine schreibenden Aktionen wie
 * Warenkorb-Bestellungen auslösen. Läuft NACH catalog.access.
 */
class RejectCatalogShareAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->attributes->get('catalog_share_access') === true) {
            abort(403, 'Über einen Freigabelink nicht verfügbar.');
        }

        return $next($request);
    }
}

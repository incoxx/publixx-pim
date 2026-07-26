<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\CollectionShareLink;
use App\Models\WebsiteProfile;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpFoundation\Response;

class CatalogAccessControl
{
    public function handle(Request $request, Closure $next): Response
    {
        $payload = WebsiteProfile::getActivePayload();
        $mode = $payload['catalog_access_mode'] ?? 'public';

        if ($mode === 'login'
            && !Auth::guard('web')->check()
            && !Auth::guard('sanctum')->check()
            && !$this->hasValidShareAccess($request)
        ) {
            abort(401, 'Authentication required.');
        }

        return $next($request);
    }

    /**
     * Prüft das Katalog-Freigabelink-Access-Token (Header X-Catalog-Share). Es wird von
     * CatalogController::shareUnlock() nach erfolgreicher Token-/Passwort-Prüfung ausgegeben,
     * ist mit dem APP_KEY verschlüsselt (nicht fälschbar) und kurzlebig. Damit erhält ein
     * Freigabelink-Empfänger Katalogzugang, ohne sich im PIM anzumelden.
     */
    private function hasValidShareAccess(Request $request): bool
    {
        $raw = $request->header('X-Catalog-Share');

        if (!is_string($raw) || $raw === '') {
            return false;
        }

        try {
            $payload = Crypt::decrypt($raw);
        } catch (\Throwable) {
            return false;
        }

        $token = is_array($payload) ? ($payload['t'] ?? null) : null;
        $exp = is_array($payload) ? (int) ($payload['exp'] ?? 0) : 0;

        if (!is_string($token) || $exp < now()->getTimestamp()) {
            return false;
        }

        $link = CollectionShareLink::where('token', $token)->first();

        return $link !== null && !$link->isExpired();
    }
}

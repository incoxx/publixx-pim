<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sanctum-Tokens ohne die '*'-Fähigkeit (z.B. der "catalog:read"-Embed-Token aus
 * SettingController::generateTypo3IntegrationEmbedToken()) dürfen ausschließlich
 * die öffentliche Katalog-API erreichen — unabhängig von der PIM-Rolle des
 * zugehörigen Benutzers. So bleibt ein Token, der im Quelltext einer externen
 * Website landet, garantiert auf reinen Katalog-Lesezugriff beschränkt.
 *
 * Normale Session-Logins (TransientToken, immer '*') sind hiervon nicht betroffen.
 */
class RestrictScopedApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = Auth::guard('sanctum')->user()?->currentAccessToken();

        if ($token && !$token->can('*') && !$request->is('api/v1/catalog/*')) {
            abort(403, 'Dieser Token ist auf den Katalog-Lesezugriff beschränkt.');
        }

        return $next($request);
    }
}

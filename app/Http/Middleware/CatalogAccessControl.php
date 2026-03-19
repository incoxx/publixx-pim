<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\WebsiteProfile;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CatalogAccessControl
{
    public function handle(Request $request, Closure $next): Response
    {
        $payload = WebsiteProfile::getActivePayload();
        $mode = $payload['catalog_access_mode'] ?? 'public';

        if ($mode === 'login' && !Auth::guard('web')->check() && !Auth::guard('sanctum')->check()) {
            abort(401, 'Authentication required.');
        }

        return $next($request);
    }
}

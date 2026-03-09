<?php

declare(strict_types=1);

namespace Tms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('tms.api_key');

        if (empty($expected)) {
            return $next($request);
        }

        $token = $request->bearerToken();

        if (!$token || !hash_equals($expected, $token)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}

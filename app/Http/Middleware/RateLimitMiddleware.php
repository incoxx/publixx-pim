<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Custom Rate Limiting Middleware.
 *
 * Tiers: standard (60/min), export (600/min), auth (10/min).
 */
class RateLimitMiddleware
{
    public function __construct(
        private readonly RateLimiter $limiter,
    ) {}

    public function handle(Request $request, Closure $next, string $tier = 'standard'): Response
    {
        $maxAttempts = match ($tier) {
            'export' => 600,
            'auth' => 10,
            default => 60,
        };

        $key = $this->resolveRequestSignature($request, $tier);

        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = $this->limiter->availableIn($key);

            return response()->json([
                'type' => 'https://publixx-pim.local/problems/rate-limit-exceeded',
                'title' => 'Too Many Requests',
                'detail' => "Rate limit exceeded. Try again in {$retryAfter} seconds.",
                'status' => Response::HTTP_TOO_MANY_REQUESTS,
                'retry_after' => $retryAfter,
            ], Response::HTTP_TOO_MANY_REQUESTS, [
                'Content-Type' => 'application/problem+json',
                'Retry-After' => (string) $retryAfter,
                'X-RateLimit-Limit' => (string) $maxAttempts,
                'X-RateLimit-Remaining' => '0',
            ]);
        }

        $this->limiter->hit($key, 60);

        $response = $next($request);

        $response->headers->set('X-RateLimit-Limit', (string) $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', (string) $this->limiter->remaining($key, $maxAttempts));

        return $response;
    }

    private function resolveRequestSignature(Request $request, string $tier): string
    {
        $userId = $request->user()?->id ?? $request->ip();

        return "pim_rate_limit:{$tier}:{$userId}";
    }
}

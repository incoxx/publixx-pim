<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\LicenseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckModuleLicense
{
    public function __construct(
        private readonly LicenseService $license,
    ) {}

    /**
     * Handle an incoming request.
     *
     * Usage in routes: ->middleware('module:bmecat')
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        if (! $this->license->isModuleActive($module)) {
            $moduleName = config("license.modules.{$module}.name", $module);

            return response()->json([
                'type' => 'https://anypim.io/problems/license/module-not-licensed',
                'title' => 'Module Not Licensed',
                'detail' => "Das Modul '{$moduleName}' erfordert eine Enterprise-Lizenz.",
                'status' => 403,
                'module' => $module,
            ], 403, [
                'Content-Type' => 'application/problem+json',
            ]);
        }

        return $next($request);
    }
}

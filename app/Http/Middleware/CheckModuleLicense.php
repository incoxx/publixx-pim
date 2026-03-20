<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Setting;
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
        if ($this->license->isModuleActive($module)) {
            return $next($request);
        }

        // Allow 'connectors' module when API keys are configured
        if ($module === 'connectors' && $this->hasConfiguredConnectors()) {
            return $next($request);
        }

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

    private function hasConfiguredConnectors(): bool
    {
        try {
            $payload = Setting::getPayload('connector_credentials');
        } catch (\Throwable) {
            return false;
        }

        if ($payload) {
            foreach ($payload as $fields) {
                if (collect($fields)->some(fn ($v) => ! empty($v))) {
                    return true;
                }
            }
        }

        // Check .env-based config
        return ! empty(config('connectors.deepl.api_key'))
            || ! empty(config('connectors.canva.client_id'))
            || ! empty(config('connectors.shopware.client_id'))
            || ! empty(config('connectors.cloudinary.api_key'))
            || ! empty(config('connectors.claude_ai.api_key'))
            || ! empty(config('connectors.openai.api_key'));
    }
}

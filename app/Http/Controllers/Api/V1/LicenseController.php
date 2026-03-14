<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Services\LicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LicenseController extends Controller
{
    public function __construct(
        private readonly LicenseService $license,
    ) {}

    /**
     * GET /api/v1/license
     *
     * Return current license status and module overview.
     */
    public function show(Request $request): JsonResponse
    {
        $info = $this->license->getLicenseInfo();
        $modules = $this->license->getModuleOverview();

        return response()->json([
            'data' => [
                'edition' => $info ? 'enterprise' : 'community',
                'valid' => $info['valid'] ?? false,
                'customer' => $info['customer'] ?? null,
                'expires_at' => $info['expires_at'] ?? null,
                'days_remaining' => $this->license->daysRemaining(),
                'max_users' => $info['max_users'] ?? 0,
                'current_users' => User::count(),
                'modules' => $modules,
            ],
        ]);
    }

    /**
     * PUT /api/v1/license
     *
     * Activate or clear a license key. Admin only.
     */
    public function update(Request $request): JsonResponse
    {
        $this->authorize('update', User::class);

        $validated = $request->validate([
            'license_key' => ['present', 'nullable', 'string', 'max:2000'],
        ]);

        $result = $this->license->activateLicense($validated['license_key'] ?? '');

        if (! $result['valid']) {
            return response()->json([
                'type' => 'https://anypim.io/problems/license/invalid-key',
                'title' => 'Invalid License Key',
                'detail' => $result['error'],
                'status' => 422,
            ], 422, [
                'Content-Type' => 'application/problem+json',
            ]);
        }

        // Return fresh status
        return $this->show($request);
    }
}

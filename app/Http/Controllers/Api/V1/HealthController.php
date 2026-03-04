<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    /**
     * GET /api/v1/health
     *
     * Public healthcheck endpoint — no authentication required.
     * Returns status of all critical services.
     */
    public function __invoke(): JsonResponse
    {
        $checks = [];
        $allHealthy = true;

        // 1. Application
        $checks['app'] = [
            'status' => 'ok',
            'version' => config('app.name', 'Publixx PIM'),
            'environment' => config('app.env'),
            'debug' => config('app.debug'),
        ];

        // 2. Database
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $dbTime = round((microtime(true) - $start) * 1000, 1);
            $checks['database'] = [
                'status' => 'ok',
                'connection' => config('database.default'),
                'response_ms' => $dbTime,
            ];
        } catch (\Throwable $e) {
            $checks['database'] = ['status' => 'error', 'message' => 'Connection failed'];
            $allHealthy = false;
        }

        // 3. Redis / Cache
        try {
            $start = microtime(true);
            Cache::store()->put('health_check', now()->toIso8601String(), 10);
            $cacheValue = Cache::store()->get('health_check');
            $cacheTime = round((microtime(true) - $start) * 1000, 1);
            $checks['cache'] = [
                'status' => $cacheValue ? 'ok' : 'error',
                'driver' => config('cache.default'),
                'response_ms' => $cacheTime,
            ];
            if (!$cacheValue) {
                $allHealthy = false;
            }
        } catch (\Throwable $e) {
            $checks['cache'] = ['status' => 'error', 'driver' => config('cache.default'), 'message' => 'Connection failed'];
            $allHealthy = false;
        }

        // 4. Storage writable
        $storagePath = storage_path('app');
        $checks['storage'] = [
            'status' => is_writable($storagePath) ? 'ok' : 'error',
            'path' => $storagePath,
        ];
        if (!is_writable($storagePath)) {
            $allHealthy = false;
        }

        // 5. Queue (Horizon)
        try {
            $horizonStatus = 'unknown';
            if (class_exists(\Laravel\Horizon\Contracts\MasterSupervisorRepository::class)) {
                $masters = app(\Laravel\Horizon\Contracts\MasterSupervisorRepository::class)->all();
                $horizonStatus = !empty($masters) ? 'running' : 'stopped';
            }
            $checks['queue'] = [
                'status' => $horizonStatus === 'running' ? 'ok' : 'warn',
                'driver' => config('queue.default'),
                'horizon' => $horizonStatus,
            ];
        } catch (\Throwable $e) {
            $checks['queue'] = ['status' => 'warn', 'horizon' => 'unavailable'];
        }

        // 6. Disk space
        $freeBytes = disk_free_space(base_path());
        $totalBytes = disk_total_space(base_path());
        $freeGb = $freeBytes ? round($freeBytes / 1073741824, 1) : 0;
        $usedPercent = ($totalBytes && $freeBytes) ? round((1 - $freeBytes / $totalBytes) * 100) : 0;
        $checks['disk'] = [
            'status' => $freeGb > 1 ? 'ok' : ($freeGb > 0.2 ? 'warn' : 'error'),
            'free_gb' => $freeGb,
            'used_percent' => $usedPercent,
        ];
        if ($freeGb <= 0.2) {
            $allHealthy = false;
        }

        // Summary
        $status = $allHealthy ? 'healthy' : 'degraded';

        return response()->json([
            'status' => $status,
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ], $allHealthy ? 200 : 503);
    }
}

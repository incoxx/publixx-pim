<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Process;

class SystemInfoController extends Controller
{
    /**
     * GET /api/v1/admin/env-info
     *
     * Parses the actual .env file on disk and compares against .env.example.
     * Works even when the app runs in a subdirectory.
     */
    public function envInfo(Request $request): JsonResponse
    {
        if (!$request->user()?->hasRole('Admin')) {
            abort(403, 'Unauthorized.');
        }

        $envValues = $this->parseEnvFile(base_path('.env'));
        $exampleValues = $this->parseEnvFile(base_path('.env.example'));

        // All known keys = union of .env and .env.example
        $allKeys = array_unique(array_merge(array_keys($envValues), array_keys($exampleValues)));

        $groups = $this->getEnvGroups();
        $categorized = [];
        foreach ($allKeys as $key) {
            $categorized[$key] = false;
        }

        $result = [];

        foreach ($groups as $group => $keys) {
            $items = [];
            foreach ($keys as $key => $description) {
                $value = $envValues[$key] ?? null;
                $isSet = $value !== null && $value !== '';
                $items[] = [
                    'key' => $key,
                    'description' => $description,
                    'is_set' => $isSet,
                    'value' => $this->maskValue($key, $value),
                    'in_example' => array_key_exists($key, $exampleValues),
                ];
                $categorized[$key] = true;
            }
            $result[] = [
                'group' => $group,
                'items' => $items,
            ];
        }

        // Collect uncategorized keys from .env that aren't in our groups
        $extraItems = [];
        foreach ($allKeys as $key) {
            if (!($categorized[$key] ?? false)) {
                $value = $envValues[$key] ?? null;
                $isSet = $value !== null && $value !== '';
                $extraItems[] = [
                    'key' => $key,
                    'description' => '',
                    'is_set' => $isSet,
                    'value' => $this->maskValue($key, $value),
                    'in_example' => array_key_exists($key, $exampleValues),
                ];
            }
        }
        if (!empty($extraItems)) {
            // Sort alphabetically
            usort($extraItems, fn ($a, $b) => strcmp($a['key'], $b['key']));
            $result[] = [
                'group' => 'Weitere',
                'items' => $extraItems,
            ];
        }

        $envPath = base_path('.env');
        $examplePath = base_path('.env.example');

        return response()->json([
            'data' => $result,
            'meta' => [
                'env_file_exists' => file_exists($envPath),
                'env_file_path' => $envPath,
                'example_file_exists' => file_exists($examplePath),
                'base_path' => base_path(),
            ],
        ]);
    }

    /**
     * GET /api/v1/admin/system-status
     *
     * Returns the status of all system services, RAM, and disk usage.
     */
    public function systemStatus(Request $request): JsonResponse
    {
        if (!$request->user()?->hasRole('Admin')) {
            abort(403, 'Unauthorized.');
        }

        return response()->json([
            'data' => [
                'services' => $this->getServiceStatuses(),
                'resources' => $this->getResourceUsage(),
                'php' => $this->getPhpInfo(),
            ],
        ]);
    }

    /**
     * Parse a .env file into a key => value array.
     */
    private function parseEnvFile(string $path): array
    {
        if (!file_exists($path)) {
            return [];
        }

        $values = [];
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip comments
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Must contain =
            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Remove surrounding quotes
            if ((str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }

            $values[$key] = $value;
        }

        return $values;
    }

    private function getServiceStatuses(): array
    {
        $services = [];

        // MySQL
        try {
            DB::select('SELECT 1');
            $version = DB::select("SELECT VERSION() as v")[0]->v ?? 'unknown';
            $services[] = [
                'name' => 'MySQL',
                'status' => 'running',
                'version' => $version,
            ];
        } catch (\Throwable $e) {
            $services[] = [
                'name' => 'MySQL',
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }

        // Redis
        try {
            $pong = Redis::ping();
            $info = Redis::info('server');
            $services[] = [
                'name' => 'Redis',
                'status' => $pong ? 'running' : 'error',
                'version' => $info['redis_version'] ?? 'unknown',
            ];
        } catch (\Throwable $e) {
            $services[] = [
                'name' => 'Redis',
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }

        // Typesense
        try {
            $host = config('typesense.host', 'localhost');
            $port = config('typesense.port', '8108');
            $protocol = config('typesense.protocol', 'http');
            $apiKey = config('typesense.api_key', '');

            if (!empty($apiKey)) {
                $ctx = stream_context_create([
                    'http' => [
                        'timeout' => 3,
                        'header' => "X-TYPESENSE-API-KEY: {$apiKey}\r\n",
                    ],
                ]);
                $health = @file_get_contents("{$protocol}://{$host}:{$port}/health", false, $ctx);
                if ($health !== false) {
                    $data = json_decode($health, true);
                    $services[] = [
                        'name' => 'Typesense',
                        'status' => ($data['ok'] ?? false) ? 'running' : 'error',
                    ];
                } else {
                    $services[] = [
                        'name' => 'Typesense',
                        'status' => 'error',
                        'error' => 'Not reachable',
                    ];
                }
            } else {
                $services[] = [
                    'name' => 'Typesense',
                    'status' => 'not_configured',
                ];
            }
        } catch (\Throwable $e) {
            $services[] = [
                'name' => 'Typesense',
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }

        // Laravel Horizon
        try {
            $horizonStatus = 'unknown';
            $result = Process::timeout(5)->run(['php', base_path('artisan'), 'horizon:status']);
            $output = trim($result->output());
            if (str_contains($output, 'running')) {
                $horizonStatus = 'running';
            } elseif (str_contains($output, 'paused')) {
                $horizonStatus = 'paused';
            } elseif (str_contains($output, 'inactive')) {
                $horizonStatus = 'stopped';
            }
            $services[] = [
                'name' => 'Horizon',
                'status' => $horizonStatus,
            ];
        } catch (\Throwable $e) {
            $services[] = [
                'name' => 'Horizon',
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }

        // Apache
        $services[] = $this->checkSystemdService('Apache', 'apache2');

        // Supervisor
        $services[] = $this->checkSystemdService('Supervisor', 'supervisor');

        return $services;
    }

    private function checkSystemdService(string $name, string $unit): array
    {
        try {
            $result = Process::timeout(5)->run(['systemctl', 'is-active', $unit]);
            $status = trim($result->output());

            return [
                'name' => $name,
                'status' => $status === 'active' ? 'running' : $status,
            ];
        } catch (\Throwable) {
            return [
                'name' => $name,
                'status' => 'unknown',
            ];
        }
    }

    private function getResourceUsage(): array
    {
        $ram = [];
        $disk = [];

        // RAM
        try {
            $meminfo = @file_get_contents('/proc/meminfo');
            if ($meminfo) {
                preg_match('/MemTotal:\s+(\d+)/', $meminfo, $total);
                preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $available);

                $totalMb = isset($total[1]) ? (int) round((int) $total[1] / 1024) : 0;
                $availableMb = isset($available[1]) ? (int) round((int) $available[1] / 1024) : 0;
                $usedMb = $totalMb - $availableMb;

                $ram = [
                    'total_mb' => $totalMb,
                    'used_mb' => $usedMb,
                    'available_mb' => $availableMb,
                    'percent' => $totalMb > 0 ? round($usedMb / $totalMb * 100, 1) : 0,
                ];
            }
        } catch (\Throwable) {
            // ignore
        }

        // Disk
        try {
            $path = base_path();
            $totalBytes = disk_total_space($path);
            $freeBytes = disk_free_space($path);

            if ($totalBytes !== false && $freeBytes !== false) {
                $totalGb = round($totalBytes / 1073741824, 1);
                $freeGb = round($freeBytes / 1073741824, 1);
                $usedGb = round(($totalBytes - $freeBytes) / 1073741824, 1);

                $disk = [
                    'total_gb' => $totalGb,
                    'used_gb' => $usedGb,
                    'free_gb' => $freeGb,
                    'percent' => $totalGb > 0 ? round($usedGb / $totalGb * 100, 1) : 0,
                    'path' => $path,
                ];
            }
        } catch (\Throwable) {
            // ignore
        }

        return [
            'ram' => $ram,
            'disk' => $disk,
        ];
    }

    private function getPhpInfo(): array
    {
        return [
            'version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'extensions' => [
                'gd' => extension_loaded('gd'),
                'redis' => extension_loaded('redis'),
                'intl' => extension_loaded('intl'),
                'mbstring' => extension_loaded('mbstring'),
                'zip' => extension_loaded('zip'),
                'bcmath' => extension_loaded('bcmath'),
            ],
        ];
    }

    /**
     * Mask sensitive values — show only that they are set, not the actual value.
     */
    private function maskValue(string $key, ?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $sensitive = [
            'APP_KEY', 'DB_PASSWORD', 'REDIS_PASSWORD',
            'TYPESENSE_API_KEY', 'TMS_API_KEY',
            'AZURE_AD_CLIENT_SECRET', 'ANYPIM_LICENSE_KEY',
            'ANYPIM_LICENSE_PUBLIC_KEY',
        ];

        if (in_array($key, $sensitive, true)) {
            return str_repeat('*', min(strlen($value), 8));
        }

        if (str_contains(strtolower($key), 'password') || str_contains(strtolower($key), 'secret')) {
            return str_repeat('*', min(strlen($value), 8));
        }

        return $value;
    }

    private function getEnvGroups(): array
    {
        return [
            'Anwendung' => [
                'APP_NAME' => 'Name der Anwendung',
                'APP_ENV' => 'Umgebung (local, production)',
                'APP_KEY' => 'Verschluesselungsschluessel',
                'APP_DEBUG' => 'Debug-Modus',
                'APP_URL' => 'Basis-URL der Anwendung',
            ],
            'Logging' => [
                'LOG_CHANNEL' => 'Log-Kanal',
                'LOG_DEPRECATIONS_CHANNEL' => 'Deprecation-Log-Kanal',
                'LOG_LEVEL' => 'Log-Level (debug, info, warning, error)',
            ],
            'Datenbank' => [
                'DB_CONNECTION' => 'Datenbank-Treiber',
                'DB_HOST' => 'Datenbank-Host',
                'DB_PORT' => 'Datenbank-Port',
                'DB_DATABASE' => 'Datenbankname',
                'DB_USERNAME' => 'Datenbank-Benutzer',
                'DB_PASSWORD' => 'Datenbank-Passwort',
            ],
            'Redis' => [
                'REDIS_CLIENT' => 'Redis-Client (phpredis, predis)',
                'REDIS_HOST' => 'Redis-Host',
                'REDIS_PASSWORD' => 'Redis-Passwort',
                'REDIS_PORT' => 'Redis-Port',
                'REDIS_CACHE_DB' => 'Redis DB fuer Cache',
                'REDIS_QUEUE_DB' => 'Redis DB fuer Queues',
                'REDIS_SESSION_DB' => 'Redis DB fuer Sessions',
            ],
            'Cache' => [
                'CACHE_STORE' => 'Cache-Treiber',
                'CACHE_PREFIX' => 'Cache-Prefix',
                'CACHE_TTL_PRODUCT_FULL' => 'TTL: Produkt (komplett)',
                'CACHE_TTL_PRODUCT_LANG' => 'TTL: Produkt (Sprache)',
                'CACHE_TTL_HIERARCHY_TREE' => 'TTL: Hierarchie-Baum',
                'CACHE_TTL_PQL_RESULT' => 'TTL: PQL-Ergebnis',
                'CACHE_TTL_PRODUCT_LIST' => 'TTL: Produktliste',
                'CACHE_TTL_ATTRIBUTES_ALL' => 'TTL: Alle Attribute',
                'CACHE_TTL_EXPORT_MAPPING' => 'TTL: Export-Mapping',
            ],
            'Queue & Horizon' => [
                'QUEUE_CONNECTION' => 'Queue-Treiber',
                'HORIZON_PREFIX' => 'Horizon Redis-Prefix',
            ],
            'Session' => [
                'SESSION_DRIVER' => 'Session-Treiber',
                'SESSION_LIFETIME' => 'Session-Dauer (Minuten)',
                'SESSION_SECURE_COOKIE' => 'Nur HTTPS-Cookies',
                'SESSION_COOKIE_PATH' => 'Cookie-Pfad',
            ],
            'Sanctum' => [
                'SANCTUM_STATEFUL_DOMAINS' => 'Stateful Domains fuer SPA',
            ],
            'Typesense' => [
                'TYPESENSE_HOST' => 'Typesense-Host',
                'TYPESENSE_PORT' => 'Typesense-Port',
                'TYPESENSE_PROTOCOL' => 'Typesense-Protokoll (http/https)',
                'TYPESENSE_API_KEY' => 'Typesense API-Key',
            ],
            'TMS' => [
                'TMS_ENABLED' => 'TMS aktiviert',
                'TMS_BASE_URL' => 'TMS-URL',
                'TMS_API_KEY' => 'TMS API-Key',
                'TMS_TIMEOUT' => 'TMS Timeout (Sekunden)',
                'TMS_TARGET_LANGUAGES' => 'TMS Zielsprachen',
            ],
            'SSO (Azure AD)' => [
                'SSO_ENABLED' => 'SSO aktiviert',
                'SSO_AUTO_PROVISION' => 'Automatische Benutzeranlage',
                'SSO_DEFAULT_ROLE' => 'Standard-Rolle fuer SSO-Benutzer',
                'AZURE_AD_CLIENT_ID' => 'Azure AD Client-ID',
                'AZURE_AD_CLIENT_SECRET' => 'Azure AD Client-Secret',
                'AZURE_AD_TENANT_ID' => 'Azure AD Tenant-ID',
                'AZURE_AD_REDIRECT_URI' => 'Azure AD Redirect-URI',
            ],
            'Lizenz' => [
                'ANYPIM_LICENSE_PUBLIC_KEY' => 'Lizenz-Public-Key',
                'ANYPIM_LICENSE_KEY' => 'Lizenz-Schluessel',
            ],
            'Sonstiges' => [
                'FILESYSTEM_DISK' => 'Dateisystem-Treiber',
                'MAIL_MAILER' => 'Mail-Treiber',
                'FRONTEND_URL' => 'Frontend-URL (CORS)',
                'DEPLOY_USER' => 'Deployment-Benutzer',
            ],
        ];
    }
}

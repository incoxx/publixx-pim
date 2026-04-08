<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Process;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Jobs\RetryFailedJob;

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
     * GET /api/v1/admin/queue-jobs
     *
     * Returns all pending, running, and failed queue jobs.
     */
    public function queueJobs(Request $request): JsonResponse
    {
        if (!$request->user()?->hasRole('Admin')) {
            abort(403, 'Unauthorized.');
        }

        $jobs = [];

        // ── Pending jobs from Redis queues ──
        $queues = ['default', 'import', 'export', 'indexing', 'cache', 'warmup', 'pdf'];
        foreach ($queues as $queue) {
            try {
                $size = Redis::llen("queues:{$queue}");
                if ($size > 0) {
                    // Peek at pending jobs (first 20)
                    $raw = Redis::lrange("queues:{$queue}", 0, min($size - 1, 19));
                    foreach ($raw as $payload) {
                        $data = json_decode($payload, true);
                        $jobs[] = [
                            'id' => $data['uuid'] ?? $data['id'] ?? null,
                            'type' => 'pending',
                            'queue' => $queue,
                            'job' => $this->extractJobName($data),
                            'payload' => $this->extractJobSummary($data),
                            'created_at' => isset($data['pushedAt']) ? date('Y-m-d H:i:s', (int) $data['pushedAt']) : null,
                        ];
                    }
                    // If more than 20, add summary
                    if ($size > 20) {
                        $jobs[] = [
                            'id' => null,
                            'type' => 'pending',
                            'queue' => $queue,
                            'job' => "... und " . ($size - 20) . " weitere",
                            'payload' => null,
                            'created_at' => null,
                        ];
                    }
                }
            } catch (\Throwable) {
                // Queue not available
            }
        }

        // ── Reserved (running) jobs ──
        foreach ($queues as $queue) {
            try {
                $reserved = Redis::zrange("queues:{$queue}:reserved", 0, -1);
                foreach ($reserved as $payload) {
                    $data = json_decode($payload, true);
                    $jobs[] = [
                        'id' => $data['uuid'] ?? $data['id'] ?? null,
                        'type' => 'running',
                        'queue' => $queue,
                        'job' => $this->extractJobName($data),
                        'payload' => $this->extractJobSummary($data),
                        'created_at' => isset($data['pushedAt']) ? date('Y-m-d H:i:s', (int) $data['pushedAt']) : null,
                    ];
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        // ── Failed jobs from DB ──
        try {
            $failed = DB::table('failed_jobs')
                ->orderByDesc('failed_at')
                ->limit(50)
                ->get();
            foreach ($failed as $fj) {
                $payload = json_decode($fj->payload, true);
                $jobs[] = [
                    'id' => $fj->uuid ?? (string) $fj->id,
                    'type' => 'failed',
                    'queue' => $fj->queue,
                    'job' => $this->extractJobName($payload),
                    'payload' => $this->extractJobSummary($payload),
                    'error' => \Illuminate\Support\Str::limit($fj->exception, 200),
                    'failed_at' => $fj->failed_at,
                ];
            }
        } catch (\Throwable) {
            // failed_jobs table might not exist
        }

        // ── Active import jobs (Cache-based) ──
        try {
            $importKeys = [];
            // Check for active BMEcat imports via known pattern
            $cursor = null;
            do {
                $result = Redis::scan($cursor, ['match' => 'laravel_cache_1:bmecat_import_progress_*', 'count' => 100]);
                if ($result === false) break;
                [$cursor, $keys] = $result;
                if (!empty($keys)) {
                    $importKeys = array_merge($importKeys, $keys);
                }
            } while ($cursor);

            foreach ($importKeys as $key) {
                $importId = str_replace('laravel_cache_1:bmecat_import_progress_', '', $key);
                $jobs[] = [
                    'id' => $importId,
                    'type' => 'import',
                    'queue' => 'import',
                    'job' => 'BMEcat Import',
                    'payload' => "Import-ID: {$importId}",
                    'created_at' => null,
                    'cancelable' => true,
                ];
            }
        } catch (\Throwable) {
            // ignore
        }

        // ── Queue sizes summary ──
        $queueSizes = [];
        foreach ($queues as $queue) {
            try {
                $queueSizes[$queue] = (int) Redis::llen("queues:{$queue}");
            } catch (\Throwable) {
                $queueSizes[$queue] = 0;
            }
        }

        return response()->json([
            'data' => [
                'jobs' => $jobs,
                'queue_sizes' => $queueSizes,
            ],
        ]);
    }

    /**
     * POST /api/v1/admin/queue-flush
     *
     * Flush a specific queue or all queues.
     */
    public function queueFlush(Request $request): JsonResponse
    {
        if (!$request->user()?->hasRole('Admin')) {
            abort(403, 'Unauthorized.');
        }

        $queue = $request->input('queue');
        $queues = $queue ? [$queue] : ['default', 'import', 'export', 'indexing', 'cache', 'warmup', 'pdf'];
        $flushed = 0;

        foreach ($queues as $q) {
            try {
                $size = (int) Redis::llen("queues:{$q}");
                if ($size > 0) {
                    Redis::del("queues:{$q}");
                    $flushed += $size;
                }
                // Also clear reserved/delayed
                Redis::del("queues:{$q}:reserved");
                Redis::del("queues:{$q}:delayed");
            } catch (\Throwable) {
                // ignore
            }
        }

        return response()->json([
            'message' => "{$flushed} Jobs gelöscht.",
            'flushed' => $flushed,
        ]);
    }

    /**
     * POST /api/v1/admin/queue-cancel-job
     *
     * Cancel a specific job by ID (import or queue job).
     */
    public function queueCancelJob(Request $request): JsonResponse
    {
        if (!$request->user()?->hasRole('Admin')) {
            abort(403, 'Unauthorized.');
        }

        $jobId = $request->input('job_id');
        $jobType = $request->input('job_type');

        if (!$jobId) {
            return response()->json(['message' => 'job_id erforderlich.'], 422);
        }

        // Cancel active BMEcat import
        if ($jobType === 'import') {
            Cache::put("bmecat_import_cancel_{$jobId}", true, 300);
            return response()->json(['message' => "Import {$jobId} wird abgebrochen..."]);
        }

        // Delete failed job
        if ($jobType === 'failed') {
            DB::table('failed_jobs')->where('uuid', $jobId)->orWhere('id', $jobId)->delete();
            return response()->json(['message' => "Fehlgeschlagener Job gelöscht."]);
        }

        return response()->json(['message' => "Job-Typ '{$jobType}' nicht unterstützt."], 422);
    }

    /**
     * DELETE /api/v1/admin/failed-jobs
     *
     * Delete all failed jobs.
     */
    public function flushFailedJobs(Request $request): JsonResponse
    {
        if (!$request->user()?->hasRole('Admin')) {
            abort(403, 'Unauthorized.');
        }

        $count = DB::table('failed_jobs')->count();
        DB::table('failed_jobs')->truncate();

        return response()->json([
            'message' => "{$count} fehlgeschlagene Jobs gelöscht.",
            'deleted' => $count,
        ]);
    }

    /**
     * POST /api/v1/admin/restart-apache
     *
     * Gracefully restart Apache (requires sudo permissions for www-data).
     */
    public function restartApache(Request $request): JsonResponse
    {
        if (!$request->user()?->hasRole('Admin')) {
            abort(403, 'Unauthorized.');
        }

        try {
            $result = Process::timeout(15)->run(['sudo', 'systemctl', 'reload', 'apache2']);

            if ($result->successful()) {
                return response()->json(['message' => 'Apache wurde neu geladen (graceful reload).']);
            }

            // Fallback: try apachectl
            $result2 = Process::timeout(15)->run(['sudo', 'apachectl', 'graceful']);
            if ($result2->successful()) {
                return response()->json(['message' => 'Apache wurde neu geladen (graceful).']);
            }

            return response()->json([
                'message' => 'Apache-Reload fehlgeschlagen.',
                'error' => $result->errorOutput() ?: $result2->errorOutput(),
            ], 500);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Apache-Reload fehlgeschlagen.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/v1/admin/restart-horizon
     *
     * Restart Horizon workers.
     */
    public function restartHorizon(Request $request): JsonResponse
    {
        if (!$request->user()?->hasRole('Admin')) {
            abort(403, 'Unauthorized.');
        }

        try {
            $result = Process::timeout(10)->run(['php', base_path('artisan'), 'horizon:terminate']);

            return response()->json([
                'message' => 'Horizon wird neu gestartet (Supervisor startet automatisch neu).',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Horizon-Restart fehlgeschlagen.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/v1/admin/system-processes
     *
     * Returns running Linux processes for www-data and MySQL.
     */
    public function systemProcesses(Request $request): JsonResponse
    {
        if (!$request->user()?->hasRole('Admin')) {
            abort(403, 'Unauthorized.');
        }

        $processes = [];

        // www-data Prozesse
        try {
            $result = Process::timeout(5)->run(['ps', '-u', 'www-data', '-o', 'pid,pcpu,pmem,vsz,rss,stat,start_time,time,args', '--no-headers']);
            if ($result->successful()) {
                foreach (explode("\n", trim($result->output())) as $line) {
                    $line = trim($line);
                    if ($line === '') continue;
                    $parts = preg_split('/\s+/', $line, 9);
                    if (count($parts) >= 9) {
                        $processes[] = [
                            'user' => 'www-data',
                            'pid' => (int) $parts[0],
                            'cpu' => (float) $parts[1],
                            'mem' => (float) $parts[2],
                            'vsz_mb' => round((int) $parts[3] / 1024, 1),
                            'rss_mb' => round((int) $parts[4] / 1024, 1),
                            'stat' => $parts[5],
                            'start' => $parts[6],
                            'time' => $parts[7],
                            'command' => $parts[8],
                        ];
                    }
                }
            }
        } catch (\Throwable) {
            // ignore
        }

        // MySQL Prozesse
        try {
            $result = Process::timeout(5)->run(['ps', '-u', 'mysql', '-o', 'pid,pcpu,pmem,vsz,rss,stat,start_time,time,args', '--no-headers']);
            if ($result->successful()) {
                foreach (explode("\n", trim($result->output())) as $line) {
                    $line = trim($line);
                    if ($line === '') continue;
                    $parts = preg_split('/\s+/', $line, 9);
                    if (count($parts) >= 9) {
                        $processes[] = [
                            'user' => 'mysql',
                            'pid' => (int) $parts[0],
                            'cpu' => (float) $parts[1],
                            'mem' => (float) $parts[2],
                            'vsz_mb' => round((int) $parts[3] / 1024, 1),
                            'rss_mb' => round((int) $parts[4] / 1024, 1),
                            'stat' => $parts[5],
                            'start' => $parts[6],
                            'time' => $parts[7],
                            'command' => $parts[8],
                        ];
                    }
                }
            }
        } catch (\Throwable) {
            // ignore
        }

        // Nach CPU absteigend sortieren
        usort($processes, fn($a, $b) => $b['cpu'] <=> $a['cpu']);

        // MySQL aktive Queries
        $mysqlQueries = [];
        try {
            $procs = DB::select("SHOW PROCESSLIST");
            foreach ($procs as $p) {
                if ($p->Command === 'Sleep' && empty($p->Info)) continue;
                $mysqlQueries[] = [
                    'id' => $p->Id,
                    'user' => $p->User,
                    'db' => $p->db,
                    'command' => $p->Command,
                    'time' => $p->Time,
                    'state' => $p->State ?? null,
                    'info' => $p->Info ? \Illuminate\Support\Str::limit($p->Info, 200) : null,
                ];
            }
        } catch (\Throwable) {
            // ignore
        }

        return response()->json([
            'data' => [
                'processes' => $processes,
                'mysql_queries' => $mysqlQueries,
            ],
        ]);
    }

    private function extractJobName(?array $data): string
    {
        if (!$data) return 'Unknown';

        $displayName = $data['displayName'] ?? null;
        if ($displayName) {
            // Shorten class name: App\Jobs\UpdateSearchIndex → UpdateSearchIndex
            return class_basename($displayName);
        }

        $job = $data['data']['commandName'] ?? $data['job'] ?? 'Unknown';
        return class_basename($job);
    }

    private function extractJobSummary(?array $data): ?string
    {
        if (!$data) return null;

        // Try to extract meaningful info from the command payload
        $command = $data['data']['command'] ?? null;
        if ($command && is_string($command)) {
            // Unserialize might be risky, just return a hint
            if (preg_match('/O:\d+:"([^"]+)"/', $command, $m)) {
                return class_basename($m[1]);
            }
        }

        return null;
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
     * GET /api/v1/admin/php-info
     * Liefert strukturierte phpinfo()-Daten als JSON-Sektionen.
     */
    public function phpInfo(Request $request): JsonResponse
    {
        if (!$request->user()?->hasRole('Admin')) {
            abort(403, 'Unauthorized.');
        }

        $sections = [];

        // ── Sektion 1: Allgemein ──────────────────────────────────────────────
        $sections[] = [
            'title' => 'Allgemein',
            'entries' => [
                ['key' => 'PHP Version',   'local' => PHP_VERSION,                                          'master' => ''],
                ['key' => 'System',        'local' => function_exists('php_uname') ? php_uname() : PHP_OS,  'master' => ''],
                ['key' => 'SAPI',          'local' => PHP_SAPI,                                             'master' => ''],
                ['key' => 'PHP Binary',    'local' => PHP_BINARY,                                           'master' => ''],
                ['key' => 'PHP OS',        'local' => PHP_OS,                                               'master' => ''],
                ['key' => 'PHP OS Family', 'local' => PHP_OS_FAMILY,                                        'master' => ''],
                ['key' => 'Zend Version',  'local' => function_exists('zend_version') ? zend_version() : '', 'master' => ''],
                ['key' => 'PHP_INT_SIZE',  'local' => (string) PHP_INT_SIZE,                                'master' => ''],
                ['key' => 'PHP_INT_MAX',   'local' => (string) PHP_INT_MAX,                                 'master' => ''],
            ],
        ];

        // ── Sektion 2: INI-Konfiguration ─────────────────────────────────────
        // ini_get_all(null) liefert ALLE INI-Werte ohne Extension-Filter.
        // ini_get_all('ExtName') wirft in manchen PHP-Builds eine Exception
        // wenn der Name nicht registriert ist — daher nie mit Extension-Namen aufrufen.
        if (function_exists('ini_get_all')) {
            try {
                $allIni = ini_get_all(null, true) ?: [];
            } catch (\Throwable) {
                $allIni = [];
            }

            if ($allIni) {
                $entries = [];
                foreach ($allIni as $key => $vals) {
                    if (!is_array($vals)) {
                        continue;
                    }
                    $entries[] = [
                        'key'    => $key,
                        'local'  => (string) ($vals['local_value']  ?? ''),
                        'master' => (string) ($vals['global_value'] ?? ''),
                    ];
                }
                if ($entries) {
                    $sections[] = ['title' => 'Konfiguration', 'entries' => $entries];
                }
            }
        } else {
            // Fallback: einzelne ini_get()-Aufrufe für die wichtigsten Direktiven
            $importantKeys = [
                'memory_limit', 'max_execution_time', 'upload_max_filesize',
                'post_max_size', 'max_input_vars', 'error_reporting',
                'display_errors', 'log_errors', 'error_log', 'default_charset',
                'date.timezone', 'session.save_handler', 'session.gc_maxlifetime',
                'opcache.enable', 'opcache.memory_consumption',
            ];
            $fallbackEntries = [];
            foreach ($importantKeys as $k) {
                $val = ini_get($k);
                if ($val !== false) {
                    $fallbackEntries[] = ['key' => $k, 'local' => (string) $val, 'master' => ''];
                }
            }
            if ($fallbackEntries) {
                $sections[] = ['title' => 'Konfiguration (Auszug)', 'entries' => $fallbackEntries];
            }
        }

        // ── Sektion 3: Geladene Extensions ───────────────────────────────────
        if (function_exists('get_loaded_extensions')) {
            $extensions = get_loaded_extensions();
            sort($extensions);
            $extList = [];
            foreach ($extensions as $ext) {
                $extList[] = [
                    'key'    => $ext,
                    'local'  => 'enabled',
                    'master' => phpversion($ext) ?: '',
                ];
            }
            if ($extList) {
                $sections[] = ['title' => 'Geladene Extensions', 'entries' => $extList];
            }
        }

        return response()->json(['sections' => $sections]);
    }

    /**
     * POST /api/v1/admin/cache-flush
     *
     * Leert einen oder mehrere Cache-Speicher.
     * Erlaubte Stores: app, config, views, routes
     */
    public function cacheFlush(Request $request): JsonResponse
    {
        if (!$request->user()?->hasRole('Admin')) {
            abort(403, 'Unauthorized.');
        }

        $requested = $request->input('stores', ['app', 'config', 'views', 'routes']);
        $allowed   = ['app', 'config', 'views', 'routes'];
        $stores    = array_values(array_intersect((array) $requested, $allowed));

        if (empty($stores)) {
            $stores = $allowed;
        }

        $results = [];

        foreach ($stores as $store) {
            try {
                match ($store) {
                    'app'    => Cache::flush(),
                    'config' => \Artisan::call('config:clear'),
                    'views'  => \Artisan::call('view:clear'),
                    'routes' => \Artisan::call('route:clear'),
                };
                $results[$store] = ['success' => true];
            } catch (\Throwable $e) {
                $results[$store] = ['success' => false, 'error' => $e->getMessage()];
            }
        }

        $allSuccess = collect($results)->every(fn ($r) => $r['success']);

        return response()->json([
            'message' => $allSuccess
                ? 'Cache erfolgreich geleert.'
                : 'Cache teilweise geleert (siehe Ergebnisse).',
            'results' => $results,
        ], $allSuccess ? 200 : 207);
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

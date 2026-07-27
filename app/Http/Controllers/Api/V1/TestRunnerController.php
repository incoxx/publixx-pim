<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;

class TestRunnerController extends Controller
{
    /**
     * GET /api/v1/admin/test-runner/info
     *
     * Returns test suite metadata: available suites, test count, last run info.
     */
    public function info(Request $request): JsonResponse
    {
        if (! $request->user()?->hasRole('Admin')) {
            abort(403);
        }

        $suites = $this->discoverTestSuites();

        $totalFiles = array_sum(array_column($suites, 'test_files'));
        $totalTests = array_sum(array_column($suites, 'test_cases'));

        return response()->json([
            'data' => [
                'suites' => $suites,
                'total_test_files' => $totalFiles,
                'total_tests' => $totalTests,
                'php_version' => PHP_VERSION,
                'phpunit_available' => file_exists(base_path('vendor/bin/phpunit')),
                'vitest_available' => file_exists(base_path('pim-frontend/node_modules/.bin/vitest')),
            ],
        ]);
    }

    /**
     * POST /api/v1/admin/test-runner/run
     *
     * Executes PHPUnit and/or Vitest and returns structured results.
     */
    public function run(Request $request): JsonResponse
    {
        if (! $request->user()?->hasRole('Admin')) {
            abort(403);
        }

        $suite = $request->input('suite', 'all'); // all, unit, feature, frontend
        $results = [];

        if (in_array($suite, ['all', 'unit', 'feature'])) {
            $results['backend'] = $this->runPhpUnit($suite);
        }

        if (in_array($suite, ['all', 'frontend'])) {
            $results['frontend'] = $this->runVitest();
        }

        $totalTests = 0;
        $totalPassed = 0;
        $totalFailed = 0;
        $totalErrors = 0;
        $totalUnavailable = 0;
        $ranCount = 0;
        $allPassed = true;

        foreach ($results as $result) {
            // Runner, der wegen fehlender Umgebung (PHPUnit/Composer/Test-DB)
            // gar nicht ausgeführt werden konnte, ist kein Testfehler.
            if (! empty($result['unavailable'])) {
                $totalUnavailable++;

                continue;
            }

            $ranCount++;
            $totalTests += $result['tests'];
            $totalPassed += $result['passed'];
            $totalFailed += $result['failures'];
            $totalErrors += $result['errors'];
            if (! $result['success']) {
                $allPassed = false;
            }
        }

        // Erfolg nur, wenn mindestens ein Runner lief, alles grün war
        // und keine Umgebung fehlte.
        $success = $ranCount > 0 && $allPassed && $totalUnavailable === 0;

        return response()->json([
            'data' => [
                'success' => $success,
                'summary' => [
                    'total_tests' => $totalTests,
                    'passed' => $totalPassed,
                    'failed' => $totalFailed,
                    'errors' => $totalErrors,
                    'unavailable' => $totalUnavailable,
                ],
                'results' => $results,
                'ran_at' => now()->toIso8601String(),
            ],
        ]);
    }

    private function runPhpUnit(string $suite): array
    {
        $phpunitBin = base_path('vendor/bin/phpunit');
        $installedDevDeps = false;

        // Auto-install dev dependencies if PHPUnit is missing (e.g. production server)
        if (! file_exists($phpunitBin)) {
            // Find composer binary - check common locations
            $composerBin = null;
            foreach (['/usr/local/bin/composer', '/usr/bin/composer', '/usr/local/sbin/composer'] as $path) {
                if (file_exists($path)) {
                    $composerBin = $path;
                    break;
                }
            }

            if (! $composerBin) {
                // Try to find via which
                $which = Process::run('which composer');
                $composerBin = trim($which->output()) ?: null;
            }

            if (! $composerBin) {
                return $this->errorResult('backend', 'PHPUnit nicht installiert und Composer wurde nicht gefunden. Bitte composer install manuell ausführen.');
            }

            $composerHome = base_path('storage/.composer');
            if (! is_dir($composerHome)) {
                mkdir($composerHome, 0755, true);
            }

            $install = Process::timeout(120)
                ->path(base_path())
                ->env(['COMPOSER_HOME' => $composerHome, 'HOME' => base_path('storage')])
                ->run(escapeshellarg($composerBin) . ' install --no-interaction 2>&1');

            if ($install->exitCode() !== 0 || ! file_exists($phpunitBin)) {
                return $this->errorResult('backend', 'composer install fehlgeschlagen (bin: ' . $composerBin . '): ' . mb_substr($install->output() . $install->errorOutput(), -1000));
            }

            $installedDevDeps = true;
        }

        // Ensure test database exists and has tables
        $this->ensureTestDatabase();

        // SAFETY: Clear config cache so --env=testing is respected
        // (cached config ignores --env flag and would hit production DB!)
        Process::timeout(30)->path(base_path())->run('php artisan config:clear 2>&1');
        Process::timeout(30)->path(base_path())->run('php artisan route:clear 2>&1');

        // Migrate test database (fresh) to ensure clean state
        // Double-safety: verify the target DB name contains 'testing'
        $envCheck = Process::timeout(10)->path(base_path())
            ->run('php artisan tinker --env=testing --execute="echo config(\'database.connections.mysql.database\')" 2>&1');
        $targetDb = trim($envCheck->output());

        if (! str_contains($targetDb, 'testing')) {
            // Restore caches before aborting
            Process::timeout(30)->path(base_path())->run('php artisan config:cache 2>&1');
            Process::timeout(30)->path(base_path())->run('php artisan route:cache 2>&1');
            return $this->errorResult('backend', 'SICHERHEIT: migrate:fresh abgebrochen — Ziel-DB "' . $targetDb . '" enthält nicht "testing". Bitte .env.testing prüfen.');
        }

        Process::timeout(120)->path(base_path())->run('php artisan migrate:fresh --env=testing --force 2>&1');

        $cmd = 'php artisan test --env=testing';

        if ($suite === 'unit') {
            $cmd .= ' --testsuite=Unit';
        } elseif ($suite === 'feature') {
            $cmd .= ' --testsuite=Feature';
        }

        $process = Process::timeout(300)->path(base_path())->run($cmd . ' 2>&1');
        $result = $this->parsePhpUnitOutput($process->output() . $process->errorOutput(), $process->exitCode());

        // Restore production caches
        Process::timeout(30)->path(base_path())->run('php artisan config:cache 2>&1');
        Process::timeout(30)->path(base_path())->run('php artisan route:cache 2>&1');

        // Cleanup: remove dev dependencies to keep production clean
        if ($installedDevDeps) {
            Process::timeout(120)
                ->path(base_path())
                ->env(['COMPOSER_HOME' => base_path('storage/.composer'), 'HOME' => base_path('storage')])
                ->run(escapeshellarg($composerBin) . ' install --no-dev --no-interaction 2>&1');
        }

        return $result;
    }

    private function runVitest(): array
    {
        $vitestBin = base_path('pim-frontend/node_modules/.bin/vitest');
        if (! file_exists($vitestBin)) {
            return $this->errorResult('frontend', 'Vitest nicht installiert (npm install ausstehend)');
        }

        $process = Process::timeout(120)
            ->path(base_path('pim-frontend'))
            ->run(['npx', 'vitest', 'run', '--reporter=json']);

        $json = $process->output();
        $data = json_decode($json, true);

        if ($data && isset($data['numTotalTests'])) {
            return [
                'runner' => 'Vitest',
                'success' => ($data['numFailedTests'] ?? 0) === 0,
                'tests' => $data['numTotalTests'] ?? 0,
                'passed' => $data['numPassedTests'] ?? 0,
                'failures' => $data['numFailedTests'] ?? 0,
                'errors' => 0,
                'duration_ms' => round(($data['testResults'][0]['endTime'] ?? 0) - ($data['startTime'] ?? 0)),
                'unavailable' => false,
                'test_files' => collect($data['testResults'] ?? [])->map(fn ($f) => [
                    'file' => str_replace(base_path('pim-frontend/'), '', $f['name'] ?? ''),
                    'tests' => ($f['numPassingTests'] ?? 0) + ($f['numFailingTests'] ?? 0),
                    'passed' => $f['numPassingTests'] ?? 0,
                    'failed' => $f['numFailingTests'] ?? 0,
                    'duration_ms' => round(($f['endTime'] ?? 0) - ($f['startTime'] ?? 0)),
                ])->values()->toArray(),
                'output' => null,
            ];
        }

        // Fallback: parse plain text output
        return $this->parseVitestFallback($process->output() . $process->errorOutput(), $process->exitCode());
    }

    private function parsePhpUnitOutput(string $output, int $exitCode): array
    {
        $tests = 0;
        $failures = 0;
        $errors = 0;

        // Parse "Tests: 110, Assertions: 253, Failures: 1." or "OK (110 tests, 253 assertions)"
        if (preg_match('/Tests:\s*(\d+).*?Failures:\s*(\d+)/s', $output, $m)) {
            $tests = (int) $m[1];
            $failures = (int) $m[2];
        } elseif (preg_match('/OK\s*\((\d+)\s+tests?/i', $output, $m)) {
            $tests = (int) $m[1];
        }

        if (preg_match('/Errors:\s*(\d+)/', $output, $m)) {
            $errors = (int) $m[1];
        }

        $passed = max(0, $tests - $failures - $errors);

        // Extract time
        $durationMs = 0;
        if (preg_match('/Time:\s*(\d+):(\d+\.?\d*)/i', $output, $m)) {
            $durationMs = (int) (((int) $m[1] * 60 + (float) $m[2]) * 1000);
        } elseif (preg_match('/Time:\s*([\d.]+)\s*s/i', $output, $m)) {
            $durationMs = (int) ((float) $m[1] * 1000);
        }

        return [
            'runner' => 'PHPUnit',
            'success' => $exitCode === 0,
            'tests' => $tests,
            'passed' => $passed,
            'failures' => $failures,
            'errors' => $errors,
            'duration_ms' => $durationMs,
            'unavailable' => false,
            'test_files' => null,
            'output' => mb_substr($output, -3000), // Last 3000 chars
        ];
    }

    private function parseVitestFallback(string $output, int $exitCode): array
    {
        $tests = 0;
        $passed = 0;
        $failed = 0;

        if (preg_match('/(\d+)\s+passed/i', $output, $m)) {
            $passed = (int) $m[1];
        }
        if (preg_match('/(\d+)\s+failed/i', $output, $m)) {
            $failed = (int) $m[1];
        }
        $tests = $passed + $failed;

        return [
            'runner' => 'Vitest',
            'success' => $exitCode === 0,
            'tests' => $tests,
            'passed' => $passed,
            'failures' => $failed,
            'errors' => 0,
            'duration_ms' => 0,
            'unavailable' => false,
            'test_files' => null,
            'output' => mb_substr($output, -3000),
        ];
    }

    /**
     * Ergebnis für einen Runner, der wegen fehlender Umgebung nicht
     * ausgeführt werden konnte (kein Testfehler, sondern nicht verfügbar).
     */
    private function errorResult(string $runner, string $message): array
    {
        return [
            'runner' => $runner,
            'success' => false,
            'tests' => 0,
            'passed' => 0,
            'failures' => 0,
            'errors' => 0,
            'duration_ms' => 0,
            'unavailable' => true,
            'test_files' => null,
            'output' => $message,
        ];
    }

    private function ensureTestDatabase(): void
    {
        try {
            $db = config('database.connections.mysql');
            $testDbName = $db['database'] . '_testing';
            $pdo = new \PDO(
                "mysql:host={$db['host']};port={$db['port']}",
                $db['username'],
                $db['password']
            );
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$testDbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (\Exception $e) {
            // User may not have CREATE DATABASE privilege — that's OK,
            // the test DB should be created by setup.sh
        }
    }

    private function discoverTestSuites(): array
    {
        $suites = [];

        // PHPUnit-Testdateien rekursiv erfassen (*Test.php).
        $unitFiles = $this->findFiles(base_path('tests/Unit'), '/Test\.php$/');
        $featureFiles = $this->findFiles(base_path('tests/Feature'), '/Test\.php$/');

        $suites[] = [
            'id' => 'unit',
            'name' => 'Backend Unit Tests',
            'runner' => 'PHPUnit',
            'test_files' => count($unitFiles),
            'test_cases' => $this->countPhpTestCases($unitFiles),
        ];
        $suites[] = [
            'id' => 'feature',
            'name' => 'Backend Feature Tests',
            'runner' => 'PHPUnit',
            'test_files' => count($featureFiles),
            'test_cases' => $this->countPhpTestCases($featureFiles),
        ];

        // Vitest-Testdateien rekursiv erfassen (*.test.js).
        $frontendFiles = $this->findFiles(base_path('pim-frontend/src'), '/\.test\.js$/');
        $suites[] = [
            'id' => 'frontend',
            'name' => 'Frontend Tests',
            'runner' => 'Vitest',
            'test_files' => count($frontendFiles),
            'test_cases' => $this->countJsTestCases($frontendFiles),
        ];

        return $suites;
    }

    /**
     * Findet alle Dateien unterhalb von $dir, deren Name auf $pattern passt
     * (rekursiv — im Gegensatz zu glob('**') zählt das alle Ebenen).
     *
     * @return list<string>
     */
    private function findFiles(string $dir, string $pattern): array
    {
        if (! is_dir($dir)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && preg_match($pattern, $file->getFilename())) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * Zählt PHPUnit-Testmethoden (Namenskonvention test* + #[Test]-Attribut).
     *
     * @param  list<string>  $files
     */
    private function countPhpTestCases(array $files): int
    {
        $count = 0;

        foreach ($files as $file) {
            $src = @file_get_contents($file) ?: '';
            $count += preg_match_all('/function\s+test\w/i', $src);
            $count += preg_match_all('/#\[\s*Test\s*\]/', $src);
        }

        return $count;
    }

    /**
     * Zählt Vitest-Testfälle (it(...) und test(...)).
     *
     * @param  list<string>  $files
     */
    private function countJsTestCases(array $files): int
    {
        $count = 0;

        foreach ($files as $file) {
            $src = @file_get_contents($file) ?: '';
            $count += preg_match_all('/\b(?:it|test)\s*(?:\.\w+)?\s*\(/', $src);
        }

        return $count;
    }
}

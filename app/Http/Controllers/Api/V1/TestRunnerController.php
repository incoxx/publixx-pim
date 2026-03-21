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

        return response()->json([
            'data' => [
                'suites' => $suites,
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
        $allPassed = true;

        foreach ($results as $result) {
            $totalTests += $result['tests'];
            $totalPassed += $result['passed'];
            $totalFailed += $result['failures'];
            $totalErrors += $result['errors'];
            if (! $result['success']) {
                $allPassed = false;
            }
        }

        return response()->json([
            'data' => [
                'success' => $allPassed,
                'summary' => [
                    'total_tests' => $totalTests,
                    'passed' => $totalPassed,
                    'failed' => $totalFailed,
                    'errors' => $totalErrors,
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

            $install = Process::timeout(120)
                ->path(base_path())
                ->run(escapeshellarg($composerBin) . ' install --no-interaction 2>&1');

            if ($install->exitCode() !== 0 || ! file_exists($phpunitBin)) {
                return $this->errorResult('backend', 'composer install fehlgeschlagen (bin: ' . $composerBin . '): ' . mb_substr($install->output() . $install->errorOutput(), -1000));
            }

            if (! file_exists($phpunitBin)) {
                return $this->errorResult('backend', 'PHPUnit konnte nicht installiert werden');
            }

            $installedDevDeps = true;
        }

        $args = [$phpunitBin, '--no-interaction'];

        if ($suite === 'unit') {
            $args[] = '--testsuite=Unit';
        } elseif ($suite === 'feature') {
            $args[] = '--testsuite=Feature';
        }

        $process = Process::timeout(300)->run($args);
        $result = $this->parsePhpUnitOutput($process->output() . $process->errorOutput(), $process->exitCode());

        // Cleanup: remove dev dependencies to keep production clean
        if ($installedDevDeps) {
            $composerPath = collect(['/usr/local/bin/composer', '/usr/bin/composer'])->first(fn ($p) => file_exists($p)) ?? 'composer';
            Process::timeout(120)
                ->path(base_path())
                ->run(escapeshellarg($composerPath) . ' install --no-dev --no-interaction 2>&1');
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
            'test_files' => null,
            'output' => mb_substr($output, -3000),
        ];
    }

    private function errorResult(string $runner, string $message): array
    {
        return [
            'runner' => $runner,
            'success' => false,
            'tests' => 0,
            'passed' => 0,
            'failures' => 0,
            'errors' => 1,
            'duration_ms' => 0,
            'test_files' => null,
            'output' => $message,
        ];
    }

    private function discoverTestSuites(): array
    {
        $suites = [];

        // Count PHP test files
        $unitCount = count(glob(base_path('tests/Unit/**/*.php')) ?: []);
        $featureCount = count(glob(base_path('tests/Feature/**/*.php')) ?: []);

        $suites[] = [
            'id' => 'unit',
            'name' => 'Backend Unit Tests',
            'runner' => 'PHPUnit',
            'test_files' => $unitCount,
        ];
        $suites[] = [
            'id' => 'feature',
            'name' => 'Backend Feature Tests',
            'runner' => 'PHPUnit',
            'test_files' => $featureCount,
        ];

        // Count JS test files
        $frontendTests = glob(base_path('pim-frontend/src/**/__tests__/*.test.js')) ?: [];
        $suites[] = [
            'id' => 'frontend',
            'name' => 'Frontend Tests',
            'runner' => 'Vitest',
            'test_files' => count($frontendTests),
        ];

        return $suites;
    }
}

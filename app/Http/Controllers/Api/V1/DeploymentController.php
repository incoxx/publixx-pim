<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class DeploymentController extends Controller
{
    /**
     * POST /api/v1/admin/deploy
     *
     * Zieht den main Branch von GitHub und führt alle
     * notwendigen Deployment-Schritte durch.
     * Nur für Admins.
     */
    public function deploy(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasRole('Admin')) {
            return $this->errorResponse(
                type: 'auth/forbidden',
                title: 'Forbidden',
                status: 403,
                detail: 'Only admins can trigger deployments.',
            );
        }

        $basePath = base_path();
        $log = [];
        $startTime = microtime(true);

        // Schritt 1: Backup — aktuellen Commit-Hash merken
        $log[] = $this->runStep('backup', 'git rev-parse HEAD', $basePath);

        if ($log[0]['success']) {
            $backupHash = trim($log[0]['output']);
            $log[0]['output'] = "Backup-Punkt: {$backupHash}";
        }

        // Schritt 2: Git fetch + pull main
        $log[] = $this->runStep('git_fetch', 'git fetch origin main', $basePath);
        $log[] = $this->runStep('git_pull', 'git pull origin main', $basePath, timeout: 120);

        // Schritt 3: Composer install (ohne Dev)
        $log[] = $this->runStep(
            'composer_install',
            'composer install --no-dev --optimize-autoloader --no-interaction',
            $basePath,
            timeout: 300,
        );

        // Schritt 4: Migrationen
        $log[] = $this->runStep(
            'migrate',
            'php artisan migrate --force',
            $basePath,
            timeout: 120,
        );

        // Schritt 5: Caches neu bauen
        $log[] = $this->runStep('config_cache', 'php artisan config:cache', $basePath);
        $log[] = $this->runStep('route_cache', 'php artisan route:cache', $basePath);
        $log[] = $this->runStep('view_cache', 'php artisan view:cache', $basePath);

        // Schritt 6: Horizon restarten
        $log[] = $this->runStep('horizon_terminate', 'php artisan horizon:terminate', $basePath);

        $duration = round(microtime(true) - $startTime, 2);
        $allSuccessful = collect($log)->every(fn (array $step) => $step['success']);

        // Neuen Commit-Hash lesen
        $newHashResult = $this->runStep('new_hash', 'git rev-parse --short HEAD', $basePath);
        $newHash = trim($newHashResult['output']);

        $result = [
            'success' => $allSuccessful,
            'duration_seconds' => $duration,
            'deployed_by' => $user->name,
            'commit' => $newHash,
            'backup_hash' => $backupHash ?? null,
            'steps' => $log,
        ];

        Log::channel('single')->info('Deployment triggered', [
            'user' => $user->email,
            'success' => $allSuccessful,
            'commit' => $newHash,
            'duration' => $duration,
        ]);

        return $this->successResponse($result, $allSuccessful ? 200 : 207);
    }

    /**
     * GET /api/v1/admin/deploy/status
     *
     * Gibt den aktuellen Git-Status und den letzten Commit zurück.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasRole('Admin')) {
            return $this->errorResponse(
                type: 'auth/forbidden',
                title: 'Forbidden',
                status: 403,
                detail: 'Only admins can view deployment status.',
            );
        }

        $basePath = base_path();

        $commitHash = trim($this->runCommand('git rev-parse --short HEAD', $basePath));
        $commitMessage = trim($this->runCommand('git log -1 --pretty=%s', $basePath));
        $commitDate = trim($this->runCommand('git log -1 --pretty=%ci', $basePath));
        $branch = trim($this->runCommand('git rev-parse --abbrev-ref HEAD', $basePath));

        return $this->successResponse([
            'branch' => $branch,
            'commit' => $commitHash,
            'message' => $commitMessage,
            'date' => $commitDate,
            'laravel_version' => app()->version(),
            'php_version' => PHP_VERSION,
        ]);
    }

    /**
     * POST /api/v1/admin/deploy/rollback
     *
     * Rollt auf einen gegebenen Commit-Hash zurück.
     */
    public function rollback(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasRole('Admin')) {
            return $this->errorResponse(
                type: 'auth/forbidden',
                title: 'Forbidden',
                status: 403,
                detail: 'Only admins can trigger rollbacks.',
            );
        }

        $hash = $request->input('commit_hash');
        if (! $hash || ! preg_match('/^[a-f0-9]{6,40}$/', $hash)) {
            return $this->errorResponse(
                type: 'validation/invalid',
                title: 'Invalid commit hash',
                status: 422,
                detail: 'A valid commit hash is required.',
            );
        }

        $basePath = base_path();
        $log = [];

        $log[] = $this->runStep('checkout', "git checkout {$hash}", $basePath);
        $log[] = $this->runStep(
            'composer_install',
            'composer install --no-dev --optimize-autoloader --no-interaction',
            $basePath,
            timeout: 300,
        );
        $log[] = $this->runStep('config_cache', 'php artisan config:cache', $basePath);
        $log[] = $this->runStep('route_cache', 'php artisan route:cache', $basePath);
        $log[] = $this->runStep('horizon_terminate', 'php artisan horizon:terminate', $basePath);

        $allSuccessful = collect($log)->every(fn (array $step) => $step['success']);

        Log::channel('single')->warning('Rollback triggered', [
            'user' => $user->email,
            'target_hash' => $hash,
            'success' => $allSuccessful,
        ]);

        return $this->successResponse([
            'success' => $allSuccessful,
            'rolled_back_to' => $hash,
            'steps' => $log,
        ], $allSuccessful ? 200 : 207);
    }

    /**
     * Führt einen einzelnen Deployment-Schritt aus.
     */
    private function runStep(string $name, string $command, string $cwd, int $timeout = 60): array
    {
        $process = Process::fromShellCommandline($command, $cwd, [
            'COMPOSER_ALLOW_SUPERUSER' => '1',
            'HOME' => env('HOME', '/root'),
            'PATH' => env('PATH', '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin'),
        ]);
        $process->setTimeout($timeout);

        try {
            $process->run();

            return [
                'step' => $name,
                'success' => $process->isSuccessful(),
                'output' => trim($process->getOutput() ?: $process->getErrorOutput()),
                'exit_code' => $process->getExitCode(),
            ];
        } catch (\Throwable $e) {
            return [
                'step' => $name,
                'success' => false,
                'output' => $e->getMessage(),
                'exit_code' => -1,
            ];
        }
    }

    /**
     * Führt einen Befehl aus und gibt den Output zurück.
     */
    private function runCommand(string $command, string $cwd): string
    {
        $process = Process::fromShellCommandline($command, $cwd, [
            'COMPOSER_ALLOW_SUPERUSER' => '1',
            'HOME' => env('HOME', '/root'),
            'PATH' => env('PATH', '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin'),
        ]);
        $process->setTimeout(15);
        $process->run();

        return $process->getOutput();
    }
}

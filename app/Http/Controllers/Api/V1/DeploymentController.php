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
     * notwendigen Deployment-Schritte durch — analog zu update.sh.
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
        $deployUser = $this->resolveDeployUser($basePath);
        $webUser = $this->resolveWebUser();

        // Pre-check: Kann sudo verwendet werden?
        if ($deployUser !== null) {
            $sudoCheck = $this->runStep('sudo_check', "sudo -n -u {$deployUser} whoami", $basePath);
            if (! $sudoCheck['success']) {
                Log::warning('Deployment: sudo check failed', [
                    'user'        => $user->email,
                    'deploy_user' => $deployUser,
                    'output'      => $sudoCheck['output'],
                ]);
                $log[] = array_merge($sudoCheck, [
                    'output' => "sudo -u {$deployUser} nicht möglich. Fahre ohne sudo fort.",
                ]);
                $deployUser = null;
            } else {
                $log[] = $sudoCheck;
            }
        }

        // Schritt 1: Wartungsmodus aktivieren
        $log[] = $this->runStep('maintenance_on', 'php artisan down --retry=60 2>/dev/null || true', $basePath, deployUser: $deployUser);

        // Schritt 2: Backup — aktuellen Commit-Hash merken
        $backupStep = $this->runStep('backup', 'git rev-parse HEAD', $basePath, deployUser: $deployUser);
        $log[] = $backupStep;
        $backupHash = null;

        if ($backupStep['success']) {
            $backupHash = trim($backupStep['output']);
            $log[count($log) - 1]['output'] = "Backup-Punkt: {$backupHash}";
        }

        // Schritt 3: Git fetch + pull main
        $log[] = $this->runStep('git_fetch', 'git fetch origin main', $basePath, deployUser: $deployUser);
        $log[] = $this->runStep('git_pull', 'git pull origin main', $basePath, timeout: 120, deployUser: $deployUser);

        // Schritt 4: Composer install (ohne Dev)
        $log[] = $this->runStep(
            'composer_install',
            'composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist',
            $basePath,
            timeout: 300,
            deployUser: $deployUser,
        );

        // Schritt 5: Migrationen
        $log[] = $this->runStep(
            'migrate',
            'php artisan migrate --force',
            $basePath,
            timeout: 120,
            deployUser: $deployUser,
        );

        // Schritt 6: Frontend bauen (npm ci + build, wie update.sh)
        $frontendPath = $basePath . '/pim-frontend';
        $log[] = $this->runStep('npm_ci', 'npm ci', $frontendPath, timeout: 180, deployUser: $deployUser);

        // VITE_BASE_PATH aus APP_URL ermitteln (Subdirectory-Support)
        $viteEnv = $this->resolveViteEnv();
        $buildCmd = $viteEnv . 'npm run build';
        $log[] = $this->runStep('npm_build', $buildCmd, $frontendPath, timeout: 180, deployUser: $deployUser);

        // Schritt 6b: Build nach public/ kopieren
        $copyCmd = 'rm -rf public/pim-assets'
            . ' && cp -r pim-frontend/dist/pim-assets public/pim-assets'
            . ' && cp pim-frontend/dist/index.html public/spa.html';
        $log[] = $this->runStep('copy_frontend', $copyCmd, $basePath, deployUser: $deployUser);

        // Schritt 7: Storage-Link sicherstellen
        $log[] = $this->runStep(
            'storage_link',
            'php artisan storage:link 2>/dev/null || true; mkdir -p storage/app/public/media',
            $basePath,
            deployUser: $deployUser,
        );

        // Schritt 8: Berechtigungen korrigieren (wie update.sh)
        $log[] = $this->runStep(
            'fix_permissions',
            $this->buildPermissionFixCommand($basePath, $webUser),
            $basePath,
            timeout: 120,
        );

        // Schritt 9: Caches neu bauen
        $log[] = $this->runStep('config_cache', 'php artisan config:cache', $basePath, deployUser: $deployUser);
        $log[] = $this->runStep('route_cache', 'php artisan route:cache', $basePath, deployUser: $deployUser);
        $log[] = $this->runStep('view_cache', 'php artisan view:cache', $basePath, deployUser: $deployUser);

        // Schritt 10: Horizon restarten
        $log[] = $this->runStep('horizon_terminate', 'php artisan horizon:terminate', $basePath, deployUser: $deployUser);

        // Schritt 11: Apache neu laden
        $log[] = $this->runStep('reload_apache', 'systemctl reload apache2 2>/dev/null || true', $basePath);

        // Schritt 12: Wartungsmodus deaktivieren
        $log[] = $this->runStep('maintenance_off', 'php artisan up', $basePath, deployUser: $deployUser);

        $duration = round(microtime(true) - $startTime, 2);
        $allSuccessful = collect($log)->every(fn (array $step) => $step['success']);

        // Neuen Commit-Hash lesen
        $newHashResult = $this->runStep('new_hash', 'git rev-parse --short HEAD', $basePath, deployUser: $deployUser);
        $newHash = trim($newHashResult['output']);

        $result = [
            'success'          => $allSuccessful,
            'duration_seconds' => $duration,
            'deployed_by'      => $user->name,
            'commit'           => $newHash,
            'backup_hash'      => $backupHash,
            'deploy_user'      => $deployUser,
            'web_user'         => $webUser,
            'steps'            => $log,
        ];

        Log::channel('single')->info('Deployment triggered', [
            'user'        => $user->email,
            'success'     => $allSuccessful,
            'commit'      => $newHash,
            'duration'    => $duration,
            'deploy_user' => $deployUser,
            'web_user'    => $webUser,
        ]);

        return $this->successResponse($result, $allSuccessful ? 200 : 207);
    }

    /**
     * GET /api/v1/admin/deploy/status
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
        $deployUser = $this->resolveDeployUser($basePath);
        $webUser = $this->resolveWebUser();

        $commitHash = trim($this->runCommand('git rev-parse --short HEAD', $basePath));
        $commitMessage = trim($this->runCommand('git log -1 --pretty=%s', $basePath));
        $commitDate = trim($this->runCommand('git log -1 --pretty=%ci', $basePath));
        $branch = trim($this->runCommand('git rev-parse --abbrev-ref HEAD', $basePath));

        $sudoAvailable = true;
        if ($deployUser !== null) {
            $check = $this->runCommand("sudo -n -u {$deployUser} whoami 2>&1", $basePath);
            $sudoAvailable = trim($check) === $deployUser;
        }

        return $this->successResponse([
            'branch'          => $branch,
            'commit'          => $commitHash,
            'message'         => $commitMessage,
            'date'            => $commitDate,
            'laravel_version' => app()->version(),
            'php_version'     => PHP_VERSION,
            'deploy_user'     => $deployUser,
            'web_user'        => $webUser,
            'sudo_available'  => $sudoAvailable,
        ]);
    }

    /**
     * POST /api/v1/admin/deploy/rollback
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
        $deployUser = $this->resolveDeployUser($basePath);
        $webUser = $this->resolveWebUser();

        $log[] = $this->runStep('maintenance_on', 'php artisan down --retry=60 2>/dev/null || true', $basePath, deployUser: $deployUser);
        $log[] = $this->runStep('checkout', "git checkout {$hash}", $basePath, deployUser: $deployUser);
        $log[] = $this->runStep(
            'composer_install',
            'composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist',
            $basePath,
            timeout: 300,
            deployUser: $deployUser,
        );

        // Berechtigungen korrigieren
        $log[] = $this->runStep(
            'fix_permissions',
            $this->buildPermissionFixCommand($basePath, $webUser),
            $basePath,
            timeout: 120,
        );

        $log[] = $this->runStep('config_cache', 'php artisan config:cache', $basePath, deployUser: $deployUser);
        $log[] = $this->runStep('route_cache', 'php artisan route:cache', $basePath, deployUser: $deployUser);
        $log[] = $this->runStep('horizon_terminate', 'php artisan horizon:terminate', $basePath, deployUser: $deployUser);
        $log[] = $this->runStep('maintenance_off', 'php artisan up', $basePath, deployUser: $deployUser);

        $allSuccessful = collect($log)->every(fn (array $step) => $step['success']);

        Log::channel('single')->warning('Rollback triggered', [
            'user'        => $user->email,
            'target_hash' => $hash,
            'success'     => $allSuccessful,
        ]);

        return $this->successResponse([
            'success'        => $allSuccessful,
            'rolled_back_to' => $hash,
            'steps'          => $log,
        ], $allSuccessful ? 200 : 207);
    }

    /**
     * Ermittelt VITE_BASE_PATH und VITE_API_BASE_URL aus APP_URL.
     * Gibt einen Shell-Export-Prefix zurück für den npm build Befehl.
     */
    private function resolveViteEnv(): string
    {
        $appUrl = config('app.url', '');
        $parsed = parse_url($appUrl);
        $webPath = rtrim($parsed['path'] ?? '', '/');

        if (empty($webPath)) {
            return '';
        }

        return "VITE_BASE_PATH=\"{$webPath}/\" VITE_API_BASE_URL=\"{$webPath}/api/v1\" ";
    }

    /**
     * Baut den Berechtigungs-Befehl (wie update.sh Schritt 9).
     */
    private function buildPermissionFixCommand(string $basePath, ?string $webUser): string
    {
        $user = $webUser ?: 'www-data';

        return "chown -R {$user}:{$user} {$basePath}/storage {$basePath}/bootstrap/cache"
            . " && chmod -R 775 {$basePath}/storage"
            . " && chmod -R 775 {$basePath}/bootstrap/cache";
    }

    /**
     * Ermittelt den System-User für Deployment-Befehle.
     */
    private function resolveDeployUser(string $basePath): ?string
    {
        $envUser = config('app.deploy_user', env('DEPLOY_USER'));
        if (! empty($envUser)) {
            return $envUser;
        }

        if (! function_exists('posix_getpwuid') || ! function_exists('posix_geteuid')) {
            return null;
        }

        $ownerUid = (int) fileowner($basePath);
        $currentUid = posix_geteuid();

        if ($ownerUid === $currentUid) {
            return null;
        }

        $ownerInfo = posix_getpwuid($ownerUid);

        return $ownerInfo ? $ownerInfo['name'] : null;
    }

    /**
     * Ermittelt den Webserver-User (normalerweise www-data).
     */
    private function resolveWebUser(): ?string
    {
        if (! function_exists('posix_getpwuid') || ! function_exists('posix_geteuid')) {
            return null;
        }

        $info = posix_getpwuid(posix_geteuid());

        return $info ? $info['name'] : null;
    }

    /**
     * Verpackt einen Befehl mit sudo -u, falls ein deploy user gesetzt ist.
     */
    private function wrapWithSudo(string $command, ?string $deployUser): string
    {
        if ($deployUser === null) {
            return $command;
        }

        $escapedCommand = str_replace("'", "'\\''", $command);

        return "sudo -n -u {$deployUser} bash -c '{$escapedCommand}'";
    }

    /**
     * Führt einen einzelnen Deployment-Schritt aus.
     */
    private function runStep(string $name, string $command, string $cwd, int $timeout = 60, ?string $deployUser = null): array
    {
        $actualCommand = $this->wrapWithSudo($command, $deployUser);

        $env = [
            'COMPOSER_ALLOW_SUPERUSER' => '1',
            'PATH' => getenv('PATH') ?: '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
        ];

        if ($deployUser && function_exists('posix_getpwnam')) {
            $userInfo = posix_getpwnam($deployUser);
            $env['HOME'] = $userInfo['dir'] ?? "/home/{$deployUser}";
        } else {
            $env['HOME'] = getenv('HOME') ?: '/root';
        }

        $process = Process::fromShellCommandline($actualCommand, $cwd, $env);
        $process->setTimeout($timeout);

        try {
            $process->run();

            return [
                'step'      => $name,
                'success'   => $process->isSuccessful(),
                'output'    => trim($process->getOutput() ?: $process->getErrorOutput()),
                'exit_code' => $process->getExitCode(),
            ];
        } catch (\Throwable $e) {
            return [
                'step'      => $name,
                'success'   => false,
                'output'    => $e->getMessage(),
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
            'HOME' => getenv('HOME') ?: '/root',
            'PATH' => getenv('PATH') ?: '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
        ]);
        $process->setTimeout(15);
        $process->run();

        return $process->getOutput();
    }
}

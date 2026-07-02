<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Services\ArtisanCockpit\ArtisanCommandRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Artisan-Cockpit (System-Menü): kuratierte Artisan-Befehle per GUI ausführen.
 *
 * Nur Admins dürfen zugreifen. Es kann ausschließlich ausgeführt werden, was in
 * ArtisanCommandRegistry als `runnable = true` markiert ist — Befehlsname und
 * Optionen werden strikt gegen die Registry validiert, es wird niemals ein
 * roher, vom Client übergebener Befehl ausgeführt.
 */
class ArtisanCockpitController extends Controller
{
    /**
     * GET /admin/artisan-cockpit/commands
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        return response()->json(['data' => ArtisanCommandRegistry::categories()]);
    }

    /**
     * POST /admin/artisan-cockpit/run
     */
    public function run(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $request->validate([
            'command' => 'required|string',
            'argument' => 'sometimes|nullable|string',
            'options' => 'sometimes|array',
            'confirmed' => 'sometimes|boolean',
        ]);

        $definition = ArtisanCommandRegistry::find((string) $request->input('command'));

        if ($definition === null || !$definition['runnable']) {
            return response()->json([
                'message' => 'Dieser Befehl ist im Artisan-Cockpit nicht ausführbar.',
            ], 422);
        }

        if (($definition['danger'] ?? 'safe') === 'confirm' && !$request->boolean('confirmed')) {
            return response()->json([
                'message' => 'Dieser Befehl erfordert eine Bestätigung (confirmed=true).',
            ], 422);
        }

        [$cliParts, $validationError] = $this->buildCliParts($definition, $request);
        if ($validationError !== null) {
            return response()->json(['message' => $validationError], 422);
        }

        $cmd = 'php artisan ' . implode(' ', $cliParts) . ' 2>&1';
        $timeout = $definition['timeout'] ?? 60;

        $process = Process::timeout($timeout)->path(base_path())->run($cmd);

        $result = [
            'command' => $definition['command'],
            'cli' => 'php artisan ' . implode(' ', $cliParts),
            'exit_code' => $process->exitCode(),
            'success' => $process->successful(),
            'output' => mb_substr($process->output(), -8000),
            'ran_at' => now()->toIso8601String(),
        ];

        Log::channel('artisan-cockpit')->info('Befehl ausgeführt', [
            'user' => $request->user()?->email,
            'command' => $result['cli'],
            'exit_code' => $result['exit_code'],
        ]);

        return response()->json(['data' => $result]);
    }

    private function authorizeAdmin(Request $request): void
    {
        if (!$request->user()?->hasRole('Admin')) {
            abort(403, 'Nur für Administratoren.');
        }
    }

    /**
     * Baut die CLI-Argumente strikt aus der Registry-Definition + validierten Request-Werten.
     * Es werden ausschließlich Optionen übernommen, die in der Definition des jeweiligen
     * Befehls existieren — beliebige zusätzliche Client-Eingaben werden ignoriert.
     *
     * @return array{0: array<int, string>, 1: ?string} [cliParts, validationErrorOrNull]
     */
    private function buildCliParts(array $definition, Request $request): array
    {
        $parts = [$definition['command']];

        $argumentDef = $definition['argument'] ?? null;
        if ($argumentDef !== null) {
            $value = $request->input('argument');
            $value = ($value === null || $value === '') ? ($argumentDef['default'] ?? null) : $value;

            if (!empty($argumentDef['required']) && ($value === null || $value === '')) {
                return [[], "Argument \"{$argumentDef['label']}\" ist erforderlich."];
            }

            if ($value !== null && $value !== '') {
                $parts[] = escapeshellarg((string) $value);
            }
        }

        $submittedOptions = (array) $request->input('options', []);

        foreach ($definition['options'] ?? [] as $optionDef) {
            $name = $optionDef['name'];
            $raw = $submittedOptions[$name] ?? null;

            if ($optionDef['type'] === 'boolean') {
                if ($raw === true || $raw === '1' || $raw === 1) {
                    $parts[] = '--' . $name;
                }
                continue;
            }

            $value = ($raw === null || $raw === '') ? ($optionDef['default'] ?? null) : $raw;

            if (!empty($optionDef['required']) && ($value === null || $value === '')) {
                return [[], "Option \"{$optionDef['label']}\" ist erforderlich."];
            }

            if ($optionDef['type'] === 'number' && $value !== null && !is_numeric($value)) {
                return [[], "Option \"{$optionDef['label']}\" muss eine Zahl sein."];
            }

            if ($value !== null && $value !== '') {
                $parts[] = '--' . $name . '=' . escapeshellarg((string) $value);
            }
        }

        if (!empty($definition['appends_force'])) {
            $parts[] = '--force';
        }

        return [$parts, null];
    }
}

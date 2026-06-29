<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\ContentType;
use App\Services\Content\ContentConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export/Import der gesamten Content-Konfiguration als JSON-Bundle.
 *
 * Ermöglicht den Roundtrip „Konfig exportieren → per KI gestalten →
 * idempotent zurückspielen". Schreibrechte = create auf ContentType
 * (Admin umgeht die Policy ohnehin, nicht aber das module:content-Gate).
 */
class ContentConfigController extends Controller
{
    public function __construct(private readonly ContentConfigService $service) {}

    /**
     * Konfiguration als JSON-Download. `?sections=content_types,navigations`
     * grenzt optional ein.
     */
    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', ContentType::class);

        $sections = $this->parseSections($request->query('sections'));
        $bundle = $this->service->export($sections);

        $json = json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $fileName = 'anypim-content-config-' . date('Y-m-d') . '.json';

        return new StreamedResponse(function () use ($json) {
            echo $json;
        }, 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Content-Length' => strlen($json),
        ]);
    }

    /**
     * Bundle importieren. Akzeptiert entweder das JSON direkt als Body, das
     * Bundle unter `bundle`, oder einen hochgeladenen `file`-Upload.
     */
    public function import(Request $request): JsonResponse
    {
        $this->authorize('create', ContentType::class);

        $bundle = $this->resolveBundle($request);
        if ($bundle === null) {
            return response()->json([
                'message' => 'Kein gültiges JSON-Bundle übergeben (als Body, Feld "bundle" oder Datei-Upload "file").',
            ], 422);
        }

        $sections = $this->parseSections($request->input('sections', $request->query('sections')));

        try {
            $summary = $this->service->import($bundle, $sections);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Content-Konfiguration importiert.',
            'summary' => $summary,
        ]);
    }

    /** @return array<string> */
    private function parseSections(mixed $raw): array
    {
        if (is_array($raw)) {
            return array_values(array_filter(array_map('strval', $raw)));
        }
        if (is_string($raw) && $raw !== '') {
            return array_values(array_filter(array_map('trim', explode(',', $raw))));
        }

        return [];
    }

    private function resolveBundle(Request $request): ?array
    {
        // 1) Datei-Upload
        if ($request->hasFile('file')) {
            $content = file_get_contents($request->file('file')->getRealPath());
            $decoded = json_decode((string) $content, true);

            return is_array($decoded) ? $decoded : null;
        }

        // 2) Feld "bundle" (Objekt oder JSON-String)
        if ($request->has('bundle')) {
            $bundle = $request->input('bundle');
            if (is_array($bundle)) {
                return $bundle;
            }
            if (is_string($bundle)) {
                $decoded = json_decode($bundle, true);

                return is_array($decoded) ? $decoded : null;
            }
        }

        // 3) Roh-JSON als Body
        $all = $request->all();
        if (isset($all['anypim_content_config'])) {
            return $all;
        }

        return null;
    }
}

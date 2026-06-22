<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\RenderSocialVideoJob;
use App\Services\Export\SocialVideoBuilder;
use App\Services\Export\SocialVideoElementMap;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Steuert die Erzeugung von Social-Media-Produktvideos (Reels/Shorts).
 *
 * Ablauf (asynchron, da Rendering langlaufend):
 *   POST social-video           → baut Reel-Definition, stößt Render-Job an, liefert job_id + Vorschau
 *   GET  social-video/{job}/status   → Render-Status (queued|rendering|done|failed)
 *   GET  social-video/{job}/download → fertiges MP4
 */
class SocialVideoController extends Controller
{
    public function store(Request $request, SocialVideoBuilder $builder): JsonResponse
    {
        $validated = $request->validate([
            'product_ids'   => 'required|array|min:1',
            'product_ids.*' => 'string',
            'mapping_id'    => 'sometimes|nullable|string',
            'languages'     => 'sometimes|array',
            'languages.*'   => 'string',
            'format'        => 'sometimes|string|in:9x16,1x1,16x9',
            'template'      => 'sometimes|string',
            'use_ai'        => 'sometimes|boolean',
        ]);

        $builder->setProductIds($validated['product_ids']);
        if (! empty($validated['mapping_id'])) {
            $builder->setMappingId($validated['mapping_id']);
        }
        if (! empty($validated['languages'])) {
            $builder->setLanguages($validated['languages']);
        }
        if (! empty($validated['format'])) {
            $builder->setFormat($validated['format']);
        }
        if (! empty($validated['template'])) {
            $builder->setTemplate($validated['template']);
        }
        $builder->setUseAi((bool) ($validated['use_ai'] ?? false));

        $reel = $builder->build();

        $jobId = (string) Str::uuid();
        $dir = RenderSocialVideoJob::jobDir($jobId);
        @mkdir($dir, 0775, true);
        file_put_contents(
            RenderSocialVideoJob::statusPath($jobId),
            json_encode([
                'job_id'     => $jobId,
                'status'     => 'queued',
                'message'    => 'In Warteschlange …',
                'updated_at' => now()->toIso8601String(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );

        RenderSocialVideoJob::dispatch($jobId, $reel);

        return response()->json([
            'job_id' => $jobId,
            'reel'   => $reel,
        ], 202);
    }

    public function status(string $job): JsonResponse
    {
        if (! Str::isUuid($job)) {
            return response()->json(['message' => 'Ungültige Job-ID'], 422);
        }

        $path = RenderSocialVideoJob::statusPath($job);
        if (! is_file($path)) {
            return response()->json(['job_id' => $job, 'status' => 'unknown', 'message' => 'Job nicht gefunden'], 404);
        }

        return response()->json(json_decode((string) file_get_contents($path), true) ?: []);
    }

    public function download(string $job): BinaryFileResponse|JsonResponse
    {
        if (! Str::isUuid($job)) {
            return response()->json(['message' => 'Ungültige Job-ID'], 422);
        }

        $path = public_path('videos/social/' . $job . '.mp4');
        if (! is_file($path)) {
            return response()->json(['message' => 'Video noch nicht verfügbar'], 404);
        }

        return response()->download($path, 'reel-' . date('Y-m-d') . '.mp4', [
            'Content-Type' => 'video/mp4',
        ]);
    }

    /**
     * Liefert die Standard-Mapping-Regeln und Zielfelder als Vorlage für die GUI.
     */
    public function defaultMapping(): JsonResponse
    {
        return response()->json([
            'target_fields'  => SocialVideoElementMap::TARGET_FIELDS,
            'mapping_rules'  => SocialVideoElementMap::defaultMappingRules(),
            'field_defaults' => SocialVideoElementMap::fieldDefaults(),
        ]);
    }
}

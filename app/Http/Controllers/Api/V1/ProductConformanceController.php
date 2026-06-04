<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Product;
use App\Models\ProductConformanceResult;
use App\Models\ProductReferenceProfile;
use App\Services\Conformance\ConformanceExplainer;
use App\Services\Conformance\ProfileConformanceChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Konformitätsprüfung von Produkten gegen ihr Referenz-Profil.
 *
 * - show:   letztes Ergebnis (Produkt-Tab "Konformität")
 * - check:  On-demand-Neuprüfung (synchron)
 * - assign: Referenz-Profil zuweisen/entfernen (keine abstrakten Profile)
 * - report: aggregierte Auswertung über den gesamten Bestand
 */
class ProductConformanceController extends Controller
{
    public function __construct(private ProfileConformanceChecker $checker)
    {
    }

    /**
     * Letztes Konformitätsergebnis eines Produkts.
     */
    public function show(Product $product): JsonResponse
    {
        $result = ProductConformanceResult::where('product_id', $product->id)
            ->with('profile:id,name,technical_name,version')
            ->first();

        $profile = $product->referenceProfile;

        return response()->json([
            'data' => $result,
            'profile' => $profile ? $profile->only(['id', 'name', 'technical_name', 'version']) : null,
            // Ergebnis veraltet, wenn Profil-Version inzwischen höher ist
            'is_stale' => $result && $profile && $result->profile_version < $profile->version,
        ]);
    }

    /**
     * On-demand-Neuprüfung eines Produkts (synchron).
     */
    public function check(Product $product): JsonResponse
    {
        if ($product->reference_profile_id === null) {
            return response()->json([
                'message' => 'Diesem Produkt ist kein Referenz-Profil zugewiesen.',
            ], 422);
        }

        $result = $this->checker->check($product, 'manual');

        return response()->json(['data' => $result]);
    }

    /**
     * Schritt 2: KI-Erklärung der Abweichungen (Claude AI).
     */
    public function explain(Product $product, ConformanceExplainer $explainer): JsonResponse
    {
        $result = ProductConformanceResult::where('product_id', $product->id)->first();

        if (!$result) {
            return response()->json([
                'message' => 'Es liegt noch kein Prüfergebnis vor. Bitte zuerst prüfen.',
            ], 422);
        }

        try {
            $explanation = $explainer->explain($product, $result);
        } catch (\RuntimeException $e) {
            // z.B. fehlender API-Key
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'KI-Erklärung fehlgeschlagen: ' . $e->getMessage(),
            ], 502);
        }

        return response()->json(['data' => $explanation]);
    }

    /**
     * Referenz-Profil zuweisen oder entfernen (reference_profile_id = null).
     */
    public function assign(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'reference_profile_id' => ['nullable', 'uuid', 'exists:product_reference_profiles,id'],
        ]);

        $profileId = $validated['reference_profile_id'] ?? null;

        if ($profileId !== null) {
            $profile = ProductReferenceProfile::findOrFail($profileId);
            // Abstrakte Profile dürfen nicht direkt referenziert werden
            if ($profile->is_abstract) {
                return response()->json([
                    'message' => 'Abstrakte Profile können nicht direkt zugewiesen werden.',
                ], 422);
            }
        }

        $product->update(['reference_profile_id' => $profileId]);

        // Direktes Ergebnis liefern (Observer prüft zwar async, hier sofort sichtbar)
        $result = $profileId !== null ? $this->checker->check($product, 'manual') : null;

        return response()->json([
            'data' => $product->only(['id', 'reference_profile_id']),
            'result' => $result,
        ]);
    }

    /**
     * Aggregierter Konformitäts-Report über alle geprüften Produkte.
     *
     * Query-Parameter:
     *  - profile_id : nur Produkte dieses Profils
     *  - status     : pass | warning | fail
     */
    public function report(Request $request): JsonResponse
    {
        $request->validate([
            'profile_id' => ['nullable', 'uuid', 'exists:product_reference_profiles,id'],
            'status' => ['nullable', 'string', 'in:pass,warning,fail'],
        ]);

        $base = ProductConformanceResult::query();
        if ($request->filled('profile_id')) {
            $base->where('profile_id', $request->input('profile_id'));
        }

        // Gesamt-Zusammenfassung
        $summary = (clone $base)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(status = 'pass') as pass")
            ->selectRaw("SUM(status = 'warning') as warning")
            ->selectRaw("SUM(status = 'fail') as fail")
            ->selectRaw('AVG(score) as avg_score')
            ->first();

        // Aufschlüsselung pro Profil
        $byProfile = (clone $base)
            ->select('profile_id')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(status = 'pass') as pass")
            ->selectRaw("SUM(status = 'warning') as warning")
            ->selectRaw("SUM(status = 'fail') as fail")
            ->selectRaw('AVG(score) as avg_score')
            ->groupBy('profile_id')
            ->get()
            ->map(function ($row) {
                $profile = ProductReferenceProfile::find($row->profile_id);
                return [
                    'profile_id' => $row->profile_id,
                    'profile_name' => $profile?->name,
                    'total' => (int) $row->total,
                    'pass' => (int) $row->pass,
                    'warning' => (int) $row->warning,
                    'fail' => (int) $row->fail,
                    'avg_score' => round((float) $row->avg_score, 1),
                ];
            });

        // Produktliste (paginiert), optional nach Status gefiltert
        $listQuery = (clone $base)->with('product:id,sku,name');
        if ($request->filled('status')) {
            $listQuery->where('status', $request->input('status'));
        }
        $products = $listQuery
            ->orderBy('score')
            ->paginate((int) $request->input('per_page', 50));

        return response()->json([
            'summary' => [
                'total' => (int) ($summary->total ?? 0),
                'pass' => (int) ($summary->pass ?? 0),
                'warning' => (int) ($summary->warning ?? 0),
                'fail' => (int) ($summary->fail ?? 0),
                'avg_score' => round((float) ($summary->avg_score ?? 0), 1),
            ],
            'by_profile' => $byProfile,
            'top_deviations' => $this->topDeviations($base),
            'products' => $products,
        ]);
    }

    /**
     * Häufigste Abweichungen (attribute + check) über die gefilterte Menge.
     * Begrenzt auf eine vertretbare Anzahl Zeilen, um Reports performant zu halten.
     *
     * @return array<int, array<string, mixed>>
     */
    private function topDeviations($base): array
    {
        $counts = [];
        (clone $base)
            ->select('deviations')
            ->whereIn('status', ['warning', 'fail'])
            ->limit(5000)
            ->get()
            ->each(function ($row) use (&$counts) {
                foreach (($row->deviations ?? []) as $dev) {
                    $key = ($dev['attribute'] ?? '?') . '|' . ($dev['check'] ?? '?');
                    if (!isset($counts[$key])) {
                        $counts[$key] = [
                            'attribute' => $dev['attribute'] ?? null,
                            'check' => $dev['check'] ?? null,
                            'severity' => $dev['severity'] ?? null,
                            'count' => 0,
                        ];
                    }
                    $counts[$key]['count']++;
                }
            });

        usort($counts, fn ($a, $b) => $b['count'] <=> $a['count']);

        return array_slice(array_values($counts), 0, 10);
    }
}

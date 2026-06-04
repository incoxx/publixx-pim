<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Jobs\RecheckProfileProducts;
use App\Models\ProductReferenceProfile;
use App\Services\Conformance\RuleSuggester;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * CRUD für virtuelle Referenzprodukte (Referenz-Profile).
 *
 * Bei einer Regeländerung wird die Profil-Version erhöht und ein Batch-Re-Check
 * aller referenzierenden Produkte (inkl. abgeleiteter Profile) angestoßen.
 */
class ProductReferenceProfileController extends Controller
{
    private const ALLOWED_CHECKS = [
        'required', 'not_empty', 'min', 'max', 'between', 'in_list', 'max_length', 'regex',
    ];
    private const ALLOWED_SEVERITIES = ['error', 'warning', 'info'];

    public function index(Request $request): JsonResponse
    {
        $query = ProductReferenceProfile::query()
            ->withCount(['products', 'childProfiles']);

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }
        if ($request->boolean('only_concrete')) {
            $query->where('is_abstract', false);
        }

        $profiles = $query->orderBy('name')->get();

        return response()->json(['data' => $profiles]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateProfile($request);

        $profile = ProductReferenceProfile::create($validated);

        return response()->json(['data' => $profile], 201);
    }

    public function show(ProductReferenceProfile $referenceProfile): JsonResponse
    {
        $referenceProfile->loadCount(['products', 'childProfiles']);
        $referenceProfile->load('parentProfile:id,name,technical_name');

        return response()->json([
            'data' => $referenceProfile,
            // Effektive (vererbte) Regeln für die GUI-Vorschau
            'effective_rules' => app(\App\Services\Conformance\ProfileConformanceChecker::class)
                ->effectiveRules($referenceProfile),
        ]);
    }

    public function update(Request $request, ProductReferenceProfile $referenceProfile): JsonResponse
    {
        $validated = $this->validateProfile($request, $referenceProfile);

        // Regeländerung erkennen → Versionssprung + Batch-Re-Check
        $rulesChanged = array_key_exists('rules', $validated)
            && json_encode($validated['rules']) !== json_encode($referenceProfile->rules);

        if ($rulesChanged) {
            $validated['version'] = $referenceProfile->version + 1;
        }

        $referenceProfile->update($validated);

        if ($rulesChanged) {
            dispatch(new RecheckProfileProducts($referenceProfile->id))->afterCommit();
        }

        return response()->json(['data' => $referenceProfile->fresh()]);
    }

    public function destroy(ProductReferenceProfile $referenceProfile): JsonResponse
    {
        // Profil mit referenzierenden Produkten oder Kind-Profilen nicht löschen
        $blockers = [];
        if ($referenceProfile->products()->exists()) {
            $blockers['products'] = $referenceProfile->products()->count();
        }
        if ($referenceProfile->childProfiles()->exists()) {
            $blockers['childProfiles'] = $referenceProfile->childProfiles()->count();
        }

        if (!empty($blockers)) {
            return response()->json([
                'message' => 'Profil wird noch verwendet und kann nicht gelöscht werden.',
                'blockers' => $blockers,
            ], 409);
        }

        $referenceProfile->delete();

        return response()->json(null, 204);
    }

    /**
     * Regel-Vorschläge aus einem oder mehreren Musterprodukten ableiten.
     * Liefert eine Regel-Vorlage (nicht gespeichert) für den Profil-Editor.
     */
    public function suggestRules(Request $request, RuleSuggester $suggester): JsonResponse
    {
        $validated = $request->validate([
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['uuid', 'exists:products,id'],
            'tolerance_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $rules = $suggester->suggest(
            $validated['product_ids'],
            (float) ($validated['tolerance_percent'] ?? 20)
        );

        return response()->json([
            'rules' => $rules,
            // Musterprodukte gleich als KI-Kontext (Schritt 2) vorschlagen
            'golden_product_ids' => $validated['product_ids'],
        ]);
    }

    /**
     * Manuell einen Batch-Re-Check aller referenzierenden Produkte anstoßen.
     */
    public function recheck(ProductReferenceProfile $referenceProfile): JsonResponse
    {
        dispatch(new RecheckProfileProducts($referenceProfile->id))->afterCommit();

        return response()->json([
            'message' => 'Re-Check wurde angestoßen.',
            'profile_id' => $referenceProfile->id,
        ], 202);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateProfile(Request $request, ?ProductReferenceProfile $existing = null): array
    {
        $isUpdate = $existing !== null;

        $validated = $request->validate([
            'technical_name' => [
                $isUpdate ? 'sometimes' : 'required',
                'string',
                'max:191',
                Rule::unique('product_reference_profiles', 'technical_name')
                    ->ignore($existing?->id),
            ],
            'name' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'parent_profile_id' => [
                'nullable',
                'uuid',
                'exists:product_reference_profiles,id',
                // Selbstreferenz verbieten
                Rule::notIn(array_filter([$existing?->id])),
            ],
            'is_abstract' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'rules' => ['sometimes', 'array'],
            'rules.*.attribute' => ['required_with:rules', 'string', 'max:191'],
            'rules.*.check' => ['required_with:rules', 'string', Rule::in(self::ALLOWED_CHECKS)],
            'rules.*.severity' => ['nullable', 'string', Rule::in(self::ALLOWED_SEVERITIES)],
            'rules.*.unit' => ['nullable', 'string', 'max:50'],
            'rules.*.min' => ['nullable', 'numeric'],
            'rules.*.max' => ['nullable', 'numeric'],
            'rules.*.value' => ['nullable'],
            'rules.*.values' => ['nullable', 'array'],
            'rules.*.pattern' => ['nullable', 'string', 'max:255'],
            'golden_product_ids' => ['nullable', 'array'],
            'golden_product_ids.*' => ['uuid', 'exists:products,id'],
        ]);

        return $validated;
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\PortalConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Admin CRUD fuer Portal-Konfigurationen.
 */
class PortalConfigController extends Controller
{
    private function validationRules(?string $excludeId = null): array
    {
        $slugUnique = $excludeId
            ? 'unique:portal_configs,slug,' . $excludeId
            : 'unique:portal_configs,slug';

        return [
            'name' => $excludeId ? 'sometimes|string|max:255' : 'required|string|max:255',
            'slug' => "sometimes|string|max:255|{$slugUnique}",
            'description' => 'sometimes|string|nullable',
            'html_template' => $excludeId ? 'sometimes|string' : 'required|string',
            'catalog_template_id' => 'sometimes|nullable|uuid|exists:catalog_templates,id',
            'filter_steps' => 'sometimes|array',
            'filter_steps.*.key' => 'sometimes|string|max:100',
            'filter_steps.*.attribute_id' => 'required|uuid|exists:attributes,id',
            'filter_steps.*.widget' => 'sometimes|string|in:country-select,language-select,filter-dropdown,filter-cards',
            'filter_steps.*.label' => 'sometimes|string|max:255',
            'branding' => 'sometimes|array|nullable',
            'css_variables' => 'sometimes|array|nullable',
            'custom_css' => 'sometimes|string|nullable|max:50000',
            'default_locale' => 'sometimes|string|max:10',
            'is_shared' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;

        $portals = PortalConfig::query()
            ->with('catalogTemplate:id,name,slug')
            ->when($userId, fn ($q) => $q->visibleTo($userId))
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $portals]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->validationRules());

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
            $base = $validated['slug'];
            $counter = 1;
            while (PortalConfig::where('slug', $validated['slug'])->exists()) {
                $validated['slug'] = $base . '-' . $counter++;
            }
        }

        $validated['filter_steps'] = $this->normalizeFilterSteps($validated['filter_steps'] ?? []);
        $validated['user_id'] = $request->user()?->id;

        $portal = PortalConfig::create($validated);
        $portal->load('catalogTemplate:id,name,slug');

        return response()->json(['data' => $portal], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $portal = PortalConfig::with('catalogTemplate:id,name,slug')->findOrFail($id);
        $this->authorizeAccess($request, $portal);

        return response()->json(['data' => $portal]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $portal = PortalConfig::findOrFail($id);
        $this->authorizeAccess($request, $portal);

        $validated = $request->validate($this->validationRules($id));

        if (isset($validated['filter_steps'])) {
            $validated['filter_steps'] = $this->normalizeFilterSteps($validated['filter_steps']);
        }

        $portal->update($validated);

        return response()->json(['data' => $portal->fresh()->load('catalogTemplate:id,name,slug')]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $portal = PortalConfig::findOrFail($id);
        $this->authorizeAccess($request, $portal);

        $portal->delete();

        return response()->json(null, 204);
    }

    public function duplicate(Request $request, string $id): JsonResponse
    {
        $portal = PortalConfig::findOrFail($id);
        $this->authorizeAccess($request, $portal);

        $newSlug = $portal->slug . '-kopie';
        $base = $newSlug;
        $counter = 1;
        while (PortalConfig::where('slug', $newSlug)->exists()) {
            $newSlug = $base . '-' . $counter++;
        }

        $copy = PortalConfig::create([
            'name' => $portal->name . ' (Kopie)',
            'slug' => $newSlug,
            'description' => $portal->description,
            'html_template' => $portal->html_template,
            'catalog_template_id' => $portal->catalog_template_id,
            'filter_steps' => $portal->filter_steps,
            'branding' => $portal->branding,
            'css_variables' => $portal->css_variables,
            'custom_css' => $portal->custom_css,
            'default_locale' => $portal->default_locale,
            'is_shared' => false,
            'is_active' => false,
            'user_id' => $request->user()?->id,
        ]);

        return response()->json(['data' => $copy], 201);
    }

    public function presets(): JsonResponse
    {
        $dir = base_path('portal-embed/examples');
        $presets = [];

        if (is_dir($dir)) {
            foreach (glob($dir . '/*.html') as $file) {
                $name = pathinfo($file, PATHINFO_FILENAME);
                $presets[] = [
                    'name' => ucwords(str_replace('-', ' ', $name)),
                    'slug' => $name,
                    'html' => file_get_contents($file),
                ];
            }
        }

        return response()->json(['data' => $presets]);
    }

    /**
     * Auto-generiert fehlende Keys/Labels/Widgets in Filter-Steps.
     */
    private function normalizeFilterSteps(array $steps): array
    {
        foreach ($steps as &$step) {
            // Key aus Label oder Attribut-Name ableiten wenn leer
            if (empty($step['key']) && !empty($step['label'])) {
                $step['key'] = Str::slug($step['label'], '_');
            } elseif (empty($step['key']) && !empty($step['attribute_id'])) {
                $attr = \App\Models\Attribute::find($step['attribute_id']);
                $step['key'] = $attr ? Str::slug($attr->technical_name, '_') : 'step_' . uniqid();
            }

            // Default Widget wenn nicht gesetzt
            if (empty($step['widget'])) {
                $step['widget'] = 'filter-dropdown';
            }

            // Label aus Attribut-Name wenn leer
            if (empty($step['label']) && !empty($step['attribute_id'])) {
                $attr = $attr ?? \App\Models\Attribute::find($step['attribute_id']);
                $step['label'] = $attr?->name_de ?? $step['key'] ?? '';
            }
        }
        unset($step);

        return $steps;
    }

    private function authorizeAccess(Request $request, PortalConfig $portal): void
    {
        $userId = $request->user()?->id;
        if (!$portal->is_shared && $portal->user_id && $userId !== $portal->user_id) {
            abort(403, 'Zugriff verweigert.');
        }
    }
}

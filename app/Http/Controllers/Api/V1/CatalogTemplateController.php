<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\CatalogTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CatalogTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;

        $templates = CatalogTemplate::query()
            ->when($userId, fn ($q) => $q->visibleTo($userId))
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $templates]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'sometimes|string|nullable',
            'slug' => 'sometimes|string|max:255|unique:catalog_templates,slug',
            'html_template' => 'required|string',
            'css_variables' => 'sometimes|array|nullable',
            'settings' => 'sometimes|array|nullable',
            'is_shared' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
            $base = $validated['slug'];
            $counter = 1;
            while (CatalogTemplate::where('slug', $validated['slug'])->exists()) {
                $validated['slug'] = $base . '-' . $counter++;
            }
        }

        $validated['user_id'] = $request->user()?->id;

        $template = CatalogTemplate::create($validated);

        return response()->json(['data' => $template], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $template = CatalogTemplate::findOrFail($id);
        $this->authorizeAccess($request, $template);

        return response()->json(['data' => $template]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $template = CatalogTemplate::findOrFail($id);
        $this->authorizeAccess($request, $template);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|nullable',
            'slug' => 'sometimes|string|max:255|unique:catalog_templates,slug,' . $id,
            'html_template' => 'sometimes|string',
            'css_variables' => 'sometimes|array|nullable',
            'settings' => 'sometimes|array|nullable',
            'is_shared' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ]);

        $template->update($validated);

        return response()->json(['data' => $template->fresh()]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $template = CatalogTemplate::findOrFail($id);
        $this->authorizeAccess($request, $template);

        $template->delete();

        return response()->json(null, 204);
    }

    /**
     * POST /api/v1/catalog-templates/{id}/duplicate — Duplicate a template.
     */
    public function duplicate(Request $request, string $id): JsonResponse
    {
        $template = CatalogTemplate::findOrFail($id);
        $this->authorizeAccess($request, $template);

        $newSlug = $template->slug . '-kopie';
        $base = $newSlug;
        $counter = 1;
        while (CatalogTemplate::where('slug', $newSlug)->exists()) {
            $newSlug = $base . '-' . $counter++;
        }

        $copy = CatalogTemplate::create([
            'name' => $template->name . ' (Kopie)',
            'description' => $template->description,
            'slug' => $newSlug,
            'html_template' => $template->html_template,
            'css_variables' => $template->css_variables,
            'settings' => $template->settings,
            'is_shared' => false,
            'is_active' => false,
            'user_id' => $request->user()?->id,
        ]);

        return response()->json(['data' => $copy], 201);
    }

    /**
     * GET /api/v1/catalog-templates/{id}/preview — Preview URL for the template.
     */
    public function preview(Request $request, string $id): JsonResponse
    {
        $template = CatalogTemplate::findOrFail($id);
        $this->authorizeAccess($request, $template);

        $basePath = rtrim(parse_url(config('app.url'), PHP_URL_PATH) ?? '', '/');
        $previewUrl = rtrim(config('app.url'), '/') . $basePath . '/catalog-embed/' . $template->slug;

        return response()->json([
            'data' => [
                'preview_url' => $previewUrl,
                'slug' => $template->slug,
            ],
        ]);
    }

    /**
     * GET /api/v1/catalog-templates/presets — Built-in file-based templates.
     */
    public function presets(): JsonResponse
    {
        $dir = base_path('catalog-embed/examples');
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

    private function authorizeAccess(Request $request, CatalogTemplate $template): void
    {
        $userId = $request->user()?->id;
        if (!$template->is_shared && $template->user_id && $userId !== $template->user_id) {
            abort(403, 'Kein Zugriff auf dieses Katalog-Template.');
        }
    }
}

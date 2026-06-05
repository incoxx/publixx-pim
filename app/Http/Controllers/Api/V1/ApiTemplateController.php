<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Traits\ChecksDeletionConstraints;
use App\Models\ApiTemplate;
use App\Models\Attribute;
use App\Models\MediaUsageType;
use App\Models\PriceType;
use App\Models\ProductRelationType;
use App\Models\SearchProfile;
use App\Services\ApiDesigner\ApiDesignerService;
use App\Services\ApiDesigner\GraphqlDesignerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApiTemplateController extends Controller
{
    use ChecksDeletionConstraints;

    public function __construct(
        private readonly ApiDesignerService $apiDesignerService,
        private readonly GraphqlDesignerService $graphqlDesignerService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;

        $templates = ApiTemplate::query()
            ->when($userId, fn ($q) => $q->visibleTo($userId))
            ->with('searchProfile')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $templates]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'sometimes|string|nullable',
            'search_profile_id' => 'sometimes|string|nullable|exists:search_profiles,id',
            'template_json' => 'required|array',
            'direction' => 'sometimes|string|in:export,import,bidirectional',
            'output_format' => 'sometimes|string|in:json,graphql',
            'language' => 'sometimes|string|max:5',
            'is_shared' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'is_mcp_enabled' => 'sometimes|boolean',
            'slug' => 'sometimes|string|max:100|unique:api_templates,slug',
            'auth_type' => 'sometimes|string|in:bearer,api_key,none',
            'rate_limit' => 'sometimes|integer|min:1|max:10000',
        ]);

        $validated['user_id'] = $request->user()?->id;

        // Auto-generate slug if not provided
        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        // Auto-generate API key
        $plainKey = Str::random(48);
        $validated['api_key'] = hash('sha256', $plainKey);

        $template = ApiTemplate::create($validated);

        return response()->json([
            'data' => $template->load('searchProfile'),
            'api_key_plain' => $plainKey,
        ], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $template = ApiTemplate::findOrFail($id);
        $this->authorizeAccess($request, $template);

        return response()->json([
            'data' => $template->load('searchProfile'),
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $template = ApiTemplate::findOrFail($id);
        $this->authorizeAccess($request, $template);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|nullable',
            'search_profile_id' => 'sometimes|string|nullable|exists:search_profiles,id',
            'template_json' => 'sometimes|array',
            'direction' => 'sometimes|string|in:export,import,bidirectional',
            'output_format' => 'sometimes|string|in:json,graphql',
            'language' => 'sometimes|string|max:5',
            'is_shared' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'is_mcp_enabled' => 'sometimes|boolean',
            'slug' => 'sometimes|string|max:100|unique:api_templates,slug,' . $template->id,
            'auth_type' => 'sometimes|string|in:bearer,api_key,none',
            'rate_limit' => 'sometimes|integer|min:1|max:10000',
        ]);

        $template->update($validated);

        return response()->json(['data' => $template->fresh()->load('searchProfile')]);
    }

    public function dependencies(ApiTemplate $apiTemplate): JsonResponse
    {
        return $this->dependenciesResponse($apiTemplate);
    }

    public function destroy(Request $request, ApiTemplate $apiTemplate): JsonResponse
    {
        $this->authorizeAccess($request, $apiTemplate);

        return $this->destroyWithConstraintCheck($request, $apiTemplate);
    }

    /**
     * GET /api/v1/api-templates/fields — Available fields for the designer palette.
     */
    public function fields(): JsonResponse
    {
        // Base fields
        $baseFields = [
            ['field' => 'sku', 'label_de' => 'Artikelnummer', 'label_en' => 'SKU', 'category' => 'base'],
            ['field' => 'name', 'label_de' => 'Produktname', 'label_en' => 'Product Name', 'category' => 'base'],
            ['field' => 'ean', 'label_de' => 'EAN', 'label_en' => 'EAN', 'category' => 'base'],
            ['field' => 'status', 'label_de' => 'Status', 'label_en' => 'Status', 'category' => 'base'],
            ['field' => 'product_type', 'label_de' => 'Produkttyp', 'label_en' => 'Product Type', 'category' => 'base'],
            ['field' => 'hierarchy_node', 'label_de' => 'Kategorie', 'label_en' => 'Category', 'category' => 'base'],
            ['field' => 'created_at', 'label_de' => 'Erstellt am', 'label_en' => 'Created at', 'category' => 'base'],
            ['field' => 'updated_at', 'label_de' => 'Geändert am', 'label_en' => 'Updated at', 'category' => 'base'],
        ];

        // Attributes
        $attributes = Attribute::query()
            ->select(['id', 'technical_name', 'name_de', 'name_en', 'data_type', 'attribute_type_id'])
            ->with('attributeType:id,name_de,name_en')
            ->orderBy('name_de')
            ->get()
            ->map(fn ($attr) => [
                'attributeId' => $attr->id,
                'technical_name' => $attr->technical_name,
                'label_de' => $attr->name_de,
                'label_en' => $attr->name_en,
                'data_type' => $attr->data_type,
                'category' => 'attribute',
                'group_de' => $attr->attributeType?->name_de,
                'group_en' => $attr->attributeType?->name_en,
            ]);

        // Group field options
        $groupFields = [
            ['field' => 'product_type', 'label_de' => 'Produkttyp', 'label_en' => 'Product Type'],
            ['field' => 'master_hierarchy_node', 'label_de' => 'Hierarchieknoten', 'label_en' => 'Hierarchy Node'],
            ['field' => 'status', 'label_de' => 'Status', 'label_en' => 'Status'],
            ['field' => 'none', 'label_de' => 'Keine Gruppierung', 'label_en' => 'No Grouping'],
        ];

        $priceTypes = PriceType::orderBy('name_de')->get()->map(fn ($pt) => [
            'priceTypeId'  => $pt->id,
            'technical_name' => $pt->technical_name,
            'label_de'     => $pt->name_de,
            'label_en'     => $pt->name_en,
            'category'     => 'price',
        ]);

        $mediaUsageTypes = MediaUsageType::orderBy('sort_order')->orderBy('name_de')->get()->map(fn ($mt) => [
            'usageTypeId'  => $mt->id,
            'technical_name' => $mt->technical_name,
            'label_de'     => $mt->name_de,
            'label_en'     => $mt->name_en,
            'category'     => 'media',
        ]);

        $relationTypes = ProductRelationType::orderBy('name_de')->get()->map(fn ($rt) => [
            'relationTypeId' => $rt->id,
            'technical_name' => $rt->technical_name,
            'label_de'       => $rt->name_de,
            'label_en'       => $rt->name_en,
            'category'       => 'relation',
        ]);

        return response()->json([
            'data' => [
                'base_fields'       => $baseFields,
                'attributes'        => $attributes,
                'group_fields'      => $groupFields,
                'price_types'       => $priceTypes,
                'media_usage_types' => $mediaUsageTypes,
                'relation_types'    => $relationTypes,
            ],
        ]);
    }

    /**
     * POST /api/v1/api-templates/{id}/preview — Generate JSON preview with limited products.
     */
    public function preview(Request $request, string $id): JsonResponse
    {
        $template = ApiTemplate::findOrFail($id);
        $this->authorizeAccess($request, $template);

        $validated = $request->validate([
            'search_profile_id' => 'sometimes|string|nullable|exists:search_profiles,id',
            'limit' => 'sometimes|integer|min:1|max:20',
        ]);

        $searchProfile = isset($validated['search_profile_id'])
            ? SearchProfile::find($validated['search_profile_id'])
            : null;

        $result = $this->apiDesignerService->preview(
            $template,
            $searchProfile,
            $validated['limit'] ?? 5,
        );

        return response()->json([
            'data' => $result,
        ]);
    }

    /**
     * POST /api/v1/api-templates/{id}/regenerate-key — Generate a new API key.
     */
    public function regenerateApiKey(Request $request, string $id): JsonResponse
    {
        $template = ApiTemplate::findOrFail($id);
        $this->authorizeAccess($request, $template);

        $plainKey = Str::random(48);
        $template->update(['api_key' => hash('sha256', $plainKey)]);

        return response()->json([
            'data' => $template->fresh(),
            'api_key_plain' => $plainKey,
        ]);
    }

    /**
     * POST /api/v1/api-templates/{id}/schema-preview — GraphQL-Schema SDL + Sample Query.
     */
    public function schemaPreview(Request $request, string $id): JsonResponse
    {
        $template = ApiTemplate::findOrFail($id);
        $this->authorizeAccess($request, $template);

        $result = $this->graphqlDesignerService->schemaPreview($template);

        return response()->json(['data' => $result]);
    }

    private function authorizeAccess(Request $request, ApiTemplate $template): void
    {
        $userId = $request->user()?->id;
        if (!$template->is_shared && $template->user_id && $userId !== $template->user_id) {
            abort(403, 'Kein Zugriff auf dieses API-Template.');
        }
    }
}

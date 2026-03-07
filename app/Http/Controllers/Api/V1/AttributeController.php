<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreAttributeRequest;
use App\Http\Requests\Api\V1\UpdateAttributeRequest;
use App\Http\Resources\Api\V1\AttributeResource;
use App\Models\Attribute;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AttributeController extends Controller
{
    private const ALLOWED_INCLUDES = [
        'attributeType', 'unitGroup', 'defaultUnit', 'valueList',
        'children', 'parent', 'comparisonOperatorGroup', 'attributeViews',
        'dictionaryEntries',
    ];

    private const ALLOWED_FILTERS = [
        'status', 'data_type', 'attribute_type_id', 'is_translatable',
        'is_searchable', 'is_mandatory', 'is_inheritable', 'is_variant_attribute',
        'is_internal', 'source_system',
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Attribute::class);

        $query = Attribute::query()
            ->with($this->parseIncludes($request, self::ALLOWED_INCLUDES));

        $this->applyFilters($query, array_intersect_key(
            $request->query('filter', []),
            array_flip(self::ALLOWED_FILTERS)
        ));
        $this->applySearch($query, $request, ['name_de', 'name_en', 'technical_name']);
        $this->applySorting($query, $request, 'position', 'asc');

        return AttributeResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    public function store(StoreAttributeRequest $request): JsonResponse
    {
        $this->authorize('create', Attribute::class);

        $attribute = Attribute::create($request->validated());

        return (new AttributeResource($attribute))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Attribute $attribute): AttributeResource
    {
        $this->authorize('view', $attribute);

        $attribute->load($this->parseIncludes($request, self::ALLOWED_INCLUDES));

        return new AttributeResource($attribute);
    }

    public function update(UpdateAttributeRequest $request, Attribute $attribute): AttributeResource
    {
        $this->authorize('update', $attribute);

        $attribute->update($request->validated());

        return new AttributeResource($attribute->fresh());
    }

    public function destroy(Attribute $attribute): JsonResponse
    {
        $this->authorize('delete', $attribute);

        $attribute->delete();

        return response()->json(null, 204);
    }

    private const BULK_ALLOWED_FIELDS = [
        'is_translatable', 'is_multipliable', 'is_searchable', 'is_mandatory',
        'is_unique', 'is_inheritable', 'is_variant_attribute', 'is_internal',
        'attribute_type_id', 'status',
    ];

    public function bulkUpdate(Request $request): JsonResponse
    {
        $this->authorize('update', Attribute::class);

        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'uuid|exists:attributes,id',
            'fields' => 'required|array|min:1',
            'fields.is_translatable' => 'boolean',
            'fields.is_multipliable' => 'boolean',
            'fields.is_searchable' => 'boolean',
            'fields.is_mandatory' => 'boolean',
            'fields.is_unique' => 'boolean',
            'fields.is_inheritable' => 'boolean',
            'fields.is_variant_attribute' => 'boolean',
            'fields.is_internal' => 'boolean',
            'fields.attribute_type_id' => 'nullable|uuid|exists:attribute_types,id',
            'fields.status' => 'in:active,inactive',
        ]);

        $fields = array_intersect_key(
            $request->input('fields'),
            array_flip(self::BULK_ALLOWED_FIELDS)
        );

        if (empty($fields)) {
            return response()->json(['message' => 'No valid fields provided.'], 422);
        }

        $count = Attribute::whereIn('id', $request->input('ids'))->update($fields);

        return response()->json([
            'message' => "{$count} Attribute aktualisiert.",
            'updated' => $count,
        ]);
    }
}

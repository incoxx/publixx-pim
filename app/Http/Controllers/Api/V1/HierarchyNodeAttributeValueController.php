<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\BulkUpdateNodeAttributeValuesRequest;
use App\Http\Resources\Api\V1\HierarchyNodeAttributeValueResource;
use App\Models\HierarchyNode;
use App\Models\HierarchyNodeAttributeValue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class HierarchyNodeAttributeValueController extends Controller
{
    /**
     * GET /hierarchy-nodes/{hierarchy_node}/attribute-values
     */
    public function index(Request $request, HierarchyNode $hierarchyNode): AnonymousResourceCollection
    {
        $this->authorize('view', $hierarchyNode);

        $query = $hierarchyNode->attributeValues()
            ->with(['attribute', 'valueListEntry', 'unit']);

        if ($request->has('filter')) {
            $this->applyFilters($query, $request->query('filter'));
        }

        return HierarchyNodeAttributeValueResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    /**
     * PUT /hierarchy-nodes/{hierarchy_node}/attribute-values
     *
     * Bulk upsert attribute values for a hierarchy node.
     */
    public function bulkUpdate(BulkUpdateNodeAttributeValuesRequest $request, HierarchyNode $hierarchyNode): AnonymousResourceCollection
    {
        $this->authorize('update', $hierarchyNode);

        $values = $request->validated()['values'];

        foreach ($values as $entry) {
            $key = [
                'hierarchy_node_id' => $hierarchyNode->id,
                'attribute_id' => $entry['attribute_id'],
                'language' => $entry['language'] ?? null,
                'multiplied_index' => $entry['multiplied_index'] ?? 0,
            ];

            $data = array_filter([
                'value_string' => $this->resolveValue($entry, 'string'),
                'value_number' => $this->resolveValue($entry, 'number'),
                'value_date' => $this->resolveValue($entry, 'date'),
                'value_flag' => $this->resolveValue($entry, 'flag'),
                'value_selection_id' => $entry['value_selection_id'] ?? null,
                'unit_id' => $entry['unit_id'] ?? null,
            ], fn ($v) => $v !== null);

            HierarchyNodeAttributeValue::updateOrCreate($key, $data);
        }

        return HierarchyNodeAttributeValueResource::collection(
            $hierarchyNode->attributeValues()
                ->with(['attribute', 'valueListEntry', 'unit'])
                ->get()
        );
    }

    /**
     * DELETE /hierarchy-node-attribute-values/{hierarchy_node_attribute_value}
     */
    public function destroy(HierarchyNodeAttributeValue $hierarchyNodeAttributeValue): JsonResponse
    {
        $this->authorize('update', $hierarchyNodeAttributeValue->hierarchyNode);

        $hierarchyNodeAttributeValue->delete();

        return response()->json(null, 204);
    }

    /**
     * Resolve the typed value from the request entry.
     * The frontend sends a generic 'value' field; we map it to the correct column
     * based on the attribute's data_type. For explicit columns, pass them directly.
     */
    private function resolveValue(array $entry, string $type): mixed
    {
        $key = "value_{$type}";

        return $entry[$key] ?? null;
    }
}

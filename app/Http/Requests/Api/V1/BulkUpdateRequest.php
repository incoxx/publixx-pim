<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Controller handles authorization
    }

    public function rules(): array
    {
        return [
            'product_ids' => 'required|array|min:1|max:500',
            'product_ids.*' => 'required|string|uuid|exists:products,id',

            'operations' => 'required|array',

            // Attributes
            'operations.attributes' => 'nullable|array',
            'operations.attributes.*.attribute_id' => 'required_with:operations.attributes|string|uuid|exists:attributes,id',
            'operations.attributes.*.value' => 'present',
            'operations.attributes.*.language' => 'nullable|string|max:5',
            'operations.attributes.*.mode' => 'required_with:operations.attributes|in:overwrite,fill_empty,clear',

            // Relations
            'operations.relations' => 'nullable|array',
            'operations.relations.*.relation_type_id' => 'required_with:operations.relations|string|uuid|exists:product_relation_types,id',
            'operations.relations.*.target_product_id' => 'required_with:operations.relations|string|uuid|exists:products,id',
            'operations.relations.*.action' => 'required_with:operations.relations|in:add,remove',

            // Output hierarchy
            'operations.output_hierarchy' => 'nullable|array',
            'operations.output_hierarchy.*.hierarchy_node_id' => 'required_with:operations.output_hierarchy|string|uuid|exists:hierarchy_nodes,id',
            'operations.output_hierarchy.*.action' => 'required_with:operations.output_hierarchy|in:assign,remove',

            // Status
            'operations.status' => 'nullable|in:draft,active,inactive,discontinued',

            // Master hierarchy node
            'operations.master_hierarchy_node_id' => 'nullable|string|uuid|exists:hierarchy_nodes,id',

            // Media
            'operations.media' => 'nullable|array',
            'operations.media.*.media_id' => 'required_with:operations.media|string|uuid|exists:media,id',
            'operations.media.*.usage_type_id' => 'nullable|string|uuid',
            'operations.media.*.action' => 'required_with:operations.media|in:assign,remove',
        ];
    }
}

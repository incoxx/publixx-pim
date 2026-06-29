<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreNavigationNodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parent_node_id' => 'nullable|string|exists:navigation_nodes,id',
            'sort_order' => 'sometimes|integer',
            'label_de' => 'required|string|max:255',
            'label_en' => 'nullable|string|max:255',
            'label_json' => 'nullable|array',
            'slug' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:50',
            'is_visible' => 'sometimes|boolean',
            'target_type' => 'required|in:folder,content_page,product_category,product,external_url',
            // Ziel je nach target_type
            'content_page_id' => 'nullable|required_if:target_type,content_page|string|exists:content_pages,id',
            'hierarchy_node_id' => 'nullable|required_if:target_type,product_category|string|exists:hierarchy_nodes,id',
            'product_id' => 'nullable|required_if:target_type,product|string|exists:products,id',
            'external_url' => 'nullable|required_if:target_type,external_url|string|max:1000',
            'auto_expand' => 'sometimes|boolean',
            'expand_depth' => 'nullable|integer|min:1',
        ];
    }
}

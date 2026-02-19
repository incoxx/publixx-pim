<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HierarchyNodeAttributeAssignment extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'hierarchy_node_attribute_assignments';

    protected $fillable = [
        'hierarchy_node_id',
        'attribute_id',
        'collection_name',
        'collection_sort',
        'attribute_sort',
        'dont_inherit',
        'access_hierarchy',
        'access_product',
        'access_variant',
    ];

    protected function casts(): array
    {
        return [
            'collection_sort' => 'integer',
            'attribute_sort' => 'integer',
            'dont_inherit' => 'boolean',
        ];
    }

    public function hierarchyNode(): BelongsTo
    {
        return $this->belongsTo(HierarchyNode::class);
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }
}

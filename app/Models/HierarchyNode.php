<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class HierarchyNode extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'hierarchy_id',
        'parent_node_id',
        'name_de',
        'name_en',
        'name_json',
        'path',
        'depth',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'name_json' => 'array',
            'depth' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function hierarchy(): BelongsTo
    {
        return $this->belongsTo(Hierarchy::class);
    }

    public function parentNode(): BelongsTo
    {
        return $this->belongsTo(HierarchyNode::class, 'parent_node_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(HierarchyNode::class, 'parent_node_id')->orderBy('sort_order');
    }

    public function attributeAssignments(): HasMany
    {
        return $this->hasMany(HierarchyNodeAttributeAssignment::class);
    }

    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class, 'hierarchy_node_attribute_assignments')
            ->withPivot([
                'collection_name', 'collection_sort', 'attribute_sort',
                'dont_inherit', 'access_hierarchy', 'access_product', 'access_variant',
            ]);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'master_hierarchy_node_id');
    }

    public function outputProductAssignments(): HasMany
    {
        return $this->hasMany(OutputHierarchyProductAssignment::class);
    }

    /**
     * Get all descendants using materialized path.
     */
    public function scopeDescendantsOf($query, string $path)
    {
        return $query->where('path', 'like', $path . '%')
            ->where('path', '!=', $path);
    }

    /**
     * Get all ancestors using materialized path.
     */
    public function scopeAncestorsOf($query, string $path)
    {
        if (DB::getDriverName() === 'sqlite') {
            return $query->whereRaw('? LIKE (path || \'%\')', [$path])
                ->where('path', '!=', $path);
        }

        return $query->whereRaw('? LIKE CONCAT(path, \'%\')', [$path])
            ->where('path', '!=', $path);
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'product_type_id',
        'sku',
        'ean',
        'name',
        'status',
        'product_type_ref',
        'parent_product_id',
        'master_hierarchy_node_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [];
    }

    // --- Relationships ---

    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class);
    }

    public function parentProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'parent_product_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(Product::class, 'parent_product_id');
    }

    public function masterHierarchyNode(): BelongsTo
    {
        return $this->belongsTo(HierarchyNode::class, 'master_hierarchy_node_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function attributeValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }

    public function variantInheritanceRules(): HasMany
    {
        return $this->hasMany(VariantInheritanceRule::class);
    }

    public function media(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'product_media_assignments')
            ->withPivot(['usage_type', 'sort_order', 'is_primary'])
            ->orderByPivot('sort_order');
    }

    public function mediaAssignments(): HasMany
    {
        return $this->hasMany(ProductMediaAssignment::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    public function outgoingRelations(): HasMany
    {
        return $this->hasMany(ProductRelation::class, 'source_product_id');
    }

    public function incomingRelations(): HasMany
    {
        return $this->hasMany(ProductRelation::class, 'target_product_id');
    }

    public function outputHierarchyAssignments(): HasMany
    {
        return $this->hasMany(OutputHierarchyProductAssignment::class);
    }

    public function searchIndex(): HasOne
    {
        return $this->hasOne(ProductSearchIndex::class, 'product_id');
    }
}

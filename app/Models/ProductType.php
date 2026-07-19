<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasDeletionConstraints;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductType extends Model
{
    use HasDeletionConstraints, HasFactory, HasUuids;

    protected $fillable = [
        'technical_name',
        'name_de',
        'name_en',
        'name_json',
        'description',
        'icon',
        'color',
        'has_variants',
        'has_ean',
        'has_prices',
        'has_media',
        'has_stock',
        'has_physical_dimensions',
        'has_dynamic_cluster',
        'allows_free_attributes',
        'default_attribute_groups',
        'allowed_relation_types',
        'default_variant_axes',
        'validation_rules',
        'sort_order',
        'is_active',
        'workflow_id',
    ];

    protected function casts(): array
    {
        return [
            'name_json' => 'array',
            'has_variants' => 'boolean',
            'has_ean' => 'boolean',
            'has_prices' => 'boolean',
            'has_media' => 'boolean',
            'has_stock' => 'boolean',
            'has_physical_dimensions' => 'boolean',
            'has_dynamic_cluster' => 'boolean',
            'allows_free_attributes' => 'boolean',
            'default_attribute_groups' => 'array',
            'allowed_relation_types' => 'array',
            'default_variant_axes' => 'array',
            'validation_rules' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function deletionConstraints(): array
    {
        return [
            'products'     => 'Produkte',
        ];
    }
}

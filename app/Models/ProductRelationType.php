<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasDeletionConstraints;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductRelationType extends Model
{
    use HasDeletionConstraints, HasFactory, HasUuids;

    protected $fillable = [
        'technical_name',
        'name_de',
        'name_en',
        'name_json',
        'is_bidirectional',
        'allowed_source_product_type_ids',
        'allowed_target_product_type_ids',
    ];

    protected function casts(): array
    {
        return [
            'name_json' => 'array',
            'is_bidirectional' => 'boolean',
            'allowed_source_product_type_ids' => 'array',
            'allowed_target_product_type_ids' => 'array',
        ];
    }

    /**
     * Ob dieser Beziehungstyp für den gegebenen Produkttyp als Quelle
     * zulässig ist. Leere/keine Einschränkung = alle Produkttypen erlaubt.
     */
    public function allowsSourceProductType(?string $productTypeId): bool
    {
        return empty($this->allowed_source_product_type_ids)
            || in_array($productTypeId, $this->allowed_source_product_type_ids, true);
    }

    /**
     * Ob dieser Beziehungstyp für den gegebenen Produkttyp als Ziel
     * zulässig ist. Leere/keine Einschränkung = alle Produkttypen erlaubt.
     */
    public function allowsTargetProductType(?string $productTypeId): bool
    {
        return empty($this->allowed_target_product_type_ids)
            || in_array($productTypeId, $this->allowed_target_product_type_ids, true);
    }

    public function relations(): HasMany
    {
        return $this->hasMany(ProductRelation::class, 'relation_type_id');
    }

    public function defaultAttributes(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class, 'relation_type_default_attributes', 'relation_type_id', 'attribute_id')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function deletionConstraints(): array
    {
        return [
            'relations'         => 'Produktbeziehungen',
            'defaultAttributes' => 'Standard-Attribute',
        ];
    }
}

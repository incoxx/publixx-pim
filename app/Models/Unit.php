<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasDeletionConstraints;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use HasDeletionConstraints, HasFactory, HasUuids;

    protected $fillable = [
        'unit_group_id',
        'technical_name',
        'abbreviation',
        'abbreviation_json',
        'conversion_factor',
        'is_base_unit',
        'is_translatable',
    ];

    protected function casts(): array
    {
        return [
            'abbreviation_json' => 'array',
            'conversion_factor' => 'decimal:10',
            'is_base_unit' => 'boolean',
            'is_translatable' => 'boolean',
        ];
    }

    public function unitGroup(): BelongsTo
    {
        return $this->belongsTo(UnitGroup::class);
    }

    public function productAttributeValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }

    public function attributesAsDefault(): HasMany
    {
        return $this->hasMany(Attribute::class, 'default_unit_id');
    }

    public function deletionConstraints(): array
    {
        return [
            'productAttributeValues' => 'Produktattributwerte',
            'attributesAsDefault'    => 'Attribute (Standardeinheit)',
        ];
    }
}

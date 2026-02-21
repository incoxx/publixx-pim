<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attribute extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'technical_name',
        'name_de',
        'name_en',
        'name_json',
        'description_de',
        'description_en',
        'data_type',
        'attribute_type_id',
        'value_list_id',
        'unit_group_id',
        'default_unit_id',
        'comparison_operator_group_id',
        'is_translatable',
        'is_multipliable',
        'max_multiplied',
        'max_pre_decimal',
        'max_post_decimal',
        'max_characters',
        'is_searchable',
        'is_mandatory',
        'is_unique',
        'is_country_specific',
        'is_inheritable',
        'is_variant_attribute',
        'is_internal',
        'parent_attribute_id',
        'position',
        'source_system',
        'source_attribute_name',
        'source_attribute_key',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'name_json' => 'array',
            'is_translatable' => 'boolean',
            'is_multipliable' => 'boolean',
            'max_multiplied' => 'integer',
            'max_pre_decimal' => 'integer',
            'max_post_decimal' => 'integer',
            'max_characters' => 'integer',
            'is_searchable' => 'boolean',
            'is_mandatory' => 'boolean',
            'is_unique' => 'boolean',
            'is_country_specific' => 'boolean',
            'is_inheritable' => 'boolean',
            'is_variant_attribute' => 'boolean',
            'is_internal' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function attributeType(): BelongsTo
    {
        return $this->belongsTo(AttributeType::class);
    }

    public function valueList(): BelongsTo
    {
        return $this->belongsTo(ValueList::class);
    }

    public function unitGroup(): BelongsTo
    {
        return $this->belongsTo(UnitGroup::class);
    }

    public function defaultUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'default_unit_id');
    }

    public function comparisonOperatorGroup(): BelongsTo
    {
        return $this->belongsTo(ComparisonOperatorGroup::class);
    }

    public function parentAttribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class, 'parent_attribute_id');
    }

    public function childAttributes(): HasMany
    {
        return $this->hasMany(Attribute::class, 'parent_attribute_id');
    }

    public function attributeViews(): BelongsToMany
    {
        return $this->belongsToMany(AttributeView::class, 'attribute_view_assignments')
            ->using(AttributeViewAssignment::class);
    }

    public function viewAssignments(): HasMany
    {
        return $this->hasMany(AttributeViewAssignment::class);
    }

    public function productAttributeValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }

    public function hierarchyNodeAssignments(): HasMany
    {
        return $this->hasMany(HierarchyNodeAttributeAssignment::class);
    }

    public function variantInheritanceRules(): HasMany
    {
        return $this->hasMany(VariantInheritanceRule::class);
    }
}

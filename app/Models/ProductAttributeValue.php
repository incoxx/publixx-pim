<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAttributeValue extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'product_id',
        'attribute_id',
        'value_string',
        'value_number',
        'value_date',
        'value_flag',
        'value_selection_id',
        'unit_id',
        'comparison_operator_id',
        'language',
        'multiplied_index',
        'is_inherited',
        'inherited_from_node_id',
        'inherited_from_product_id',
    ];

    protected function casts(): array
    {
        return [
            'value_number' => 'decimal:6',
            'value_date' => 'date',
            'value_flag' => 'boolean',
            'multiplied_index' => 'integer',
            'is_inherited' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    public function valueListEntry(): BelongsTo
    {
        return $this->belongsTo(ValueListEntry::class, 'value_selection_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function comparisonOperator(): BelongsTo
    {
        return $this->belongsTo(ComparisonOperator::class);
    }

    public function inheritedFromNode(): BelongsTo
    {
        return $this->belongsTo(HierarchyNode::class, 'inherited_from_node_id');
    }

    public function inheritedFromProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'inherited_from_product_id');
    }
}

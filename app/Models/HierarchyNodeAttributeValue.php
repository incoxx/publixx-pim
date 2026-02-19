<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HierarchyNodeAttributeValue extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'hierarchy_node_id',
        'attribute_id',
        'value_string',
        'value_number',
        'value_date',
        'value_flag',
        'value_selection_id',
        'unit_id',
        'language',
        'multiplied_index',
    ];

    protected function casts(): array
    {
        return [
            'value_number' => 'decimal:6',
            'value_date' => 'date',
            'value_flag' => 'boolean',
            'multiplied_index' => 'integer',
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

    public function valueListEntry(): BelongsTo
    {
        return $this->belongsTo(ValueListEntry::class, 'value_selection_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}

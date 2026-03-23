<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttributeMappingRule extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'source_hierarchy_id',
        'target_hierarchy_id',
        'name',
        'condition_attribute_id',
        'condition_operator',
        'condition_value',
        'actions',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'condition_value' => 'json',
            'actions' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function sourceHierarchy(): BelongsTo
    {
        return $this->belongsTo(Hierarchy::class, 'source_hierarchy_id');
    }

    public function targetHierarchy(): BelongsTo
    {
        return $this->belongsTo(Hierarchy::class, 'target_hierarchy_id');
    }

    public function conditionAttribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class, 'condition_attribute_id');
    }

    /**
     * Prüft ob die Bedingung für einen gegebenen Wert erfüllt ist.
     */
    public function evaluateCondition(mixed $value): bool
    {
        $compareValue = $this->condition_value;

        return match ($this->condition_operator) {
            '=' => $value == $compareValue,
            '!=' => $value != $compareValue,
            '>' => $value > $compareValue,
            '<' => $value < $compareValue,
            '>=' => $value >= $compareValue,
            '<=' => $value <= $compareValue,
            'in' => is_array($compareValue) && in_array($value, $compareValue),
            'not_in' => is_array($compareValue) && !in_array($value, $compareValue),
            'contains' => is_string($value) && is_string($compareValue) && str_contains($value, $compareValue),
            'is_empty' => $value === null || $value === '',
            'is_not_empty' => $value !== null && $value !== '',
            default => false,
        };
    }
}

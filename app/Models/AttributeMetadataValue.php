<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Metadatenwert einer Attributdefinition.
 *
 * Skalare Werte liegen in `value`, Mehrfachauswahl in `value_json`.
 */
class AttributeMetadataValue extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'attribute_id',
        'definition_id',
        'value',
        'value_json',
    ];

    /**
     * Die Definition wird immer mitgeladen — ohne sie ist ein Wert nicht
     * interpretierbar (Wert-Typ, technischer Name). Verhindert N+1 beim Mapping.
     */
    protected $with = ['definition'];

    protected function casts(): array
    {
        return [
            'value_json' => 'array',
        ];
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(AttributeMetadataDefinition::class, 'definition_id');
    }

    /**
     * Liefert den Wert typgerecht anhand der zugehörigen Definition.
     */
    public function resolvedValue(): mixed
    {
        $type = $this->definition?->value_type ?? 'text';

        if (in_array($type, AttributeMetadataDefinition::MULTI_VALUE_TYPES, true)) {
            return $this->value_json ?? [];
        }

        if ($this->value === null) {
            return null;
        }

        return match ($type) {
            'number' => is_numeric($this->value) ? $this->value + 0 : null,
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            default => $this->value,
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttributeMapping extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'source_attribute_id',
        'target_attribute_id',
        'output_hierarchy_id',
        'transform_type',
        'transform_config',
        'ai_suggested',
        'ai_confidence',
        'ai_confirmed_by',
        'ai_confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'transform_config' => 'array',
            'ai_suggested' => 'boolean',
            'ai_confidence' => 'decimal:2',
            'ai_confirmed_at' => 'datetime',
        ];
    }

    public function sourceAttribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class, 'source_attribute_id');
    }

    public function targetAttribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class, 'target_attribute_id');
    }

    public function outputHierarchy(): BelongsTo
    {
        return $this->belongsTo(Hierarchy::class, 'output_hierarchy_id');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ai_confirmed_by');
    }

}

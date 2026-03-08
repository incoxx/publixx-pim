<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HierarchyAttributeAssignment extends Model
{
    use HasUuids;

    protected $table = 'hierarchy_attribute_assignments';

    protected $fillable = [
        'hierarchy_id',
        'attribute_id',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function hierarchy(): BelongsTo
    {
        return $this->belongsTo(Hierarchy::class);
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }
}

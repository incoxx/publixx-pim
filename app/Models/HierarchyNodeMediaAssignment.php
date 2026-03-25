<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HierarchyNodeMediaAssignment extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'hierarchy_node_id',
        'media_id',
        'usage_type_id',
        'sort_order',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_primary' => 'boolean',
        ];
    }

    public function hierarchyNode(): BelongsTo
    {
        return $this->belongsTo(HierarchyNode::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function usageType(): BelongsTo
    {
        return $this->belongsTo(MediaUsageType::class, 'usage_type_id');
    }
}

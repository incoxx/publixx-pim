<?php

declare(strict_types=1);

namespace App\Models\Etim;

use App\Models\HierarchyNode;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EtimClassMapping extends Model
{
    use HasUuids;

    protected $fillable = [
        'hierarchy_node_id',
        'etim_class_id',
        'etim_version_id',
        'confidence',
        'mapping_source',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'float',
        ];
    }

    public function hierarchyNode(): BelongsTo
    {
        return $this->belongsTo(HierarchyNode::class, 'hierarchy_node_id');
    }

    public function etimClass(): BelongsTo
    {
        return $this->belongsTo(EtimClass::class, 'etim_class_id');
    }

    public function etimVersion(): BelongsTo
    {
        return $this->belongsTo(EtimVersion::class, 'etim_version_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

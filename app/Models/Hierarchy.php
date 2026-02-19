<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Hierarchy extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'technical_name',
        'name_de',
        'name_en',
        'name_json',
        'hierarchy_type',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'name_json' => 'array',
        ];
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(HierarchyNode::class);
    }

    public function rootNodes(): HasMany
    {
        return $this->hasMany(HierarchyNode::class)->whereNull('parent_node_id');
    }
}

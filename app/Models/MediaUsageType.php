<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MediaUsageType extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'technical_name',
        'name_de',
        'name_en',
        'name_json',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'name_json' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function mediaAssignments(): HasMany
    {
        return $this->hasMany(ProductMediaAssignment::class, 'usage_type_id');
    }
}

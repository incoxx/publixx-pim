<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductRelationType extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'technical_name',
        'name_de',
        'name_en',
        'name_json',
        'is_bidirectional',
    ];

    protected function casts(): array
    {
        return [
            'name_json' => 'array',
            'is_bidirectional' => 'boolean',
        ];
    }

    public function relations(): HasMany
    {
        return $this->hasMany(ProductRelation::class, 'relation_type_id');
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttributeView extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'technical_name',
        'name_de',
        'name_en',
        'name_json',
        'description',
        'sort_order',
        'is_write_protected',
    ];

    protected function casts(): array
    {
        return [
            'name_json' => 'array',
            'sort_order' => 'integer',
            'is_write_protected' => 'boolean',
        ];
    }

    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class, 'attribute_view_assignments')
            ->using(AttributeViewAssignment::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(AttributeViewAssignment::class);
    }
}

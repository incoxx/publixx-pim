<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasDeletionConstraints;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttributeFormattingRule extends Model
{
    use HasDeletionConstraints, HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'rule_type',
        'config',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
        ];
    }

    public function attributes(): HasMany
    {
        return $this->hasMany(Attribute::class, 'formatting_rule_id');
    }

    public function deletionConstraints(): array
    {
        return [
            'attributes' => 'Attribute',
        ];
    }
}

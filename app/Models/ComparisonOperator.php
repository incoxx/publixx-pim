<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasDeletionConstraints;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComparisonOperator extends Model
{
    use HasDeletionConstraints, HasFactory, HasUuids;

    protected $fillable = [
        'group_id',
        'technical_name',
        'symbol',
        'description_de',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(ComparisonOperatorGroup::class, 'group_id');
    }

    public function productAttributeValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }

    public function deletionConstraints(): array
    {
        return [
            'productAttributeValues' => 'Attributwerte',
        ];
    }
}

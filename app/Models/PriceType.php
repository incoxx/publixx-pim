<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasDeletionConstraints;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceType extends Model
{
    use HasDeletionConstraints, HasFactory, HasUuids;

    protected $fillable = [
        'technical_name',
        'name_de',
        'name_en',
        'name_json',
    ];

    protected function casts(): array
    {
        return [
            'name_json' => 'array',
        ];
    }

    public function productPrices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    public function deletionConstraints(): array
    {
        return [
            'productPrices' => 'Produktpreise',
        ];
    }
}

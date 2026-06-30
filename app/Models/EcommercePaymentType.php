<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EcommercePaymentType extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'technical_name',
        'name_de',
        'name_en',
        'description_de',
        'description_en',
        'icon',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(EcommerceOrder::class, 'payment_type_id');
    }
}

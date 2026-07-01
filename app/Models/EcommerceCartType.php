<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EcommerceCartType extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'technical_name',
        'name_de',
        'name_en',
        'name_json',
        'type',
        'price_type_id',
        'show_prices',
        'show_quantity',
        'show_total',
        'allow_checkout',
        'adapter_type',
        'adapter_config',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'name_json'      => 'array',
            'adapter_config' => 'array',
            'show_prices'    => 'boolean',
            'show_quantity'  => 'boolean',
            'show_total'     => 'boolean',
            'allow_checkout' => 'boolean',
            'is_active'      => 'boolean',
        ];
    }

    public function priceType(): BelongsTo
    {
        return $this->belongsTo(PriceType::class);
    }

    public function carts(): HasMany
    {
        return $this->hasMany(EcommerceCart::class, 'cart_type_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(EcommerceOrder::class, 'cart_type_id');
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EcommerceCartItem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'cart_id',
        'product_id',
        'quantity',
        'unit_price',
        'currency',
        'price_snapshot',
        'note',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity'       => 'decimal:3',
            'unit_price'     => 'decimal:4',
            'price_snapshot' => 'array',
            'added_at'       => 'datetime',
        ];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(EcommerceCart::class, 'cart_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // Aktueller Zeilenpreis (Menge × Einzelpreis)
    public function getLinePriceAttribute(): ?float
    {
        if ($this->unit_price === null) {
            return null;
        }

        return round((float) $this->unit_price * (float) $this->quantity, 2);
    }
}

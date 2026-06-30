<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EcommerceCart extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'cart_type_id',
        'session_id',
        'user_id',
        'status',
        'expires_at',
        'submitted_at',
        'billing_address',
        'shipping_address',
        'payment_type_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'billing_address'  => 'array',
            'shipping_address' => 'array',
            'expires_at'       => 'datetime',
            'submitted_at'     => 'datetime',
        ];
    }

    public function cartType(): BelongsTo
    {
        return $this->belongsTo(EcommerceCartType::class, 'cart_type_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function paymentType(): BelongsTo
    {
        return $this->belongsTo(EcommercePaymentType::class, 'payment_type_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(EcommerceCartItem::class, 'cart_id')->orderBy('sort_order')->orderBy('added_at');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(EcommerceOrder::class, 'cart_id');
    }

    // Nur aktive, nicht abgelaufene Warenkörbe
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }
}

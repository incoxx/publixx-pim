<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EcommerceOrder extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'order_number',
        'cart_id',
        'cart_type_id',
        'session_id',
        'user_id',
        'status',
        'items_snapshot',
        'billing_address',
        'shipping_address',
        'payment_type_id',
        'total_amount',
        'currency',
        'customer_notes',
        'internal_notes',
        'adapter_log',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'items_snapshot'  => 'array',
            'billing_address' => 'array',
            'shipping_address' => 'array',
            'adapter_log'     => 'array',
            'total_amount'    => 'decimal:2',
            'submitted_at'    => 'datetime',
        ];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(EcommerceCart::class, 'cart_id');
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
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CollectionItem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'collection_id',
        'product_id',
        'position',
        'quantity',
        'unit_id',
        'snapshot',
        'snapshot_at',
        'hidden_attribute_ids',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'quantity' => 'decimal:6',
            'snapshot' => 'array',
            'snapshot_at' => 'datetime',
            'hidden_attribute_ids' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (self $item) {
            CollectionAttributeValue::where('owner_type', 'collection_item')
                ->where('owner_id', $item->id)
                ->delete();
        });
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function attributeValues(): HasMany
    {
        return $this->hasMany(CollectionAttributeValue::class, 'owner_id')
            ->where('owner_type', 'collection_item');
    }
}

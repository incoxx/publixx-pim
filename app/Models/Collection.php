<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Collection extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'collection_type_id',
        'organization_id',
        'organization_snapshot',
        'reference',
        'name',
        'status',
        'language',
        'currency',
        'valid_from',
        'valid_until',
        'frozen_at',
        'source_channel',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'organization_snapshot' => 'array',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'frozen_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // owner_id in collection_attribute_values traegt keine echte FK (polymorph ohne
        // Zieltabelle) -- Cascade-Delete muss daher hier explizit erfolgen.
        static::deleting(function (self $collection) {
            CollectionAttributeValue::where('owner_type', 'collection')
                ->where('owner_id', $collection->id)
                ->delete();
        });
    }

    public function collectionType(): BelongsTo
    {
        return $this->belongsTo(CollectionType::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CollectionItem::class)->orderBy('position');
    }

    public function attributeValues(): HasMany
    {
        return $this->hasMany(CollectionAttributeValue::class, 'owner_id')
            ->where('owner_type', 'collection');
    }
}

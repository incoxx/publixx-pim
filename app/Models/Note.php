<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Note extends Model
{
    use HasUuids;

    protected $fillable = [
        'title',
        'body',
        'color',
        'pinned',
        'is_shared',
        'product_id',
        'created_by',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'pinned' => 'boolean',
            'is_shared' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Eigene + geteilte Notizen.
     */
    public function scopeVisibleTo($query, string $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('created_by', $userId)
              ->orWhere('is_shared', true);
        });
    }

    public function scopeForUser($query, string $userId)
    {
        return $query->where('created_by', $userId);
    }
}

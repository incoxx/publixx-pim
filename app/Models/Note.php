<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Note extends Model
{
    use HasUuids, SoftDeletes;

    public const TYPES = ['task', 'hint', 'warning', 'error'];
    public const STATUSES = ['open', 'done'];

    protected $fillable = [
        'title',
        'body',
        'color',
        'type',
        'status',
        'pinned',
        'is_shared',
        'product_id',
        'created_by',
        'sort_order',
        'resolved_at',
        'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'pinned'      => 'boolean',
            'is_shared'   => 'boolean',
            'sort_order'  => 'integer',
            'resolved_at' => 'datetime',
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

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(NoteComment::class)->orderBy('created_at');
    }

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

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }
}

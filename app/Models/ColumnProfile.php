<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ColumnProfile extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'user_id',
        'is_shared',
        'context',
        'visible_keys',
    ];

    protected function casts(): array
    {
        return [
            'is_shared' => 'boolean',
            'visible_keys' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeVisibleTo($query, string $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)
              ->orWhere('is_shared', true);
        });
    }
}

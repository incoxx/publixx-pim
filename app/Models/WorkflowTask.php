<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowTask extends Model
{
    use HasUuids;

    protected $fillable = [
        'product_id',
        'title',
        'status',
        'assigned_to',
        'created_by',
        'closed_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'closed_at' => 'datetime',
        ];
    }

    // --- Relationships ---

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // --- Scopes ---

    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('assigned_to', $userId);
    }
}

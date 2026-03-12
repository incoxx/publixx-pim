<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessLink extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'token',
        'name',
        'role_id',
        'created_by',
        'expires_at',
        'used_at',
        'user_id',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function createdUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isValid(): bool
    {
        return ! $this->isUsed() && ! $this->isExpired();
    }

    public function scopeUnused($query)
    {
        return $query->whereNull('used_at');
    }

    public function scopeUsed($query)
    {
        return $query->whereNotNull('used_at');
    }

    public function scopeExpired($query)
    {
        return $query->whereNull('used_at')->where('expires_at', '<', now());
    }

    public function scopeOpen($query)
    {
        return $query->whereNull('used_at')->where('expires_at', '>=', now());
    }
}

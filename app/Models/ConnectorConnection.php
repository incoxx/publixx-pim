<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasDeletionConstraints;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConnectorConnection extends Model
{
    use HasDeletionConstraints, HasUuids;

    protected $fillable = [
        'connector_type',
        'name',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'settings',
        'is_active',
        'connected_by',
    ];

    protected function casts(): array
    {
        return [
            'access_token'     => 'encrypted',
            'refresh_token'    => 'encrypted',
            'token_expires_at' => 'datetime',
            'settings'         => 'array',
            'is_active'        => 'boolean',
        ];
    }

    public function connectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by');
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(ConnectorSyncLog::class);
    }

    public function isTokenExpired(): bool
    {
        if (! $this->token_expires_at) {
            return false; // API-key connections have no expiry
        }

        return $this->token_expires_at->isPast();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('connector_type', $type);
    }

    public function deletionConstraints(): array
    {
        return [
            'syncLogs' => 'Sync-Protokolle',
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class ApiClient extends Model
{
    use HasApiTokens, HasFactory, HasUuids;

    /**
     * Verfügbare Scopes für API-Clients.
     */
    public const AVAILABLE_SCOPES = [
        'products:read',
        'products:write',
        'media:read',
        'media:write',
        'categories:read',
        'categories:write',
        'prices:read',
        'prices:write',
        'attributes:read',
        'attributes:write',
    ];

    protected $fillable = [
        'name',
        'client_id',
        'client_secret',
        'scopes',
        'is_active',
        'rate_limit',
        'last_used_at',
        'expires_at',
        'created_by',
    ];

    protected $hidden = [
        'client_secret',
    ];

    protected function casts(): array
    {
        return [
            'scopes'       => 'array',
            'is_active'    => 'boolean',
            'last_used_at' => 'datetime',
            'expires_at'   => 'datetime',
        ];
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Generiert neue Credentials (client_id + client_secret).
     * Das Secret wird nur einmal im Klartext zurückgegeben.
     *
     * @return array{client_id: string, client_secret: string}
     */
    public static function generateCredentials(): array
    {
        return [
            'client_id'     => Str::random(32),
            'client_secret' => Str::random(64),
        ];
    }

    /**
     * Prüft ob der Client einen bestimmten Scope hat.
     */
    public function hasScope(string $scope): bool
    {
        $scopes = $this->scopes ?? [];

        // Wildcard-Scope: "products:*" erlaubt "products:read" und "products:write"
        $namespace = explode(':', $scope)[0] ?? '';
        if (in_array("{$namespace}:*", $scopes, true)) {
            return true;
        }

        return in_array($scope, $scopes, true);
    }

    /**
     * Prüft ob der Client abgelaufen ist.
     */
    public function isExpired(): bool
    {
        if (! $this->expires_at) {
            return false;
        }

        return $this->expires_at->isPast();
    }

    /**
     * Prüft ob der Client nutzbar ist (aktiv und nicht abgelaufen).
     */
    public function isUsable(): bool
    {
        return $this->is_active && ! $this->isExpired();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasDeletionConstraints;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApiTemplate extends Model
{
    use HasDeletionConstraints, HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'description',
        'search_profile_id',
        'template_json',
        'direction',
        'output_format',
        'language',
        'user_id',
        'is_shared',
        'is_active',
        'is_mcp_enabled',
        'slug',
        'auth_type',
        'api_key',
        'rate_limit',
    ];

    protected function casts(): array
    {
        return [
            'template_json' => 'array',
            'is_shared' => 'boolean',
            'is_active' => 'boolean',
            'is_mcp_enabled' => 'boolean',
            'rate_limit' => 'integer',
        ];
    }

    public function searchProfile(): BelongsTo
    {
        return $this->belongsTo(SearchProfile::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(ApiTemplateAccessLog::class);
    }

    public function scopeVisibleTo($query, string $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)
              ->orWhere('is_shared', true);
        });
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function deletionConstraints(): array
    {
        return [
            'accessLogs' => 'Zugriffsprotokolle',
        ];
    }
}

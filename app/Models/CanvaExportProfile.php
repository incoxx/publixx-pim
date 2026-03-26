<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CanvaExportProfile extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'user_id',
        'is_shared',
        'connector_connection_id',
        'search_profile_id',
        'product_ids',
        'attribute_ids',
        'languages',
        'include_prices',
        'include_media',
        'brand_template_id',
    ];

    protected function casts(): array
    {
        return [
            'is_shared' => 'boolean',
            'include_prices' => 'boolean',
            'include_media' => 'boolean',
            'product_ids' => 'array',
            'attribute_ids' => 'array',
            'languages' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function connectorConnection(): BelongsTo
    {
        return $this->belongsTo(ConnectorConnection::class);
    }

    public function searchProfile(): BelongsTo
    {
        return $this->belongsTo(SearchProfile::class);
    }

    public function scopeVisibleTo($query, string $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)
              ->orWhere('is_shared', true);
        });
    }
}

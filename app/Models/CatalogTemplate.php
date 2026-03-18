<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogTemplate extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'description',
        'slug',
        'html_template',
        'css_variables',
        'settings',
        'thumbnail_path',
        'is_shared',
        'is_active',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'css_variables' => 'array',
            'settings' => 'array',
            'is_shared' => 'boolean',
            'is_active' => 'boolean',
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

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

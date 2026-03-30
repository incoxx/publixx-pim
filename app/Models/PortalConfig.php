<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortalConfig extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'html_template',
        'catalog_template_id',
        'filter_steps',
        'branding',
        'css_variables',
        'custom_css',
        'default_locale',
        'is_active',
        'is_shared',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'filter_steps' => 'array',
            'branding' => 'array',
            'css_variables' => 'array',
            'is_active' => 'boolean',
            'is_shared' => 'boolean',
        ];
    }

    public function catalogTemplate(): BelongsTo
    {
        return $this->belongsTo(CatalogTemplate::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeVisibleTo($query, ?string $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('is_shared', true);
            if ($userId) {
                $q->orWhere('user_id', $userId);
            }
        });
    }
}

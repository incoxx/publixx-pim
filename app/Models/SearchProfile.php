<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasDeletionConstraints;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SearchProfile extends Model
{
    use HasDeletionConstraints, HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'user_id',
        'is_shared',
        'search_text',
        'search_mode',
        'status_filter',
        'category_ids',
        'attribute_filters',
        'attribute_filter_groups',
        'include_descendants',
        'sort_field',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_shared' => 'boolean',
            'category_ids' => 'array',
            'attribute_filters' => 'array',
            'attribute_filter_groups' => 'array',
            'include_descendants' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function exportProfiles(): HasMany
    {
        return $this->hasMany(ExportProfile::class);
    }

    public function reportTemplates(): HasMany
    {
        return $this->hasMany(ReportTemplate::class);
    }

    public function scopeVisibleTo($query, string $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)
              ->orWhere('is_shared', true);
        });
    }

    public function deletionConstraints(): array
    {
        return [
            'exportProfiles'  => 'Exportprofile',
            'reportTemplates' => 'Report-Vorlagen',
        ];
    }
}

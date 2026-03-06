<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExportJob extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'description',
        'export_profile_id',
        'search_profile_id',
        'format',
        'sections',
        'filters',
        'cron_expression',
        'is_active',
        'last_status',
        'last_run_at',
        'next_run_at',
        'last_duration_seconds',
        'last_output_path',
        'last_result',
        'last_error',
        'user_id',
        'is_shared',
    ];

    protected function casts(): array
    {
        return [
            'sections' => 'array',
            'filters' => 'array',
            'is_active' => 'boolean',
            'is_shared' => 'boolean',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
            'last_result' => 'array',
        ];
    }

    public function exportProfile(): BelongsTo
    {
        return $this->belongsTo(ExportProfile::class);
    }

    public function searchProfile(): BelongsTo
    {
        return $this->belongsTo(SearchProfile::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeScheduled($query)
    {
        return $query->active()->whereNotNull('cron_expression');
    }

    public function scopeVisibleTo($query, string $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)
              ->orWhere('is_shared', true);
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportJob extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'report_template_id',
        'search_profile_id',
        'format',
        'last_status',
        'last_run_at',
        'last_duration_seconds',
        'last_output_path',
        'last_result',
        'last_error',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'last_run_at' => 'datetime',
            'last_result' => 'array',
        ];
    }

    public function reportTemplate(): BelongsTo
    {
        return $this->belongsTo(ReportTemplate::class);
    }

    public function searchProfile(): BelongsTo
    {
        return $this->belongsTo(SearchProfile::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExportJobLog extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'export_job_id',
        'status',
        'started_at',
        'finished_at',
        'duration_seconds',
        'output_path',
        'file_size_bytes',
        'delivery_status',
        'delivery_error',
        'error',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function exportJob(): BelongsTo
    {
        return $this->belongsTo(ExportJob::class);
    }
}

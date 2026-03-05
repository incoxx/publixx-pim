<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportLog extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'import_job_id',
        'level',
        'phase',
        'sheet',
        'row',
        'column',
        'message',
        'context',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'row' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function importJob(): BelongsTo
    {
        return $this->belongsTo(ImportJob::class);
    }
}

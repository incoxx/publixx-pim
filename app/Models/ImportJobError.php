<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportJobError extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'import_job_id',
        'sheet',
        'row',
        'column',
        'field',
        'value',
        'error',
        'suggestion',
    ];

    protected function casts(): array
    {
        return [
            'row' => 'integer',
        ];
    }

    public function importJob(): BelongsTo
    {
        return $this->belongsTo(ImportJob::class);
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportJob extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'file_name',
        'file_path',
        'status',
        'sheets_found',
        'summary',
        'result',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'sheets_found' => 'array',
            'summary' => 'array',
            'result' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function errors(): HasMany
    {
        return $this->hasMany(ImportJobError::class);
    }
}

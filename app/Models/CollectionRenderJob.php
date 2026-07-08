<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectionRenderJob extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'collection_id',
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

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

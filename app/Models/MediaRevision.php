<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaRevision extends Model
{
    use HasUuids;

    protected $fillable = [
        'media_id',
        'revision_number',
        'file_name',
        'file_path',
        'original_file_name',
        'mime_type',
        'file_size',
        'width',
        'height',
        'replaced_by',
        'replaced_at',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'revision_number' => 'integer',
            'file_size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'replaced_at' => 'datetime',
        ];
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function replacedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replaced_by');
    }
}

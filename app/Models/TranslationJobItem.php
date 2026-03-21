<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TranslationJobItem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'translation_job_id',
        'entity_type',
        'entity_id',
        'attribute_id',
        'source_text',
        'translated_text',
        'status',
        'error_message',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(TranslationJob::class, 'translation_job_id');
    }
}

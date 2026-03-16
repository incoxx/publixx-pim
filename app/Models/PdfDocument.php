<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PdfDocument extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'pdf_documents';

    protected $fillable = [
        'media_id',
        'original_url',
        'status',
        'page_count',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'page_count' => 'integer',
        ];
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(PdfPage::class)->orderBy('page_number');
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PdfTemplate extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'description',
        'template_json',
        'page_orientation',
        'page_size',
        'user_id',
        'is_shared',
    ];

    protected function casts(): array
    {
        return [
            'template_json' => 'array',
            'is_shared' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeVisibleTo($query, string $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)
              ->orWhere('is_shared', true);
        });
    }
}

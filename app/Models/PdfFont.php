<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PdfFont extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'family_name',
        'file_regular',
        'file_bold',
        'file_italic',
        'file_bold_italic',
        'user_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Absoluter Pfad zu einer Font-Variante.
     */
    public function getAbsolutePath(string $variant): ?string
    {
        $field = 'file_' . $variant;
        $filename = $this->$field;

        if (!$filename) {
            return null;
        }

        return storage_path('fonts/custom/' . $filename);
    }

    /**
     * Alle vorhandenen Varianten als Array.
     */
    public function getVariants(): array
    {
        $variants = [];
        foreach (['regular', 'bold', 'italic', 'bold_italic'] as $variant) {
            $field = 'file_' . $variant;
            if ($this->$field) {
                $variants[] = $variant;
            }
        }
        return $variants;
    }
}

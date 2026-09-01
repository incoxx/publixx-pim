<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasDeletionConstraints;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Mehrsprachiges Stichwort, das Produkten und Medien zugeordnet werden kann.
 *
 * Namensschema name_de/name_en/name_json nach docs/architecture/05-i18n.md
 * (Level 1: System Labels).
 */
class Tag extends Model
{
    use HasDeletionConstraints, HasFactory, HasUuids;

    protected $fillable = [
        'technical_name',
        'name_de',
        'name_en',
        'name_json',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'name_json' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_tag')
            ->withPivot('sort_order')
            ->withTimestamps();
    }

    public function media(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'media_tag')
            ->withPivot('sort_order')
            ->withTimestamps();
    }

    public function deletionConstraints(): array
    {
        return [
            'products' => 'Produkte',
            'media' => 'Medien',
        ];
    }
}

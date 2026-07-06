<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductMediaAssignment extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'product_id',
        'media_id',
        'motif_id',
        'usage_type_id',
        'sort_order',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_primary' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /**
     * Alternative zu media_id: das gesamte Motiv ist zugeordnet, nicht eine
     * einzelne Rendition. Welche Rendition ausgeliefert wird, entscheidet
     * (aktuell noch) die Anwendung, die die Zuordnung konsumiert — die
     * Export-Pipeline-Anbindung folgt in einem späteren Schritt.
     */
    public function motif(): BelongsTo
    {
        return $this->belongsTo(MediaMotif::class, 'motif_id');
    }

    public function usageType(): BelongsTo
    {
        return $this->belongsTo(MediaUsageType::class, 'usage_type_id');
    }
}

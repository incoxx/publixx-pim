<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Legt pro Elternprodukt fest, welche Attribute die Varianten dieses Produkts
 * unterscheiden (Merkmalsachsen). Rein datengetrieben — beliebige Attribute,
 * beliebige Anzahl, keine feste Zuordnung zu einem Produkttyp oder Beispiel.
 */
class ProductVariantAxis extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'product_id',
        'attribute_id',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    /**
     * Das Elternprodukt, dessen Varianten über diese Achse unterschieden werden.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vererbungsregel eines virtuellen Produkts für ein Attribut.
 *
 * @see \App\Services\VirtualProduct\VirtualProductAttributeSyncService
 */
class VirtualProductInheritanceRule extends Model
{
    use HasFactory, HasUuids;

    public const CONFLICT_KEEP_LOCAL = 'keep_local';
    public const CONFLICT_FORCE_OVERRIDE = 'force_override';

    protected $fillable = [
        'virtual_product_id',
        'attribute_id',
        'conflict_mode',
    ];

    public function virtualProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'virtual_product_id');
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }
}

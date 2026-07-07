<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Medien-Vererbungsregel eines virtuellen Produkts für einen Usage-Type.
 *
 * @see \App\Services\VirtualProduct\VirtualProductMediaSyncService
 */
class VirtualProductMediaInheritanceRule extends Model
{
    use HasFactory, HasUuids;

    public const CONFLICT_KEEP_LOCAL = 'keep_local';
    public const CONFLICT_FORCE_OVERRIDE = 'force_override';

    protected $fillable = [
        'virtual_product_id',
        'usage_type_id',
        'conflict_mode',
    ];

    public function virtualProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'virtual_product_id');
    }

    public function usageType(): BelongsTo
    {
        return $this->belongsTo(MediaUsageType::class, 'usage_type_id');
    }
}

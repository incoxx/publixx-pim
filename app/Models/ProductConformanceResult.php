<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Letztes Ergebnis der Konformitätsprüfung eines Produkts gegen sein Referenz-Profil.
 */
class ProductConformanceResult extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_PASS = 'pass';
    public const STATUS_WARNING = 'warning';
    public const STATUS_FAIL = 'fail';

    protected $fillable = [
        'product_id',
        'profile_id',
        'profile_version',
        'status',
        'score',
        'deviation_count',
        'error_count',
        'warning_count',
        'info_count',
        'deviations',
        'trigger',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'profile_version' => 'integer',
            'score' => 'integer',
            'deviation_count' => 'integer',
            'error_count' => 'integer',
            'warning_count' => 'integer',
            'info_count' => 'integer',
            'deviations' => 'array',
            'checked_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(ProductReferenceProfile::class, 'profile_id');
    }
}

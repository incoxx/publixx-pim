<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Definition eines virtuellen Produkts (dynamischer Cluster).
 *
 * Hält fest, aus welcher Quelle die Mitglieder des Klammer-Produkts
 * (product_id) aufgelöst werden. Die Auflösung selbst übernimmt der
 * VirtualProductResolver — hier wird nur die Definition persistiert.
 */
class VirtualProductDefinition extends Model
{
    use HasFactory, HasUuids;

    public const SOURCE_SEARCH_PROFILE = 'search_profile';
    public const SOURCE_PQL = 'pql';
    public const SOURCE_MANUAL = 'manual';

    protected $fillable = [
        'product_id',
        'source_type',
        'search_profile_id',
        'pql_query',
        'manual_product_ids',
        'relation_type_id',
        'language',
        'max_members',
    ];

    protected function casts(): array
    {
        return [
            'manual_product_ids' => 'array',
            'max_members' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function searchProfile(): BelongsTo
    {
        return $this->belongsTo(SearchProfile::class);
    }

    public function relationType(): BelongsTo
    {
        return $this->belongsTo(ProductRelationType::class, 'relation_type_id');
    }
}

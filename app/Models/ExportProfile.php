<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExportProfile extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'user_id',
        'is_shared',
        'search_profile_id',
        'include_products',
        'include_attributes',
        'include_hierarchies',
        'include_prices',
        'include_relations',
        'include_media',
        'include_variants',
        'attribute_ids',
        'languages',
        'format',
        'file_name_template',
    ];

    protected function casts(): array
    {
        return [
            'is_shared' => 'boolean',
            'include_products' => 'boolean',
            'include_attributes' => 'boolean',
            'include_hierarchies' => 'boolean',
            'include_prices' => 'boolean',
            'include_relations' => 'boolean',
            'include_media' => 'boolean',
            'include_variants' => 'boolean',
            'attribute_ids' => 'array',
            'languages' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function searchProfile(): BelongsTo
    {
        return $this->belongsTo(SearchProfile::class);
    }

    public function scopeVisibleTo($query, string $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)
              ->orWhere('is_shared', true);
        });
    }
}

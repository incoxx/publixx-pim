<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Duenne Render-Projektion eines Empfaengers -- KEIN CRM-Stammsatz.
 * System of Record bleibt ERP/CRM (external_ref).
 */
class Organization extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'external_ref',
        'name',
        'language',
        'address_block',
        'logo_media_id',
        'price_list_ref',
        'currency',
    ];

    public function logoMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'logo_media_id');
    }

    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class);
    }
}

<?php

declare(strict_types=1);

namespace App\Models\Etim;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EtimUnit extends Model
{
    use HasUuids;

    protected $fillable = [
        'etim_version_id',
        'code',
        'name_de',
        'name_en',
        'abbreviation',
        'description',
    ];

    public function etimVersion(): BelongsTo
    {
        return $this->belongsTo(EtimVersion::class, 'etim_version_id');
    }

    public function features(): HasMany
    {
        return $this->hasMany(EtimFeature::class, 'etim_unit_id');
    }
}

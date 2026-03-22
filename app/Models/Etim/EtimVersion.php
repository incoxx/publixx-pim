<?php

declare(strict_types=1);

namespace App\Models\Etim;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EtimVersion extends Model
{
    use HasUuids;

    protected $fillable = [
        'version',
        'name',
        'is_active',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'imported_at' => 'datetime',
        ];
    }

    public function groups(): HasMany
    {
        return $this->hasMany(EtimGroup::class, 'etim_version_id');
    }

    public function classes(): HasMany
    {
        return $this->hasMany(EtimClass::class, 'etim_version_id');
    }

    public function features(): HasMany
    {
        return $this->hasMany(EtimFeature::class, 'etim_version_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(EtimValue::class, 'etim_version_id');
    }

    public function units(): HasMany
    {
        return $this->hasMany(EtimUnit::class, 'etim_version_id');
    }

    public function classMappings(): HasMany
    {
        return $this->hasMany(EtimClassMapping::class, 'etim_version_id');
    }

    public function featureMappings(): HasMany
    {
        return $this->hasMany(EtimFeatureMapping::class, 'etim_version_id');
    }
}

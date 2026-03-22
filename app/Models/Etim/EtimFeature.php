<?php

declare(strict_types=1);

namespace App\Models\Etim;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EtimFeature extends Model
{
    use HasUuids;

    protected $fillable = [
        'etim_version_id',
        'code',
        'name_de',
        'name_en',
        'name_json',
        'description_de',
        'description_en',
        'data_type',
        'etim_unit_id',
    ];

    protected function casts(): array
    {
        return [
            'name_json' => 'array',
        ];
    }

    public function etimVersion(): BelongsTo
    {
        return $this->belongsTo(EtimVersion::class, 'etim_version_id');
    }

    public function etimUnit(): BelongsTo
    {
        return $this->belongsTo(EtimUnit::class, 'etim_unit_id');
    }

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(EtimClass::class, 'etim_class_features', 'etim_feature_id', 'etim_class_id')
            ->withPivot('sort_order', 'is_mandatory');
    }

    public function allowedValues(): BelongsToMany
    {
        return $this->belongsToMany(EtimValue::class, 'etim_feature_values', 'etim_feature_id', 'etim_value_id')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    public function featureMappings(): HasMany
    {
        return $this->hasMany(EtimFeatureMapping::class, 'etim_feature_id');
    }
}

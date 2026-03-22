<?php

declare(strict_types=1);

namespace App\Models\Etim;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EtimClass extends Model
{
    use HasUuids;

    protected $fillable = [
        'etim_version_id',
        'etim_group_id',
        'code',
        'name_de',
        'name_en',
        'name_json',
        'description_de',
        'description_en',
        'sort_order',
        'status',
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

    public function etimGroup(): BelongsTo
    {
        return $this->belongsTo(EtimGroup::class, 'etim_group_id');
    }

    public function classFeatures(): HasMany
    {
        return $this->hasMany(EtimClassFeature::class, 'etim_class_id');
    }

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(EtimFeature::class, 'etim_class_features', 'etim_class_id', 'etim_feature_id')
            ->withPivot('sort_order', 'is_mandatory')
            ->orderByPivot('sort_order');
    }

    public function classMappings(): HasMany
    {
        return $this->hasMany(EtimClassMapping::class, 'etim_class_id');
    }
}

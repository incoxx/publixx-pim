<?php

declare(strict_types=1);

namespace App\Models\Etim;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class EtimValue extends Model
{
    use HasUuids;

    protected $fillable = [
        'etim_version_id',
        'code',
        'name_de',
        'name_en',
        'name_json',
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

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(EtimFeature::class, 'etim_feature_values', 'etim_value_id', 'etim_feature_id');
    }
}

<?php

declare(strict_types=1);

namespace App\Models\Etim;

use App\Models\Attribute;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EtimFeatureMapping extends Model
{
    use HasUuids;

    protected $fillable = [
        'attribute_id',
        'etim_feature_id',
        'etim_version_id',
        'confidence',
        'mapping_source',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'float',
        ];
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class, 'attribute_id');
    }

    public function etimFeature(): BelongsTo
    {
        return $this->belongsTo(EtimFeature::class, 'etim_feature_id');
    }

    public function etimVersion(): BelongsTo
    {
        return $this->belongsTo(EtimVersion::class, 'etim_version_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

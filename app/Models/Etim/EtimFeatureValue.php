<?php

declare(strict_types=1);

namespace App\Models\Etim;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EtimFeatureValue extends Model
{
    use HasUuids;

    protected $fillable = [
        'etim_feature_id',
        'etim_value_id',
        'sort_order',
    ];

    public function etimFeature(): BelongsTo
    {
        return $this->belongsTo(EtimFeature::class, 'etim_feature_id');
    }

    public function etimValue(): BelongsTo
    {
        return $this->belongsTo(EtimValue::class, 'etim_value_id');
    }
}

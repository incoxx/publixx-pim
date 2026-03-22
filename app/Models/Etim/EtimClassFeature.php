<?php

declare(strict_types=1);

namespace App\Models\Etim;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EtimClassFeature extends Model
{
    use HasUuids;

    protected $fillable = [
        'etim_class_id',
        'etim_feature_id',
        'sort_order',
        'is_mandatory',
    ];

    protected function casts(): array
    {
        return [
            'is_mandatory' => 'boolean',
        ];
    }

    public function etimClass(): BelongsTo
    {
        return $this->belongsTo(EtimClass::class, 'etim_class_id');
    }

    public function etimFeature(): BelongsTo
    {
        return $this->belongsTo(EtimFeature::class, 'etim_feature_id');
    }
}

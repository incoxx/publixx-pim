<?php

declare(strict_types=1);

namespace App\Models\Etim;

use App\Models\Unit;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EtimUnitMapping extends Model
{
    use HasUuids;

    protected $fillable = [
        'unit_id',
        'etim_unit_id',
        'etim_version_id',
        'confidence',
        'mapping_source',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'float',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function etimUnit(): BelongsTo
    {
        return $this->belongsTo(EtimUnit::class, 'etim_unit_id');
    }

    public function etimVersion(): BelongsTo
    {
        return $this->belongsTo(EtimVersion::class, 'etim_version_id');
    }
}

<?php

declare(strict_types=1);

namespace App\Models\Etim;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EtimGroup extends Model
{
    use HasUuids;

    protected $fillable = [
        'etim_version_id',
        'code',
        'name_de',
        'name_en',
        'name_json',
        'sort_order',
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

    public function classes(): HasMany
    {
        return $this->hasMany(EtimClass::class, 'etim_group_id');
    }
}

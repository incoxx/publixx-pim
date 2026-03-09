<?php

declare(strict_types=1);

namespace Tms\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TmsUsage extends Model
{
    use HasUuids;

    protected $table = 'tms_usages';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'tms_unit_id',
        'entity_type',
        'entity_id',
        'field_name',
        'entity_label',
        'first_seen_at',
        'last_seen_at',
    ];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(TmsUnit::class, 'tms_unit_id');
    }
}

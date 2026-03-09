<?php

declare(strict_types=1);

namespace Tms\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TmsMtLog extends Model
{
    use HasUuids;

    protected $table = 'tms_mt_log';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'provider',
        'source_lang',
        'target_lang',
        'char_count',
        'cost_estimate',
        'tms_unit_id',
        'created_at',
    ];

    protected $casts = [
        'char_count' => 'integer',
        'cost_estimate' => 'decimal:4',
        'created_at' => 'datetime',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(TmsUnit::class, 'tms_unit_id');
    }
}

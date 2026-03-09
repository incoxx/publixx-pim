<?php

declare(strict_types=1);

namespace Tms\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TmsUnit extends Model
{
    use HasUuids;

    protected $table = 'tms_units';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'source_lang',
        'source_text',
        'text_hash',
        'domain',
        'char_count',
    ];

    protected $casts = [
        'char_count' => 'integer',
    ];

    public function translations(): HasMany
    {
        return $this->hasMany(TmsTranslation::class, 'tms_unit_id');
    }

    public function usages(): HasMany
    {
        return $this->hasMany(TmsUsage::class, 'tms_unit_id');
    }

    public function mtLogs(): HasMany
    {
        return $this->hasMany(TmsMtLog::class, 'tms_unit_id');
    }
}

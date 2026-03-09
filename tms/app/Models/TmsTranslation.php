<?php

declare(strict_types=1);

namespace Tms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TmsTranslation extends Model
{
    protected $table = 'tms_translations';
    public $incrementing = false;

    protected $primaryKey = ['tms_unit_id', 'target_lang'];

    protected $fillable = [
        'tms_unit_id',
        'target_lang',
        'translation',
        'provider',
        'status',
        'confidence',
        'reviewed_at',
    ];

    protected $casts = [
        'confidence' => 'decimal:2',
        'reviewed_at' => 'datetime',
    ];

    /**
     * Override getKey for composite primary key.
     */
    public function getKey(): string
    {
        return $this->tms_unit_id . '|' . $this->target_lang;
    }

    /**
     * Override setKeysForSaveQuery for composite PK support.
     */
    protected function setKeysForSaveQuery($query)
    {
        return $query
            ->where('tms_unit_id', $this->tms_unit_id)
            ->where('target_lang', $this->target_lang);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(TmsUnit::class, 'tms_unit_id');
    }
}

<?php

declare(strict_types=1);

namespace App\Models\Etim;

use App\Models\ValueListEntry;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EtimValueMapping extends Model
{
    use HasUuids;

    protected $fillable = [
        'value_list_entry_id',
        'etim_value_id',
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

    public function valueListEntry(): BelongsTo
    {
        return $this->belongsTo(ValueListEntry::class, 'value_list_entry_id');
    }

    public function etimValue(): BelongsTo
    {
        return $this->belongsTo(EtimValue::class, 'etim_value_id');
    }

    public function etimVersion(): BelongsTo
    {
        return $this->belongsTo(EtimVersion::class, 'etim_version_id');
    }
}

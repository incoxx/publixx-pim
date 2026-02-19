<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ValueListEntry extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'value_list_id',
        'parent_entry_id',
        'technical_name',
        'display_value_de',
        'display_value_en',
        'display_value_json',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'display_value_json' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function valueList(): BelongsTo
    {
        return $this->belongsTo(ValueList::class);
    }

    public function parentEntry(): BelongsTo
    {
        return $this->belongsTo(ValueListEntry::class, 'parent_entry_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ValueListEntry::class, 'parent_entry_id');
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DictionaryEntry extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'category',
        'short_text_de',
        'short_text_en',
        'long_text_de',
        'long_text_en',
        'status',
    ];

    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class, 'attribute_dictionary_entry')
            ->withPivot('sort_order')
            ->withTimestamps();
    }
}

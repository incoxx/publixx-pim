<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Block-Instanz auf einer Content-Seite (analog ProductAttributeValue).
 * Werte liegen in values_json je Sprache: { "de": {...}, "en": {...} },
 * sprachneutrale Felder unter "_".
 */
class ContentSection extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'content_page_id',
        'section_type_id',
        'parent_section_id',
        'sort_order',
        'is_visible',
        'values_json',
        'settings_json',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_visible' => 'boolean',
            'values_json' => 'array',
            'settings_json' => 'array',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(ContentPage::class, 'content_page_id');
    }

    public function sectionType(): BelongsTo
    {
        return $this->belongsTo(SectionType::class);
    }

    public function parentSection(): BelongsTo
    {
        return $this->belongsTo(ContentSection::class, 'parent_section_id');
    }

    public function childSections(): HasMany
    {
        return $this->hasMany(ContentSection::class, 'parent_section_id')->orderBy('sort_order');
    }
}

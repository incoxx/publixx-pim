<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Baustein-Typ für strukturierten Content (z. B. Headline, Teaser, Video).
 * Das Feld-Schema (`schema`) definiert die typisierten Felder des Bausteins
 * und nutzt die data_type-Welt der Attribute wieder (gleiche Renderer/i18n).
 */
class SectionType extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'technical_name',
        'name_de',
        'name_en',
        'name_json',
        'icon',
        'category',
        'schema',
        'is_repeatable',
        'is_nestable',
        'preview_component',
        'is_system',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'name_json' => 'array',
            'schema' => 'array',
            'is_repeatable' => 'boolean',
            'is_nestable' => 'boolean',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function sections(): HasMany
    {
        return $this->hasMany(ContentSection::class);
    }
}

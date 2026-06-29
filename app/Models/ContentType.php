<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasDeletionConstraints;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Seitentyp für strukturierten Content (analog ProductType).
 * Definiert, welche Sektionstypen auf einer Seite dieses Typs erlaubt
 * und welche standardmäßig vorbelegt sind.
 */
class ContentType extends Model
{
    use HasDeletionConstraints, HasFactory, HasUuids;

    protected $fillable = [
        'technical_name',
        'name_de',
        'name_en',
        'name_json',
        'description',
        'icon',
        'color',
        'allowed_section_types',
        'default_sections',
        'layout_hint',
        'workflow_id',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'name_json' => 'array',
            'allowed_section_types' => 'array',
            'default_sections' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(ContentPage::class);
    }

    public function deletionConstraints(): array
    {
        return [
            'pages' => 'Content-Seiten',
        ];
    }
}

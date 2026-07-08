<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasDeletionConstraints;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CollectionType extends Model
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
        'default_attribute_groups',
        'default_item_attribute_groups',
        'default_render_template_id',
        'default_price_type',
        'requires_organization',
        'requires_snapshot',
        'allowed_export_formats',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'name_json' => 'array',
            'default_attribute_groups' => 'array',
            'default_item_attribute_groups' => 'array',
            'allowed_export_formats' => 'array',
            'requires_organization' => 'boolean',
            'requires_snapshot' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class);
    }

    public function defaultRenderTemplate(): BelongsTo
    {
        return $this->belongsTo(PdfTemplate::class, 'default_render_template_id');
    }

    public function deletionConstraints(): array
    {
        return [
            'collections' => 'Collections',
        ];
    }
}

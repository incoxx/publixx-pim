<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PublixxExportMapping extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'attribute_view_id',
        'output_hierarchy_id',
        'mapping_rules',
        'include_media',
        'include_prices',
        'include_variants',
        'include_relations',
        'languages',
        'flatten_mode',
    ];

    protected function casts(): array
    {
        return [
            'mapping_rules' => 'array',
            'include_media' => 'boolean',
            'include_prices' => 'boolean',
            'include_variants' => 'boolean',
            'include_relations' => 'boolean',
            'languages' => 'array',
        ];
    }

    public function attributeView(): BelongsTo
    {
        return $this->belongsTo(AttributeView::class);
    }

    public function outputHierarchy(): BelongsTo
    {
        return $this->belongsTo(Hierarchy::class, 'output_hierarchy_id');
    }

    public function pxfTemplates(): HasMany
    {
        return $this->hasMany(PxfTemplate::class, 'export_mapping_id');
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasDeletionConstraints;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Konfigurierbares Ausgabeformat für die Rendition-Pipeline
 * (z.B. "Print TIFF CMYK 300dpi" oder "Web JPEG sRGB").
 */
class MediaRenditionPreset extends Model
{
    use HasDeletionConstraints, HasFactory, HasUuids;

    protected $table = 'media_rendition_presets';

    protected $fillable = [
        'technical_name',
        'name_de',
        'name_en',
        'channel',
        'format',
        'colorspace',
        'fit',
        'max_width',
        'max_height',
        'dpi',
        'quality',
        'background_color',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'max_width' => 'integer',
            'max_height' => 'integer',
            'dpi' => 'integer',
            'quality' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function renditions(): HasMany
    {
        return $this->hasMany(Media::class, 'rendition_preset_id');
    }

    /**
     * Erfordert ein Colorspace/Format, das GD nicht kann (CMYK, TIFF)?
     */
    public function requiresImagick(): bool
    {
        return $this->colorspace === 'cmyk' || $this->format === 'tiff';
    }

    public function deletionConstraints(): array
    {
        return [
            'renditions' => 'Generierte Renditions',
        ];
    }
}

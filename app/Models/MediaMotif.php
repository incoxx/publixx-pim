<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasDeletionConstraints;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Motiv-Ebene: ein Bildinhalt, der als mehrere Renditions (media-Zeilen)
 * in unterschiedlichen Kanälen/Formaten/Farbräumen existiert.
 */
class MediaMotif extends Model
{
    use HasDeletionConstraints, HasFactory, HasUuids;

    protected $table = 'media_motifs';

    protected $fillable = [
        'title_de',
        'title_en',
        'description_de',
        'description_en',
        'focal_point_x',
        'focal_point_y',
        'rights_holder',
        'license_type',
        'license_valid_until',
        'copyright_notice',
        'usage_restrictions',
        'asset_folder_id',
    ];

    protected function casts(): array
    {
        return [
            'focal_point_x' => 'decimal:4',
            'focal_point_y' => 'decimal:4',
            'license_valid_until' => 'date',
        ];
    }

    public function assetFolder(): BelongsTo
    {
        return $this->belongsTo(HierarchyNode::class, 'asset_folder_id');
    }

    public function renditions(): HasMany
    {
        return $this->hasMany(Media::class, 'motif_id');
    }

    public function masterRendition(): HasOne
    {
        return $this->hasOne(Media::class, 'motif_id')->where('is_master_rendition', true);
    }

    public function isLicenseExpired(): bool
    {
        return $this->license_valid_until !== null && $this->license_valid_until->isPast();
    }

    public function deletionConstraints(): array
    {
        return [
            'renditions' => 'Renditions (Bildvarianten)',
        ];
    }
}

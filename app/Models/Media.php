<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasDeletionConstraints;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Media extends Model
{
    use HasDeletionConstraints, HasFactory, HasUuids;

    protected $table = 'media';

    protected $fillable = [
        'file_name',
        'original_file_name',
        'file_path',
        'file_status',
        'mime_type',
        'file_size',
        'media_type',
        'title_de',
        'title_en',
        'description_de',
        'description_en',
        'alt_text_de',
        'alt_text_en',
        'keywords',
        'width',
        'height',
        'asset_folder_id',
        'usage_purpose',
        'language',
        'document_type',
        'media_language_id',
        'media_country_id',
        'last_uploaded_at',
        'motif_id',
        'is_master_rendition',
        'rendition_channel',
        'rendition_preset_id',
        'colorspace',
        'dpi',
        'crop_data',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'keywords' => 'array',
            'width' => 'integer',
            'height' => 'integer',
            'last_uploaded_at' => 'datetime',
            'is_master_rendition' => 'boolean',
            'dpi' => 'integer',
            'crop_data' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    public function assetFolder(): BelongsTo
    {
        return $this->belongsTo(HierarchyNode::class, 'asset_folder_id');
    }

    /**
     * Motiv, zu dem diese Rendition gehört (NULL = eigenständiges Medium,
     * "einfache Lösung" ohne Motiv-Gruppierung).
     */
    public function motif(): BelongsTo
    {
        return $this->belongsTo(MediaMotif::class, 'motif_id');
    }

    public function renditionPreset(): BelongsTo
    {
        return $this->belongsTo(MediaRenditionPreset::class, 'rendition_preset_id');
    }

    public function mediaLanguage(): BelongsTo
    {
        return $this->belongsTo(MediaLanguage::class, 'media_language_id');
    }

    public function mediaCountry(): BelongsTo
    {
        return $this->belongsTo(MediaCountry::class, 'media_country_id');
    }

    public function attributeValues(): HasMany
    {
        return $this->hasMany(MediaAttributeValue::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_media_assignments')
            ->withPivot(['usage_type_id', 'sort_order', 'is_primary']);
    }

    public function productAssignments(): HasMany
    {
        return $this->hasMany(ProductMediaAssignment::class);
    }

    public function pdfDocument(): HasOne
    {
        return $this->hasOne(PdfDocument::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(MediaRevision::class)->orderByDesc('revision_number');
    }

    public function hierarchyNodeAssignments(): HasMany
    {
        return $this->hasMany(HierarchyNodeMediaAssignment::class);
    }

    public function hierarchyNodes(): BelongsToMany
    {
        return $this->belongsToMany(HierarchyNode::class, 'hierarchy_node_media_assignments')
            ->withPivot(['usage_type_id', 'sort_order', 'is_primary']);
    }

    /**
     * Schließt Medien aus, auf deren UsageType (über Produkt- oder Hierarchie-Zuordnung)
     * der übergebene Nutzer keinen Zugriff hat. Ohne Nutzer (z.B. anonymer Zugriff auf
     * den öffentlichen Asset-Katalog) werden alle irgendwo per RoleEntityRestriction
     * eingeschränkten Typen pauschal ausgeschlossen, da hier keine differenzierte
     * Zugriffsprüfung möglich ist. Mit Admin-Nutzer wird nichts ausgeschlossen.
     */
    public function scopeExcludingRestrictionSensitive($query, ?User $user = null)
    {
        if ($user && $user->hasRole('Admin')) {
            return $query;
        }

        if ($user) {
            $roleIds = $user->roles->pluck('id');
            $ownRestrictions = RoleEntityRestriction::whereIn('role_id', $roleIds)
                ->where('restrictable_type', MediaUsageType::class)
                ->get();

            if ($ownRestrictions->isEmpty()) {
                return $query;
            }

            $allowedIds = $ownRestrictions->pluck('restrictable_id');
            $excludedIds = MediaUsageType::whereNotIn('id', $allowedIds)->pluck('id');
        } else {
            $excludedIds = MediaUsageType::restrictionSensitiveIds();
        }

        if ($excludedIds->isEmpty()) {
            return $query;
        }

        return $query
            ->whereDoesntHave('productAssignments', fn ($q) => $q->whereIn('usage_type_id', $excludedIds))
            ->whereDoesntHave('hierarchyNodeAssignments', fn ($q) => $q->whereIn('usage_type_id', $excludedIds));
    }

    public function deletionConstraints(): array
    {
        return [
            'productAssignments'        => 'Produkt-Zuordnungen',
            'hierarchyNodeAssignments'  => 'Knoten-Zuordnungen',
            'attributeValues'           => 'Attributwerte',
        ];
    }
}

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
 * Virtuelles Referenzprodukt / Referenz-Profil.
 *
 * Definiert das Sollbild einer Produktgruppe (z.B. "Akkubohrschrauber") über
 * Prüfregeln. Profile erben voneinander (parent_profile_id); abstrakte Profile
 * sind nur Basis und nicht direkt von Produkten referenzierbar.
 */
class ProductReferenceProfile extends Model
{
    use HasDeletionConstraints, HasFactory, HasUuids;

    protected $fillable = [
        'technical_name',
        'name',
        'description',
        'parent_profile_id',
        'is_abstract',
        'is_active',
        'version',
        'rules',
        'golden_product_ids',
    ];

    protected function casts(): array
    {
        return [
            'is_abstract' => 'boolean',
            'is_active' => 'boolean',
            'version' => 'integer',
            'rules' => 'array',
            'golden_product_ids' => 'array',
        ];
    }

    public function parentProfile(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_profile_id');
    }

    public function childProfiles(): HasMany
    {
        return $this->hasMany(self::class, 'parent_profile_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'reference_profile_id');
    }

    public function conformanceResults(): HasMany
    {
        return $this->hasMany(ProductConformanceResult::class, 'profile_id');
    }

    public function deletionConstraints(): array
    {
        return [
            'products'      => 'Produkte',
            'childProfiles' => 'Abgeleitete Profile',
        ];
    }
}

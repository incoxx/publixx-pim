<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasDeletionConstraints;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MediaUsageType extends Model
{
    use HasDeletionConstraints, HasFactory, HasUuids;

    protected $fillable = [
        'technical_name',
        'name_de',
        'name_en',
        'name_json',
        'sort_order',
        'allowed_extensions',
        'restricted_display_mode',
        'allowed_product_type_ids',
    ];

    protected function casts(): array
    {
        return [
            'name_json' => 'array',
            'sort_order' => 'integer',
            'allowed_extensions' => 'array',
            'allowed_product_type_ids' => 'array',
        ];
    }

    /**
     * Gilt dieser Medientyp für den angegebenen Produkttyp?
     * Leere/NULL-Einschränkung = für alle Produkttypen gültig (Default).
     */
    public function allowsProductType(?string $productTypeId): bool
    {
        return empty($this->allowed_product_type_ids)
            || in_array($productTypeId, $this->allowed_product_type_ids, true);
    }

    public function mediaAssignments(): HasMany
    {
        return $this->hasMany(ProductMediaAssignment::class, 'usage_type_id');
    }

    /**
     * IDs aller UsageTypes, für die irgendeine Rolle eine RoleEntityRestriction hat
     * (unabhängig von hidden/locked). Genutzt, um Kontexte ohne Nutzer-/Rollenbezug
     * (z.B. den öffentlichen Asset-Katalog) pauschal von restriktionssensiblen
     * Medientypen freizuhalten.
     */
    public static function restrictionSensitiveIds(): \Illuminate\Support\Collection
    {
        return RoleEntityRestriction::where('restrictable_type', self::class)
            ->pluck('restrictable_id')
            ->unique();
    }

    public function defaultAttributes(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class, 'usage_type_default_attributes', 'usage_type_id', 'attribute_id')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function deletionConstraints(): array
    {
        return [
            'mediaAssignments' => 'Medien-Zuordnungen',
        ];
    }
}

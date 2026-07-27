<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasDeletionConstraints;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttributeView extends Model
{
    use HasDeletionConstraints, HasFactory, HasUuids;

    /** Präfix für den synthetischen RoleTabPermission-Tab-Key dynamischer Sicht-Tabs. */
    public const TAB_KEY_PREFIX = 'attribute-view:';

    protected $fillable = [
        'technical_name',
        'name_de',
        'name_en',
        'name_json',
        'description',
        'sort_order',
        'is_write_protected',
        'show_as_tab',
        'allowed_product_type_ids',
    ];

    protected function casts(): array
    {
        return [
            'name_json' => 'array',
            'sort_order' => 'integer',
            'is_write_protected' => 'boolean',
            'show_as_tab' => 'boolean',
            'allowed_product_type_ids' => 'array',
        ];
    }

    /**
     * Gilt diese Sicht für den angegebenen Produkttyp?
     * Leere/NULL-Einschränkung = für alle Produkttypen gültig (Default).
     */
    public function allowsProductType(?string $productTypeId): bool
    {
        return empty($this->allowed_product_type_ids)
            || in_array($productTypeId, $this->allowed_product_type_ids, true);
    }

    /**
     * Bewusst OHNE eigene orderBy()-Vorgabe: andere Konsumenten (z.B. CollectionRenderService)
     * legen für diese Relation eigene Eager-Load-Sortierungen fest (z.B. nach Attribute.position),
     * die sich sonst mit einer hier fest verdrahteten Sortierung nach Pivot-sort_order summieren
     * würden. Die Drag&Drop-Reihenfolge für Produkteditor-Tabs wird stattdessen explizit in
     * AttributeViewResource sortiert.
     */
    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class, 'attribute_view_assignments')
            ->using(AttributeViewAssignment::class)
            ->withPivot('sort_order', 'is_readonly');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(AttributeViewAssignment::class);
    }

    public function deletionConstraints(): array
    {
        return [
            'assignments' => 'Attribut-Zuordnungen',
        ];
    }

    /**
     * Synthetischer Tab-Key für die Produkteditor-Rollensteuerung (RoleTabPermission),
     * damit Attribut-Sicht-Tabs dieselbe hidden/read/write-Logik wie feste Tabs nutzen.
     */
    public function tabKey(): string
    {
        return self::TAB_KEY_PREFIX . $this->id;
    }

    protected static function booted(): void
    {
        static::deleted(function (self $view) {
            RoleTabPermission::where('tab_key', $view->tabKey())->delete();
        });
    }
}

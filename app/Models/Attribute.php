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
use Illuminate\Validation\ValidationException;

class Attribute extends Model
{
    use HasDeletionConstraints, HasFactory, HasUuids;

    /**
     * Einzige Wahrheitsquelle für alle gültigen Attribut-Datentypen.
     *
     * Wird von Validierung (StoreAttributeRequest, UpdateAttributeRequest),
     * Import (SheetValidator, TemplateGenerator) und Migrationen referenziert,
     * damit die Typ-Liste nicht mehr an mehreren Stellen driften kann.
     */
    public const DATA_TYPES = [
        'String', 'Number', 'Float', 'Date', 'Flag',
        'Selection', 'MultiSelection', 'Dictionary', 'Composite', 'RichText',
        'Hyperlink', 'ImageLink', 'PdfLink', 'VideoLink',
        'DelimitedValue', 'JsonArtefact', 'Textarea',
        'HierarchyNodeReference', 'ProductReference', 'SimpleSelect', 'SimpleMultiSelect',
    ];

    /**
     * Datentypen, die ein anderes PIM-Objekt referenzieren.
     * Der Zielwert wird als UUID in value_string abgelegt (kein DB-FK, da die
     * Spalte typübergreifend genutzt wird und das Ziel je nach Typ variiert).
     */
    public const REFERENCE_TYPES = ['HierarchyNodeReference', 'ProductReference'];

    /**
     * Liefert die value_*-Spalte, in der ein Datentyp gespeichert wird.
     * Zentrale Storage-Klassifikation der EAV-Spalten (value_string ist der
     * Standard; nur Number/Float/Date/Flag weichen ab).
     */
    public static function storageColumn(string $dataType): string
    {
        return match ($dataType) {
            'Number', 'Float' => 'value_number',
            'Date'            => 'value_date',
            'Flag'            => 'value_flag',
            default           => 'value_string',
        };
    }

    /**
     * Löst einen Selection/Dictionary-Rohwert (ID, technical_name oder
     * Anzeigetext) auf den passenden, aktiven Werteliste-Eintrag auf.
     *
     * Zentrale Stelle für alle Schreibpfade (Varianten, Bulk-Bearbeitung,
     * Bulk-Update): ein ungeprüfter String in value_selection_id verletzt die
     * FK-Constraint gegen value_list_entries.
     *
     * @return array{value_string: ?string, value_selection_id: ?string}
     *
     * @throws ValidationException wenn kein aktiver Eintrag passt
     */
    public function resolveSelectionEntry(string $value): array
    {
        if (!$this->value_list_id) {
            return ['value_string' => $value, 'value_selection_id' => null];
        }

        $entry = ValueListEntry::where('value_list_id', $this->value_list_id)
            ->where('is_active', true)
            ->where(function ($q) use ($value) {
                $q->where('id', $value)
                    ->orWhere('technical_name', $value)
                    ->orWhere('display_value_de', $value)
                    ->orWhere('display_value_en', $value);
            })
            ->first();

        if (!$entry) {
            throw ValidationException::withMessages([
                'value' => "Wert \"{$value}\" ist kein gültiger Eintrag der Werteliste von Attribut \"{$this->technical_name}\".",
            ]);
        }

        return [
            'value_string' => $entry->display_value_de ?? $entry->technical_name,
            'value_selection_id' => $entry->id,
        ];
    }

    protected $fillable = [
        'technical_name',
        'name_de',
        'name_en',
        'name_json',
        'description_de',
        'description_en',
        'data_type',
        'attribute_type_id',
        'value_list_id',
        'formatting_rule_id',
        'unit_group_id',
        'default_unit_id',
        'comparison_operator_group_id',
        'is_translatable',
        'is_multipliable',
        'max_multiplied',
        'max_pre_decimal',
        'max_post_decimal',
        'max_characters',
        'min_value',
        'max_value',
        'is_searchable',
        'is_mandatory',
        'is_unique',
        'is_country_specific',
        'is_inheritable',
        'is_variant_attribute',
        'is_internal',
        'is_readonly',
        'is_hidden',
        'is_quick_search',
        'is_primary',
        'parent_attribute_id',
        'composite_format',
        'composite_expression',
        'delimiter',
        'textarea_rows',
        'textarea_cols',
        'simple_options',
        'position',
        'source_system',
        'source_attribute_name',
        'source_attribute_key',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'name_json' => 'array',
            'simple_options' => 'array',
            'is_translatable' => 'boolean',
            'is_multipliable' => 'boolean',
            'max_multiplied' => 'integer',
            'max_pre_decimal' => 'integer',
            'max_post_decimal' => 'integer',
            'max_characters' => 'integer',
            'textarea_rows' => 'integer',
            'textarea_cols' => 'integer',
            'min_value' => 'decimal:6',
            'max_value' => 'decimal:6',
            'is_searchable' => 'boolean',
            'is_mandatory' => 'boolean',
            'is_unique' => 'boolean',
            'is_country_specific' => 'boolean',
            'is_inheritable' => 'boolean',
            'is_variant_attribute' => 'boolean',
            'is_internal' => 'boolean',
            'is_readonly' => 'boolean',
            'is_hidden' => 'boolean',
            'is_quick_search' => 'boolean',
            'is_primary' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function attributeType(): BelongsTo
    {
        return $this->belongsTo(AttributeType::class);
    }

    public function valueList(): BelongsTo
    {
        return $this->belongsTo(ValueList::class);
    }

    public function formattingRule(): BelongsTo
    {
        return $this->belongsTo(AttributeFormattingRule::class, 'formatting_rule_id');
    }

    public function unitGroup(): BelongsTo
    {
        return $this->belongsTo(UnitGroup::class);
    }

    public function defaultUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'default_unit_id');
    }

    public function comparisonOperatorGroup(): BelongsTo
    {
        return $this->belongsTo(ComparisonOperatorGroup::class);
    }

    public function parentAttribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class, 'parent_attribute_id');
    }

    /**
     * Alias for parentAttribute() — enables ?include=parent.
     */
    public function parent(): BelongsTo
    {
        return $this->parentAttribute();
    }

    public function childAttributes(): HasMany
    {
        return $this->hasMany(Attribute::class, 'parent_attribute_id');
    }

    /**
     * Alias for childAttributes() — enables ?include=children.
     */
    public function children(): HasMany
    {
        return $this->childAttributes();
    }

    public function scopeComposite($query)
    {
        return $query->where('data_type', 'Composite');
    }

    public function attributeViews(): BelongsToMany
    {
        return $this->belongsToMany(AttributeView::class, 'attribute_view_assignments')
            ->using(AttributeViewAssignment::class);
    }

    public function viewAssignments(): HasMany
    {
        return $this->hasMany(AttributeViewAssignment::class);
    }

    public function productAttributeValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }

    public function mediaAttributeValues(): HasMany
    {
        return $this->hasMany(MediaAttributeValue::class);
    }

    public function hierarchyNodeAssignments(): HasMany
    {
        return $this->hasMany(HierarchyNodeAttributeAssignment::class);
    }

    public function variantInheritanceRules(): HasMany
    {
        return $this->hasMany(VariantInheritanceRule::class);
    }

    public function variantAxes(): HasMany
    {
        return $this->hasMany(ProductVariantAxis::class);
    }

    public function dictionaryEntries(): BelongsToMany
    {
        return $this->belongsToMany(DictionaryEntry::class, 'attribute_dictionary_entry')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderBy('sort_order');
    }

    public function deletionConstraints(): array
    {
        return [
            'productAttributeValues'  => 'Produktattributwerte',
            'childAttributes'         => 'Unterattribute',
            'viewAssignments'         => 'Attributsicht-Zuordnungen',
            'hierarchyNodeAssignments' => 'Hierarchie-Zuordnungen',
            'mediaAttributeValues'    => 'Medien-Attributwerte',
            'variantInheritanceRules' => 'Vererbungsregeln',
            'variantAxes'             => 'Varianten-Achsen',
        ];
    }
}

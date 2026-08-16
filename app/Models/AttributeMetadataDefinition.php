<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasDeletionConstraints;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Frei definierbares Metadaten-Feld für Attributdefinitionen.
 *
 * Beispiele: Datenherkunft (ERP/Agentur/Marketing), Dateneigentümer, Datenverbindung.
 * Dient der fachlichen Governance und ist bewusst getrennt von den technischen
 * Import-Herkunftsfeldern `attributes.source_system` & Co.
 */
class AttributeMetadataDefinition extends Model
{
    use HasDeletionConstraints, HasFactory, HasUuids;

    /** Einzige Wahrheitsquelle für alle gültigen Metadaten-Wert-Typen. */
    public const VALUE_TYPES = [
        'text',
        'textarea',
        'number',
        'date',
        'boolean',
        'select',
        'multiselect',
        'url',
        'email',
    ];

    /** Wert-Typen, die eine Optionsliste benötigen. */
    public const OPTION_TYPES = ['select', 'multiselect'];

    /** Wert-Typen, die mehrere Werte speichern (Ablage in `value_json`). */
    public const MULTI_VALUE_TYPES = ['multiselect'];

    protected $fillable = [
        'technical_name',
        'name_de',
        'name_en',
        'description',
        'value_type',
        'options',
        'is_required',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_required' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function values(): HasMany
    {
        return $this->hasMany(AttributeMetadataValue::class, 'definition_id');
    }

    public function deletionConstraints(): array
    {
        return [
            'values' => 'Attribut-Metadatenwerte',
        ];
    }

    /**
     * Speichert dieser Typ mehrere Werte (Ablage in `value_json`)?
     */
    public function isMultiValue(): bool
    {
        return in_array($this->value_type, self::MULTI_VALUE_TYPES, true);
    }

    /**
     * Extrahiert die speicherbaren Werte aus einer Optionsliste.
     *
     * Optionen werden im Format `Label::Wert` gepflegt (wie bei
     * `attributes.simple_options`); gespeichert wird nur der Wert-Anteil.
     * Ohne `::` ist die Option selbst der Wert.
     *
     * @param array<int, string>|null $options
     * @return array<int, string>
     */
    public static function optionValues(?array $options): array
    {
        return array_map(
            static function (string $option): string {
                $parts = explode('::', $option, 2);

                return trim($parts[1] ?? $parts[0]);
            },
            $options ?? []
        );
    }

    /**
     * Speicherbare Werte der eigenen Optionsliste.
     *
     * @return array<int, string>
     */
    public function allowedValues(): array
    {
        return self::optionValues($this->options);
    }
}

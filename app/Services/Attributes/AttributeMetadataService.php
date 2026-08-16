<?php

declare(strict_types=1);

namespace App\Services\Attributes;

use App\Models\Attribute;
use App\Models\AttributeMetadataDefinition;
use App\Models\AttributeMetadataValue;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Collection;

/**
 * Pflegt die Metadatenwerte (Data Quality & Ownership) einer Attributdefinition.
 *
 * Die Werte reisen als Map `technical_name => Wert` im Attribut-Payload mit;
 * dieser Service übernimmt Validierung, Normalisierung und Abgleich.
 */
class AttributeMetadataService
{
    /**
     * Gleicht die übergebene Metadaten-Map mit den gespeicherten Werten ab.
     *
     * Nicht enthaltene Definitionen bleiben unangetastet (partielles Update).
     * Ein leerer Wert löscht die Zeile — es entstehen keine Leerzeilen.
     *
     * @param array<string, mixed> $metadata
     */
    public function sync(Attribute $attribute, array $metadata): void
    {
        if ($metadata === []) {
            return;
        }

        $definitions = $this->definitionsFor(array_keys($metadata));

        foreach ($metadata as $technicalName => $rawValue) {
            $definition = $definitions->get($technicalName);
            if (!$definition instanceof AttributeMetadataDefinition) {
                continue;
            }

            $normalized = $this->normalize($definition, $rawValue);

            if ($normalized === null) {
                AttributeMetadataValue::where('attribute_id', $attribute->id)
                    ->where('definition_id', $definition->id)
                    ->delete();

                continue;
            }

            AttributeMetadataValue::updateOrCreate(
                ['attribute_id' => $attribute->id, 'definition_id' => $definition->id],
                $normalized
            );
        }
    }

    /**
     * Überträgt alle Metadatenwerte eines Attributs auf ein anderes (Kopieren).
     */
    public function copyValues(Attribute $from, Attribute $to): void
    {
        foreach ($from->metadataValues()->get() as $value) {
            AttributeMetadataValue::updateOrCreate(
                ['attribute_id' => $to->id, 'definition_id' => $value->definition_id],
                ['value' => $value->value, 'value_json' => $value->value_json]
            );
        }
    }

    /**
     * Liefert die Metadaten eines Attributs als Map `technical_name => Wert`.
     *
     * @return array<string, mixed>
     */
    public function toMap(Attribute $attribute): array
    {
        return $attribute->metadataValues
            ->filter(fn (AttributeMetadataValue $v) => $v->definition !== null)
            ->mapWithKeys(fn (AttributeMetadataValue $v) => [
                $v->definition->technical_name => $v->resolvedValue(),
            ])
            ->all();
    }

    /**
     * Prüft eine Metadaten-Map und hängt Fehler an den Validator.
     *
     * Pflichtfelder werden nur geprüft, wenn der Payload den `metadata`-Key
     * überhaupt mitschickt — sonst würden Importer und Massenoperationen brechen.
     *
     * @param array<string, mixed> $metadata
     */
    public function validate(array $metadata, Validator $validator, string $prefix = 'metadata'): void
    {
        $definitions = $this->definitionsFor(array_keys($metadata));

        foreach ($metadata as $technicalName => $value) {
            $field = $prefix . '.' . $technicalName;
            $definition = $definitions->get($technicalName);

            if (!$definition instanceof AttributeMetadataDefinition) {
                $validator->errors()->add($field, 'Unbekannte Metadaten-Definition "' . $technicalName . '".');

                continue;
            }

            $isEmpty = $value === null || $value === '' || $value === [];

            if ($isEmpty) {
                if ($definition->is_required) {
                    $validator->errors()->add(
                        $field,
                        'Das Metadatenfeld "' . ($definition->name_de ?: $technicalName) . '" ist ein Pflichtfeld.'
                    );
                }

                continue;
            }

            $this->validateValue($definition, $value, $field, $validator);
        }
    }

    /**
     * @param array<int, string> $technicalNames
     * @return Collection<string, AttributeMetadataDefinition>
     */
    private function definitionsFor(array $technicalNames): Collection
    {
        if ($technicalNames === []) {
            return collect();
        }

        return AttributeMetadataDefinition::whereIn('technical_name', $technicalNames)
            ->get()
            ->keyBy('technical_name');
    }

    private function validateValue(
        AttributeMetadataDefinition $definition,
        mixed $value,
        string $field,
        Validator $validator
    ): void {
        $label = $definition->name_de ?: $definition->technical_name;

        // Formprüfung vor allen Typprüfungen: ohne sie würde ein Array in den
        // (string)-Casts unten eine PHP-Warnung und damit einen 500er auslösen,
        // und für text/textarea/boolean gäbe es gar keine Prüfung — normalize()
        // liefert dort für Arrays null und würde die Zeile still löschen.
        if (!$this->hasValidShape($definition, $value)) {
            $validator->errors()->add(
                $field,
                $definition->isMultiValue()
                    ? 'Das Metadatenfeld "' . $label . '" erwartet eine Liste einfacher Werte.'
                    : 'Das Metadatenfeld "' . $label . '" erwartet einen einzelnen Wert.'
            );

            return;
        }

        switch ($definition->value_type) {
            case 'number':
                if (!is_numeric($value)) {
                    $validator->errors()->add($field, 'Das Metadatenfeld "' . $label . '" erwartet eine Zahl.');
                }
                break;

            case 'date':
                if (!is_string($value) || strtotime($value) === false) {
                    $validator->errors()->add($field, 'Das Metadatenfeld "' . $label . '" erwartet ein Datum.');
                }
                break;

            case 'url':
                if (!is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false) {
                    $validator->errors()->add($field, 'Das Metadatenfeld "' . $label . '" erwartet eine gültige URL.');
                }
                break;

            case 'email':
                if (!is_string($value) || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                    $validator->errors()->add($field, 'Das Metadatenfeld "' . $label . '" erwartet eine gültige E-Mail-Adresse.');
                }
                break;

            case 'select':
                if (!in_array((string) $value, $definition->allowedValues(), true)) {
                    $validator->errors()->add(
                        $field,
                        'Der Wert "' . $value . '" ist keine gültige Option für "' . $label . '".'
                    );
                }
                break;

            case 'multiselect':
                $allowed = $definition->allowedValues();
                foreach ($value as $entry) {
                    if (!in_array((string) $entry, $allowed, true)) {
                        $validator->errors()->add(
                            $field,
                            'Der Wert "' . $entry . '" ist keine gültige Option für "' . $label . '".'
                        );
                    }
                }
                break;
        }
    }

    /**
     * Hat der Rohwert die für den Typ erwartete Grundform?
     *
     * Mehrfachauswahl: Liste aus Skalaren. Alles andere: ein einzelner Skalar.
     */
    private function hasValidShape(AttributeMetadataDefinition $definition, mixed $value): bool
    {
        if ($definition->isMultiValue()) {
            if (!is_array($value)) {
                return false;
            }

            foreach ($value as $entry) {
                if (!is_scalar($entry)) {
                    return false;
                }
            }

            return true;
        }

        return is_scalar($value);
    }

    /**
     * Bringt einen Rohwert in Speicherform. `null` bedeutet: Zeile löschen.
     *
     * @return array{value: ?string, value_json: ?array<int, string>}|null
     */
    private function normalize(AttributeMetadataDefinition $definition, mixed $value): ?array
    {
        if ($definition->isMultiValue()) {
            if (!is_array($value)) {
                return null;
            }

            $clean = array_values(array_filter(
                array_map(static fn ($v) => is_scalar($v) ? trim((string) $v) : '', $value),
                static fn (string $v) => $v !== ''
            ));

            return $clean === [] ? null : ['value' => null, 'value_json' => $clean];
        }

        if ($value === null || is_array($value)) {
            return null;
        }

        if ($definition->value_type === 'boolean') {
            // Ein abgewähltes Kontrollkästchen ist eine echte Angabe ("nein"),
            // kein leeres Feld — deshalb kein Löschen bei false.
            $flag = filter_var($value, FILTER_VALIDATE_BOOLEAN);

            return ['value' => $flag ? '1' : '0', 'value_json' => null];
        }

        $string = trim((string) $value);

        return $string === '' ? null : ['value' => $string, 'value_json' => null];
    }
}

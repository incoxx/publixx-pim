<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\AttributeMetadataDefinition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAttributeMetadataDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // `technical_name` ist bewusst nicht änderbar: die Metadaten-Map am
        // Attribut-Endpoint ist danach geschlüsselt.
        return [
            'name_de' => 'sometimes|required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'value_type' => ['sometimes', 'required', Rule::in(AttributeMetadataDefinition::VALUE_TYPES)],
            'options' => 'nullable|array',
            'options.*' => 'string|max:255',
            'is_required' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var AttributeMetadataDefinition|null $definition */
            $definition = $this->route('attribute_metadata_definition');
            if (!$definition instanceof AttributeMetadataDefinition) {
                return;
            }

            $type = $this->input('value_type', $definition->value_type);

            if (in_array($type, AttributeMetadataDefinition::OPTION_TYPES, true)) {
                $options = $this->has('options') ? $this->input('options') : $definition->options;

                if (empty($options)) {
                    $validator->errors()->add(
                        'options',
                        'Für den Wert-Typ "' . $type . '" muss mindestens eine Auswahloption angegeben werden.'
                    );

                    return;
                }

                // Entfernte Optionen dürfen nicht mehr in Gebrauch sein.
                if ($this->has('options')) {
                    $removed = $this->stillUsedRemovedOptions($definition, $options);
                    if ($removed !== []) {
                        $validator->errors()->add(
                            'options',
                            'Diese Optionen sind noch an Attributen hinterlegt und können nicht entfernt werden: '
                            . implode(', ', $removed)
                        );
                    }
                }
            }
        });
    }

    /**
     * Liefert die entfernten Optionen, die noch von Metadatenwerten belegt sind.
     *
     * @param array<int, string> $newOptions
     * @return array<int, string>
     */
    private function stillUsedRemovedOptions(AttributeMetadataDefinition $definition, array $newOptions): array
    {
        // Verglichen wird auf Ebene der gespeicherten Werte: Optionen werden als
        // `Label::Wert` gepflegt, abgelegt wird nur der Wert-Anteil. Ein Diff über
        // die Rohstrings würde bei diesem Format nie greifen.
        $removed = array_values(array_diff(
            AttributeMetadataDefinition::optionValues($definition->options),
            AttributeMetadataDefinition::optionValues($newOptions)
        ));

        if ($removed === []) {
            return [];
        }

        $used = [];
        foreach ($definition->values()->get() as $value) {
            $current = $definition->isMultiValue()
                ? ($value->value_json ?? [])
                : array_filter([$value->value], fn ($v) => $v !== null);

            foreach ($current as $entry) {
                if (in_array($entry, $removed, true) && !in_array($entry, $used, true)) {
                    $used[] = $entry;
                }
            }
        }

        return $used;
    }
}

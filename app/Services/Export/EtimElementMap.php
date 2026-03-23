<?php

declare(strict_types=1);

namespace App\Services\Export;

/**
 * Definiert die ETIM-Klassifikations-Zielfelder für den Export.
 *
 * ETIM ist kein eigenständiges Export-Format, sondern eine Klassifikationsschicht
 * die sich in bestehende Formate (BMEcat, GAEB, etc.) integriert.
 * Die Mapping-Engine löst ETIM-Codes für Features, Values und Units auf.
 */
class EtimElementMap
{
    /**
     * Verfügbare Zielfelder für ETIM-Klassifikation.
     */
    public const TARGET_FIELDS = [
        'etim_classification',  // ETIM-Klasse (system_name + group_id)
        'etim_features',        // Alle Features mit ETIM-Codes (Array)
    ];

    /**
     * Standard-Mapping-Regeln für ETIM-Integration.
     *
     * Diese Regeln werden zusätzlich zu den Format-spezifischen Regeln verwendet,
     * wenn ETIM-Kontext gesetzt ist.
     */
    public static function defaultMappingRules(): array
    {
        return [
            ['source' => 'etim:classification', 'target' => 'etim_classification', 'type' => 'etim_class'],
            ['source' => 'etim:all_features', 'target' => 'etim_features', 'type' => 'etim_features'],
        ];
    }

    /**
     * Defaults für Felder ohne Mapping-Wert.
     */
    public static function fieldDefaults(): array
    {
        return [
            'etim_classification' => null,
            'etim_features' => [],
        ];
    }
}

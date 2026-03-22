<?php

declare(strict_types=1);

namespace App\Services\Etim;

use App\Models\Attribute;
use App\Models\Etim\EtimClass;
use App\Models\Etim\EtimClassMapping;
use App\Models\Etim\EtimFeature;
use App\Models\Etim\EtimFeatureMapping;
use App\Models\Etim\EtimUnitMapping;
use App\Models\Etim\EtimValue;
use App\Models\Etim\EtimValueMapping;
use App\Models\Etim\EtimVersion;
use App\Models\HierarchyNode;
use App\Models\Unit;
use App\Models\ValueListEntry;
use Illuminate\Support\Collection;

/**
 * Smart-Mapping-Service für PIM ↔ ETIM-Zuordnungen.
 *
 * Bietet automatische Vorschläge basierend auf Namens-Ähnlichkeit,
 * Datentyp-Kompatibilität und Einheiten-Matching.
 */
class EtimMappingService
{
    /** Mindest-Confidence für Auto-Suggest-Vorschläge */
    private const MIN_CONFIDENCE = 0.5;

    /** Gewichtung der einzelnen Matching-Strategien */
    private const WEIGHT_NAME = 0.50;
    private const WEIGHT_DATATYPE = 0.25;
    private const WEIGHT_UNIT = 0.25;

    // ─── Klassen-Mapping (HierarchyNode → ETIM-Klasse) ──────────────

    /**
     * Schlägt ETIM-Klassen für einen HierarchyNode vor.
     *
     * @return array<array{etim_class: EtimClass, confidence: float, reasons: string[]}>
     */
    public function suggestClassMapping(HierarchyNode $node, string $etimVersionId, int $limit = 10): array
    {
        $nodeName = mb_strtolower($node->name_de ?? '');
        if (!$nodeName) {
            return [];
        }

        $classes = EtimClass::where('etim_version_id', $etimVersionId)
            ->where('status', 'active')
            ->get();

        $suggestions = [];

        foreach ($classes as $class) {
            $classNameDe = mb_strtolower($class->name_de ?? '');
            $classNameEn = mb_strtolower($class->name_en ?? '');

            $confidence = 0;
            $reasons = [];

            // Namens-Matching (deutsch)
            $simDe = $this->calculateNameSimilarity($nodeName, $classNameDe);
            // Namens-Matching (englisch, falls vorhanden)
            $nodeNameEn = mb_strtolower($node->name_en ?? '');
            $simEn = $nodeNameEn ? $this->calculateNameSimilarity($nodeNameEn, $classNameEn) : 0;

            $nameSim = max($simDe, $simEn);
            $confidence = $nameSim;

            if ($nameSim >= 0.8) {
                $reasons[] = "Namens-Übereinstimmung: " . round($nameSim * 100) . '%';
            } elseif ($nameSim >= 0.5) {
                $reasons[] = "Teilweise Namens-Übereinstimmung: " . round($nameSim * 100) . '%';
            }

            // Enthaltener Substring?
            if ($nameSim < 0.8) {
                if (str_contains($classNameDe, $nodeName) || str_contains($nodeName, $classNameDe)) {
                    $confidence = max($confidence, 0.7);
                    $reasons[] = 'Kategoriename enthalten im Klassennamen (oder umgekehrt)';
                }
            }

            if ($confidence >= self::MIN_CONFIDENCE) {
                $suggestions[] = [
                    'etim_class' => $class,
                    'confidence' => round($confidence, 3),
                    'reasons' => $reasons,
                ];
            }
        }

        // Absteigend nach Confidence sortieren
        usort($suggestions, fn ($a, $b) => $b['confidence'] <=> $a['confidence']);

        return array_slice($suggestions, 0, $limit);
    }

    /**
     * Erstellt ein Klassen-Mapping manuell.
     */
    public function createClassMapping(
        string $hierarchyNodeId,
        string $etimClassId,
        string $etimVersionId,
        string $source = 'manual',
        float $confidence = 1.0,
        ?string $createdBy = null,
    ): EtimClassMapping {
        return EtimClassMapping::updateOrCreate(
            ['hierarchy_node_id' => $hierarchyNodeId, 'etim_version_id' => $etimVersionId],
            [
                'etim_class_id' => $etimClassId,
                'confidence' => $confidence,
                'mapping_source' => $source,
                'created_by' => $createdBy,
            ]
        );
    }

    // ─── Feature-Mapping (Attribut → ETIM-Feature) ──────────────────

    /**
     * Schlägt ETIM-Features für PIM-Attribute vor, eingeschränkt auf eine ETIM-Klasse.
     *
     * @return array<string, array{etim_feature: EtimFeature, confidence: float, reasons: string[]}>
     *         Schlüssel = Attribute-ID
     */
    public function suggestFeatureMappings(
        EtimClass $etimClass,
        Collection $attributes,
        string $etimVersionId,
    ): array {
        // Nur Features die zu dieser Klasse gehören (Kontext-Matching)
        $classFeatures = $etimClass->features()->get();

        $suggestions = [];

        foreach ($attributes as $attribute) {
            $bestMatch = null;
            $bestConfidence = 0;
            $bestReasons = [];

            // 1. Direkt-Match über source_system/source_attribute_key
            if ($attribute->source_system === 'etim' && $attribute->source_attribute_key) {
                $directMatch = $classFeatures->first(fn ($f) => $f->code === $attribute->source_attribute_key);
                if ($directMatch) {
                    $suggestions[$attribute->id] = [
                        'etim_feature' => $directMatch,
                        'confidence' => 1.0,
                        'reasons' => ['Direkt-Mapping über source_attribute_key'],
                    ];
                    continue;
                }
            }

            $attrNameDe = mb_strtolower($attribute->name_de ?? '');
            $attrNameEn = mb_strtolower($attribute->name_en ?? '');

            foreach ($classFeatures as $feature) {
                $confidence = 0;
                $reasons = [];

                // Namens-Matching
                $featureNameDe = mb_strtolower($feature->name_de ?? '');
                $featureNameEn = mb_strtolower($feature->name_en ?? '');

                $nameSim = max(
                    $attrNameDe ? $this->calculateNameSimilarity($attrNameDe, $featureNameDe) : 0,
                    ($attrNameEn && $featureNameEn) ? $this->calculateNameSimilarity($attrNameEn, $featureNameEn) : 0,
                );

                $confidence += $nameSim * self::WEIGHT_NAME;
                if ($nameSim >= 0.7) {
                    $reasons[] = "Namens-Ähnlichkeit: " . round($nameSim * 100) . '%';
                }

                // Datentyp-Matching
                $dataTypeSim = $this->calculateDataTypeCompatibility($attribute->data_type, $feature->data_type);
                $confidence += $dataTypeSim * self::WEIGHT_DATATYPE;
                if ($dataTypeSim >= 0.8) {
                    $reasons[] = 'Kompatibler Datentyp';
                }

                // Einheiten-Matching
                $unitSim = $this->calculateUnitSimilarity($attribute, $feature);
                $confidence += $unitSim * self::WEIGHT_UNIT;
                if ($unitSim >= 0.8) {
                    $reasons[] = 'Passende Einheit';
                }

                if ($confidence > $bestConfidence && $confidence >= self::MIN_CONFIDENCE) {
                    $bestMatch = $feature;
                    $bestConfidence = $confidence;
                    $bestReasons = $reasons;
                }
            }

            if ($bestMatch) {
                $suggestions[$attribute->id] = [
                    'etim_feature' => $bestMatch,
                    'confidence' => round($bestConfidence, 3),
                    'reasons' => $bestReasons,
                ];
            }
        }

        return $suggestions;
    }

    /**
     * Erstellt ein Feature-Mapping manuell.
     */
    public function createFeatureMapping(
        string $attributeId,
        string $etimFeatureId,
        string $etimVersionId,
        string $source = 'manual',
        float $confidence = 1.0,
        ?string $createdBy = null,
    ): EtimFeatureMapping {
        return EtimFeatureMapping::updateOrCreate(
            ['attribute_id' => $attributeId, 'etim_version_id' => $etimVersionId],
            [
                'etim_feature_id' => $etimFeatureId,
                'confidence' => $confidence,
                'mapping_source' => $source,
                'created_by' => $createdBy,
            ]
        );
    }

    // ─── Value-Mapping (ValueListEntry → ETIM-Value) ────────────────

    /**
     * Schlägt ETIM-Values für ValueListEntries eines Attributs vor.
     *
     * @return array<string, array{etim_value: EtimValue, confidence: float}>
     *         Schlüssel = ValueListEntry-ID
     */
    public function suggestValueMappings(
        Attribute $attribute,
        EtimFeature $etimFeature,
        string $etimVersionId,
    ): array {
        if (!$attribute->value_list_id) {
            return [];
        }

        $entries = ValueListEntry::where('value_list_id', $attribute->value_list_id)
            ->where('is_active', true)
            ->get();

        $allowedValues = $etimFeature->allowedValues()->get();

        if ($allowedValues->isEmpty()) {
            return [];
        }

        $suggestions = [];

        foreach ($entries as $entry) {
            $entryName = mb_strtolower($entry->display_value_de ?? $entry->technical_name ?? '');
            $bestMatch = null;
            $bestSim = 0;

            foreach ($allowedValues as $etimValue) {
                $valueName = mb_strtolower($etimValue->name_de ?? '');
                $sim = $this->calculateNameSimilarity($entryName, $valueName);

                if ($sim > $bestSim && $sim >= self::MIN_CONFIDENCE) {
                    $bestMatch = $etimValue;
                    $bestSim = $sim;
                }
            }

            if ($bestMatch) {
                $suggestions[$entry->id] = [
                    'etim_value' => $bestMatch,
                    'confidence' => round($bestSim, 3),
                ];
            }
        }

        return $suggestions;
    }

    /**
     * Erstellt ein Value-Mapping.
     */
    public function createValueMapping(
        string $valueListEntryId,
        string $etimValueId,
        string $etimVersionId,
        string $source = 'manual',
        float $confidence = 1.0,
    ): EtimValueMapping {
        return EtimValueMapping::updateOrCreate(
            ['value_list_entry_id' => $valueListEntryId, 'etim_version_id' => $etimVersionId],
            [
                'etim_value_id' => $etimValueId,
                'confidence' => $confidence,
                'mapping_source' => $source,
            ]
        );
    }

    // ─── Unit-Mapping (Unit → ETIM-Unit) ────────────────────────────

    /**
     * Erstellt ein Unit-Mapping.
     */
    public function createUnitMapping(
        string $unitId,
        string $etimUnitId,
        string $etimVersionId,
        string $source = 'manual',
        float $confidence = 1.0,
    ): EtimUnitMapping {
        return EtimUnitMapping::updateOrCreate(
            ['unit_id' => $unitId, 'etim_version_id' => $etimVersionId],
            [
                'etim_unit_id' => $etimUnitId,
                'confidence' => $confidence,
                'mapping_source' => $source,
            ]
        );
    }

    // ─── Auto-Map All ───────────────────────────────────────────────

    /**
     * Führt automatisches Mapping für alle Attribute mit source_system='etim' durch.
     *
     * @return array{feature_mappings: int, class_mappings: int}
     */
    public function autoMapFromSourceSystem(string $etimVersionId): array
    {
        $stats = ['feature_mappings' => 0, 'class_mappings' => 0];

        // Attribute mit source_system='etim' direkt mappen
        $etimAttributes = Attribute::where('source_system', 'etim')
            ->whereNotNull('source_attribute_key')
            ->get();

        foreach ($etimAttributes as $attribute) {
            $etimFeature = EtimFeature::where('etim_version_id', $etimVersionId)
                ->where('code', $attribute->source_attribute_key)
                ->first();

            if ($etimFeature) {
                $this->createFeatureMapping(
                    $attribute->id,
                    $etimFeature->id,
                    $etimVersionId,
                    'auto',
                    1.0,
                );
                $stats['feature_mappings']++;
            }
        }

        return $stats;
    }

    // ─── Resolve-Methoden für Export ─────────────────────────────────

    /**
     * Löst das ETIM-Klassen-Mapping für ein Produkt auf (über Kategorie-Hierarchie).
     */
    public function resolveClassMappingForProduct(
        string $hierarchyNodeId,
        string $etimVersionId,
    ): ?EtimClassMapping {
        // Direkte Zuordnung prüfen
        $mapping = EtimClassMapping::where('hierarchy_node_id', $hierarchyNodeId)
            ->where('etim_version_id', $etimVersionId)
            ->with('etimClass.etimVersion')
            ->first();

        if ($mapping) {
            return $mapping;
        }

        // Eltern-Knoten durchlaufen (Vererbung)
        $node = HierarchyNode::find($hierarchyNodeId);
        if ($node && $node->parent_node_id) {
            return $this->resolveClassMappingForProduct($node->parent_node_id, $etimVersionId);
        }

        return null;
    }

    /**
     * Lädt alle Feature-Mappings für eine ETIM-Version als Lookup.
     *
     * @return array<string, EtimFeatureMapping> attribute_id → mapping
     */
    public function getFeatureMappingLookup(string $etimVersionId): array
    {
        return EtimFeatureMapping::where('etim_version_id', $etimVersionId)
            ->with('etimFeature')
            ->get()
            ->keyBy('attribute_id')
            ->all();
    }

    // ─── Private Hilfsmethoden ──────────────────────────────────────

    /**
     * Berechnet die Namens-Ähnlichkeit zwischen zwei Strings.
     * Kombiniert similar_text() und Levenshtein für robuste Ergebnisse.
     */
    private function calculateNameSimilarity(string $a, string $b): float
    {
        if ($a === '' || $b === '') {
            return 0.0;
        }

        if ($a === $b) {
            return 1.0;
        }

        // similar_text Prozentsatz
        similar_text($a, $b, $percent);
        $simScore = $percent / 100;

        // Levenshtein (normalisiert)
        $maxLen = max(mb_strlen($a), mb_strlen($b));
        // Levenshtein hat Zeichenlimit, bei sehr langen Strings nur similar_text verwenden
        if ($maxLen <= 255) {
            $levDist = levenshtein($a, $b);
            $levScore = 1 - ($levDist / $maxLen);
            // Gewichteter Durchschnitt
            return ($simScore * 0.6) + ($levScore * 0.4);
        }

        return $simScore;
    }

    /**
     * Berechnet die Datentyp-Kompatibilität zwischen PIM und ETIM.
     */
    private function calculateDataTypeCompatibility(string $pimType, string $etimType): float
    {
        $map = [
            'Number' => ['numeric' => 1.0, 'range' => 0.7, 'alphanumeric' => 0.3],
            'Float' => ['numeric' => 1.0, 'range' => 0.7, 'alphanumeric' => 0.3],
            'Flag' => ['logical' => 1.0, 'alphanumeric' => 0.3],
            'String' => ['alphanumeric' => 1.0, 'numeric' => 0.2],
            'Selection' => ['alphanumeric' => 0.8, 'logical' => 0.5],
            'Dictionary' => ['alphanumeric' => 0.7],
            'Date' => ['alphanumeric' => 0.3],
            'Composite' => ['alphanumeric' => 0.3],
            'RichText' => ['alphanumeric' => 0.5],
        ];

        return $map[$pimType][$etimType] ?? 0.1;
    }

    /**
     * Berechnet die Einheiten-Ähnlichkeit.
     */
    private function calculateUnitSimilarity(Attribute $attribute, EtimFeature $feature): float
    {
        // Wenn beide keine Einheit haben, ist es kompatibel
        if (!$attribute->default_unit_id && !$feature->etim_unit_id) {
            return 0.8;
        }

        // Wenn nur einer eine Einheit hat
        if (!$attribute->default_unit_id || !$feature->etim_unit_id) {
            return 0.2;
        }

        // Unit-Mapping prüfen
        $unit = Unit::find($attribute->default_unit_id);
        if (!$unit) {
            return 0.2;
        }

        $etimUnit = $feature->etimUnit;
        if (!$etimUnit) {
            return 0.2;
        }

        // Abkürzung/Symbol vergleichen
        $unitSymbol = mb_strtolower($unit->symbol ?? $unit->technical_name ?? '');
        $etimAbbr = mb_strtolower($etimUnit->abbreviation ?? $etimUnit->name_en ?? '');

        if ($unitSymbol && $etimAbbr && $unitSymbol === $etimAbbr) {
            return 1.0;
        }

        // Name vergleichen
        $unitName = mb_strtolower($unit->name_de ?? '');
        $etimName = mb_strtolower($etimUnit->name_de ?? '');

        if ($unitName && $etimName) {
            return $this->calculateNameSimilarity($unitName, $etimName);
        }

        return 0.3;
    }
}

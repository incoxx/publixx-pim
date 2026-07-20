---
globs:
  - "app/Models/Attribute*.php"
  - "app/Models/HierarchyNode*.php"
  - "database/migrations/*attribute_mapping*"
  - "app/Services/Export/*Classification*"
---

# Klassifikationen als PIM-Bordmittel

Externe Klassifikationen werden **nicht als separate Systeme**
behandelt, sondern als native PIM-Objekte:

```
┌─────────────────────────────────────────────────────────────┐
│  1. Schema-Import (einmalig pro Standard)                   │
│                                                             │
│  Externe Klassifikations-Spezifikation                      │
│     → Output-Hierarchie  (Klassen/Kategorien als Knoten)    │
│     → Attribute           (Features mit source_system)       │
│     → Wertlisten          (Standard-Values als SelectionList)│
└────────────────────────┬────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────┐
│  2. Attribut-Mapping (global, Quelle → Ziel)                │
│                                                             │
│  Tabelle: attribute_mappings                                │
│  ┌──────────────────┬──────────────────┬──────────────────┐ │
│  │ source_attribute  │ target_attribute  │ target_hierarchy │ │
│  ├──────────────────┼──────────────────┼──────────────────┤ │
│  │ breite-mm        │ EF000007         │ <ziel>           │ │
│  │ gewicht-kg       │ EF000008         │ <ziel>           │ │
│  │ schutzart        │ EF000042         │ <ziel>           │ │
│  └──────────────────┴──────────────────┴──────────────────┘ │
│                                                             │
│  Wert-Sync (app/Services/Export/AttributeMappingService.php):│
│  1. Direkter Wert (output_hierarchy_id) → Override gewinnt  │
│  2. Mapping-Regel → Wert vom Quell-Attribut holen           │
│  3. Bedingte Regel → WENN Bedingung erfüllt DANN Zielwert   │
│     (überschreibt keinen direkten Override)                 │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────┐
│  3. Export = Bestehender BMEcat-Writer + Hierarchie-Auswahl │
│                                                             │
│  BMEcat mit hierarchy_id = <Zielklassifikation> → deren XML │
│  BMEcat mit hierarchy_id = Master → normaler BMEcat          │
│                                                             │
│  Formate sind Ausprägungen, keine separaten Systeme         │
└─────────────────────────────────────────────────────────────┘
```

## Vorhandene PIM-Features

| Feature | Mechanismus | Status |
|---------|------------|--------|
| Produkt in mehreren Klassifikationen | `master_hierarchy_node_id` + `OutputHierarchyProductAssignment` | **Existiert** |
| Format-spezifische Attribute | `Attribute.source_system` | **Existiert** |
| Attribut-Werte pro Klassifikation | `ProductAttributeValue.output_hierarchy_id` | **Existiert** |
| Attribut-Zuordnung pro Knoten | `HierarchyNodeAttributeAssignment` | **Existiert** |
| Attribut-Herkunft tracken | `source_system`, `source_attribute_name`, `source_attribute_key` | **Existiert** |
| Attribut-Mapping (Quelle → Ziel, mit Transform) | `attribute_mappings` Tabelle + `AttributeMappingService` | **Existiert** |
| Bedingte Mapping-Regeln | `AttributeMappingRule` | **Existiert** |
| Bulk-Mapping, Sync (Produkt/Batch/Bulk), Excel-Im-/Export | `AttributeMappingController` (`routes/api.php:932-942`) | **Existiert** |

## `attribute_mappings` Tabelle

```sql
CREATE TABLE attribute_mappings (
    id                   UUID PRIMARY KEY,
    source_hierarchy_id  UUID REFERENCES hierarchies(id),
    source_attribute_id  UUID REFERENCES attributes(id),
    target_hierarchy_id  UUID REFERENCES hierarchies(id),
    target_attribute_id  UUID REFERENCES attributes(id),

    -- Transformation
    transform_type       VARCHAR(50) DEFAULT 'direct',  -- direct, unit_convert, value_map
    transform_config     JSON NULL,                     -- {"factor": 0.1, "from_unit": "mm", "to_unit": "cm"}

    -- KI-Metadaten (optional, aktuell nur als Datenfelder — kein KI-Vorschlags-Endpoint)
    ai_suggested         BOOLEAN DEFAULT FALSE,
    ai_confidence        DECIMAL(3,2) NULL,
    ai_confirmed_by      UUID NULL REFERENCES users(id),
    ai_confirmed_at      TIMESTAMP NULL,

    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

(siehe `database/migrations/2026_03_23_000001_create_attribute_mappings_table.php`)

## Wert-Sync Logik

```php
// Beim Export: Wert für ein Ziel-Attribut ermitteln
public function resolveAttributeForExport(
    Product $product,
    Attribute $targetAttr,
    string $hierarchyId
): mixed {
    // 1. Direkter Wert? (manuell gepflegt, output_hierarchy_id-scoped)
    $directValue = $product->attributeValues
        ->where('attribute_id', $targetAttr->id)
        ->where('output_hierarchy_id', $hierarchyId)
        ->first();

    if ($directValue) {
        return $directValue;  // Manueller Override gewinnt immer
    }

    // 2. Mapping-Regel vorhanden?
    $mapping = AttributeMapping::where('target_attribute_id', $targetAttr->id)
        ->where('target_hierarchy_id', $hierarchyId)
        ->first();

    if ($mapping) {
        $sourceValue = $product->attributeValues
            ->where('attribute_id', $mapping->source_attribute_id)
            ->first();

        return $this->applyTransform($sourceValue, $mapping);
    }

    // 3. Kein Wert vorhanden
    return null;
}
```

Reale Implementierung: `App\Services\Export\AttributeMappingService::resolveForProduct()`.

## Nicht implementiert

Es gibt **keinen** KI-Vorschlags-Endpoint für Attribut-Mappings (kein `ai-suggest`-Route,
keine entsprechende Controller-Methode, keine KI-Anbindung). Die Felder `ai_suggested`/
`ai_confidence`/`ai_confirmed_by`/`ai_confirmed_at` existieren in der Tabelle und sind über
die normale CRUD-API (`AttributeMappingResource`, Store/Update-Requests) les- und schreibbar,
werden aber von keiner KI-Logik automatisch befüllt oder in der Wert-Sync-Auflösung ausgewertet.

---
globs:
  - "app/Models/Attribute*.php"
  - "app/Models/HierarchyNode*.php"
  - "database/migrations/*attribute_mapping*"
  - "app/Services/Export/*Classification*"
---

# Klassifikationen als PIM-Bordmittel

Externe Klassifikationen (ETIM, ONYX, GAEB, FABDIS) werden **nicht als separate Systeme**
behandelt, sondern als native PIM-Objekte:

```
┌─────────────────────────────────────────────────────────────┐
│  1. Schema-Import (einmalig pro Standard)                   │
│                                                             │
│  ETIM XML / ONYX / GAEB / FABDIS Spezifikation              │
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
│  │ source_attribute  │ target_attribute  │ output_hierarchy │ │
│  ├──────────────────┼──────────────────┼──────────────────┤ │
│  │ breite-mm        │ EF000007         │ etim             │ │
│  │ gewicht-kg       │ EF000008         │ etim             │ │
│  │ schutzart        │ EF000042         │ etim             │ │
│  └──────────────────┴──────────────────┴──────────────────┘ │
│                                                             │
│  Wert-Sync:                                                 │
│  1. Direkter Wert (output_hierarchy_id) → Override gewinnt  │
│  2. Mapping-Regel → Wert vom Quell-Attribut holen           │
│  3. Transform anwenden (direct, unit_convert, value_map)    │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────┐
│  3. Export = Bestehender BMEcat-Writer + Hierarchie-Auswahl │
│                                                             │
│  BMEcat mit hierarchy_id = ETIM → ETIM BMEcat               │
│  BMEcat mit hierarchy_id = Master → normaler BMEcat          │
│  GAEB mit hierarchy_id = GAEB → GAEB DA XML                 │
│                                                             │
│  Formate sind Ausprägungen, keine separaten Systeme         │
└─────────────────────────────────────────────────────────────┘
```

## Vorhandene PIM-Features

| Feature | Mechanismus | Status |
|---------|------------|--------|
| Produkt in mehreren Klassifikationen | `master_hierarchy_node_id` + `OutputHierarchyProductAssignment` | **Existiert** |
| Format-spezifische Attribute | `Attribute.source_system` = 'ETIM', 'ONYX', etc. | **Existiert** |
| Attribut-Werte pro Klassifikation | `ProductAttributeValue.output_hierarchy_id` | **Existiert** |
| Attribut-Zuordnung pro Knoten | `HierarchyNodeAttributeAssignment` | **Existiert** |
| Attribut-Herkunft tracken | `source_system`, `source_attribute_name`, `source_attribute_key` | **Existiert** |

## Missing Piece: `attribute_mappings` Tabelle

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

    -- KI-Metadaten (optional)
    ai_suggested         BOOLEAN DEFAULT FALSE,
    ai_confidence        DECIMAL(3,2) NULL,
    ai_confirmed_by      UUID NULL REFERENCES users(id),
    ai_confirmed_at      TIMESTAMP NULL,

    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

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
        ->where('output_hierarchy_id', $hierarchyId)
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

## KI-gestütztes Attribut-Mapping (optional, Claude API)

```php
// POST /api/v1/attribute-mappings/ai-suggest
// Request:
{
    "source_attributes": ["breite-mm", "gewicht-kg", "schutzart"],
    "output_hierarchy_id": "uuid-etim-hierarchy",
    "target_node_id": "uuid-etim-EC001234"
}

// Response:
{
    "suggestions": [
        {
            "source": "breite-mm",
            "target": "EF000007",
            "confidence": 0.95,
            "reason": "Gleicher Datentyp (Number), gleiche Einheit (mm), semantisch: Breite"
        },
        {
            "source": "schutzart",
            "target": "EF000042",
            "confidence": 0.88,
            "reason": "Selection-Typ, Werte IP55/IP44 matchen ETIM-Werteliste"
        }
    ]
}
```

Die KI erhält Quell-Attribute (Name, Datentyp, Einheit, Beispielwerte) + Ziel-Attribute
aus der Klassifikation und schlägt Zuordnungen vor. Der Nutzer bestätigt in der Tabellen-GUI.

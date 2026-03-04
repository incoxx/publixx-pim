---
title: Datenmodell
---

# Datenmodell

Das Datenmodell von Publixx PIM umfasst **35 Tabellen**, die in neun fachliche Domaenen gegliedert sind. Alle Tabellen verwenden **UUID-Primaerschluessel** fuer global eindeutige Identifikatoren und nutzen an zahlreichen Stellen **JSON-Spalten** fuer flexible, schemaunabhaengige Datenstrukturen.

## Domaenenuebersicht

### Attributmodell (10 Tabellen)

Das Attributmodell bildet das Rueckgrat der EAV-Architektur. Es definiert, welche Eigenschaften Produkte besitzen koennen und wie diese strukturiert sind.

| Tabelle | Beschreibung |
|---|---|
| `attributes` | Attributdefinitionen mit Typ, Validierungsregeln, Sortierung und Mehrsprachigkeitseinstellungen |
| `attribute_groups` | Logische Gruppierung von Attributen (z.B. "Technische Daten", "Marketing-Texte") |
| `attribute_group_attribute` | Zuordnung von Attributen zu Gruppen (n:m) |
| `attribute_options` | Vordefinierte Auswahlmoeglichkeiten fuer Select- und Multiselect-Attribute |
| `attribute_views` | Sichtbarkeitsdefinitionen: Welche Teilmenge von Attributen eine Benutzerrolle sehen darf |
| `attribute_view_attribute` | Zuordnung von Attributen zu Views (n:m) |
| `attribute_collections` | Definition von Collection-Attributgruppen (wiederholbare Attributgruppen) |
| `attribute_collection_entries` | Einzelne Eintraege innerhalb einer Collection-Instanz |
| `attribute_validations` | Erweiterte Validierungsregeln pro Attribut (Regex, Min/Max, Pflichtfeld) |
| `attribute_translations` | Uebersetzungen fuer Attributnamen und Beschreibungen |

### Produktmodell (6 Tabellen)

| Tabelle | Beschreibung |
|---|---|
| `products` | Kernentitaet mit Referenz auf Elternprodukt (Varianten), Status und Metadaten |
| `product_attribute_values` | Zentrale EAV-Werttabelle: Verknuepft Produkt, Attribut und Wert mit Sprachbezug |
| `product_variants` | Zuordnungstabelle fuer die Eltern-Varianten-Beziehung mit Vererbungsregeln |
| `product_variant_rules` | Pro-Attribut-Regeln fuer die Varianten-Vererbung (inherit/override) |
| `product_versions` | Versionierungsinformationen fuer Produkte (Aenderungshistorie) |
| `product_collection_values` | Werte innerhalb von Collection-Instanzen pro Produkt |

### Hierarchiemodell (4 Tabellen)

| Tabelle | Beschreibung |
|---|---|
| `hierarchies` | Hierarchie-Definitionen (Master-Hierarchie, Ausgabe-Hierarchien) mit Typ und Metadaten |
| `hierarchy_nodes` | Einzelne Knoten innerhalb einer Hierarchie, mit `parent_id` fuer die Baumstruktur |
| `hierarchy_node_product` | Zuordnung von Produkten zu Hierarchieknoten (n:m) |
| `hierarchy_node_attribute` | Zuordnung von Attributen zu Knoten fuer die Hierarchie-Vererbung, inkl. `dont_inherit`-Flag |

### Medienmodell (2 Tabellen)

| Tabelle | Beschreibung |
|---|---|
| `media` | Mediendateien mit Pfad, MIME-Typ, Dateigroesse und Metadaten (JSON) |
| `mediables` | Polymorphe Zuordnungstabelle: Verknuepft Medien mit Produkten, Attributen oder Knoten |

### Preismodell (2 Tabellen)

| Tabelle | Beschreibung |
|---|---|
| `price_lists` | Preislisten-Definitionen mit Waehrung, Gueltigkeitszeitraum und Prioritaet |
| `product_prices` | Preiszuordnungen: Produkt-Preisliste-Kombination mit Netto-/Bruttopreis |

### Export und PXF (2 Tabellen)

| Tabelle | Beschreibung |
|---|---|
| `export_templates` | PXF-Export-Templates mit Kanalzuordnung und Mapping-Konfiguration (JSON) |
| `export_mappings` | Einzelne Feld-Mappings innerhalb eines Templates: Quell-Attribut zu Ziel-Feldname |

### Import (2 Tabellen)

| Tabelle | Beschreibung |
|---|---|
| `import_jobs` | Import-Auftraege mit Status, Dateireferenz, Fortschritt und Fehlerprotokoll (JSON) |
| `import_mappings` | Spaltenzuordnungen zwischen Excel-Spalten und Systemattributen inkl. Fuzzy-Match-Score |

### Benutzerverwaltung (5 Tabellen)

| Tabelle | Beschreibung |
|---|---|
| `users` | Benutzerkonten mit Profilinformationen und Einstellungen |
| `roles` | Rollendefinitionen (Spatie Permission) |
| `permissions` | Einzelberechtigungen (Spatie Permission) |
| `model_has_roles` | Zuordnung von Rollen zu Benutzern |
| `model_has_permissions` | Direkte Zuordnung von Berechtigungen zu Benutzern oder Rollen |

### Performance (1 Tabelle)

| Tabelle | Beschreibung |
|---|---|
| `products_search_index` | Materialisierter Suchindex mit denormalisierten Produktdaten, FULLTEXT-Indizes und voraggregierten Attributwerten |

### System (1 Tabelle)

| Tabelle | Beschreibung |
|---|---|
| `system_settings` | Schluessel-Wert-Speicher fuer globale Systemkonfigurationen (JSON-Werte) |

---

## Das EAV-Muster im Detail

Die zentrale Designentscheidung von Publixx PIM ist das **Entity-Attribute-Value-Muster**. Die folgende Darstellung zeigt die drei Kernentitaeten und ihre Beziehungen.

<svg viewBox="0 0 880 520" xmlns="http://www.w3.org/2000/svg" style="max-width:100%;height:auto;margin:2rem auto;display:block;">
  <defs>
    <filter id="erShadow" x="-3%" y="-3%" width="108%" height="112%">
      <feDropShadow dx="1" dy="2" stdDeviation="2" flood-opacity="0.1"/>
    </filter>
    <marker id="erArrow" markerWidth="12" markerHeight="8" refX="12" refY="4" orient="auto">
      <polygon points="0 0, 12 4, 0 8" fill="#64748b"/>
    </marker>
    <marker id="erDiamond" markerWidth="14" markerHeight="10" refX="0" refY="5" orient="auto">
      <polygon points="7 0, 14 5, 7 10, 0 5" fill="#64748b"/>
    </marker>
  </defs>

  <!-- Entity: products -->
  <rect x="30" y="40" width="240" height="220" rx="8" fill="white" stroke="#3b82f6" stroke-width="2" filter="url(#erShadow)"/>
  <rect x="30" y="40" width="240" height="40" rx="8" fill="#3b82f6"/>
  <rect x="30" y="72" width="240" height="8" fill="#3b82f6"/>
  <text x="150" y="66" text-anchor="middle" fill="white" font-size="14" font-weight="bold">products</text>
  <text x="45" y="104" fill="#1e293b" font-size="12" font-family="monospace">id         UUID PK</text>
  <text x="45" y="124" fill="#1e293b" font-size="12" font-family="monospace">parent_id  UUID FK null</text>
  <text x="45" y="144" fill="#1e293b" font-size="12" font-family="monospace">sku        VARCHAR unique</text>
  <text x="45" y="164" fill="#1e293b" font-size="12" font-family="monospace">status     ENUM</text>
  <text x="45" y="184" fill="#1e293b" font-size="12" font-family="monospace">metadata   JSON</text>
  <text x="45" y="204" fill="#1e293b" font-size="12" font-family="monospace">created_at TIMESTAMP</text>
  <text x="45" y="224" fill="#1e293b" font-size="12" font-family="monospace">updated_at TIMESTAMP</text>

  <!-- Entity: attributes -->
  <rect x="590" y="40" width="260" height="260" rx="8" fill="white" stroke="#059669" stroke-width="2" filter="url(#erShadow)"/>
  <rect x="590" y="40" width="260" height="40" rx="8" fill="#059669"/>
  <rect x="590" y="72" width="260" height="8" fill="#059669"/>
  <text x="720" y="66" text-anchor="middle" fill="white" font-size="14" font-weight="bold">attributes</text>
  <text x="605" y="104" fill="#1e293b" font-size="12" font-family="monospace">id              UUID PK</text>
  <text x="605" y="124" fill="#1e293b" font-size="12" font-family="monospace">code            VARCHAR unique</text>
  <text x="605" y="144" fill="#1e293b" font-size="12" font-family="monospace">type            ENUM</text>
  <text x="605" y="164" fill="#1e293b" font-size="12" font-family="monospace">is_translatable BOOLEAN</text>
  <text x="605" y="184" fill="#1e293b" font-size="12" font-family="monospace">is_required     BOOLEAN</text>
  <text x="605" y="204" fill="#1e293b" font-size="12" font-family="monospace">validation      JSON</text>
  <text x="605" y="224" fill="#1e293b" font-size="12" font-family="monospace">sort_order      INTEGER</text>
  <text x="605" y="244" fill="#1e293b" font-size="12" font-family="monospace">created_at      TIMESTAMP</text>
  <text x="605" y="264" fill="#1e293b" font-size="12" font-family="monospace">updated_at      TIMESTAMP</text>

  <!-- Entity: product_attribute_values -->
  <rect x="220" y="340" width="420" height="170" rx="8" fill="white" stroke="#d97706" stroke-width="2" filter="url(#erShadow)"/>
  <rect x="220" y="340" width="420" height="40" rx="8" fill="#d97706"/>
  <rect x="220" y="372" width="420" height="8" fill="#d97706"/>
  <text x="430" y="366" text-anchor="middle" fill="white" font-size="14" font-weight="bold">product_attribute_values</text>
  <text x="240" y="404" fill="#1e293b" font-size="12" font-family="monospace">id            UUID PK</text>
  <text x="240" y="424" fill="#1e293b" font-size="12" font-family="monospace">product_id    UUID FK  ──&gt; products.id</text>
  <text x="240" y="444" fill="#1e293b" font-size="12" font-family="monospace">attribute_id  UUID FK  ──&gt; attributes.id</text>
  <text x="240" y="464" fill="#1e293b" font-size="12" font-family="monospace">locale        VARCHAR  (z.B. "de", "en")</text>
  <text x="240" y="484" fill="#1e293b" font-size="12" font-family="monospace">value         JSON     (flexibler Werttyp)</text>

  <!-- Relationship Lines -->
  <!-- products -> product_attribute_values -->
  <line x1="150" y1="260" x2="150" y2="380" stroke="#64748b" stroke-width="2"/>
  <line x1="150" y1="380" x2="218" y2="380" stroke="#64748b" stroke-width="2" marker-end="url(#erArrow)"/>
  <text x="100" y="320" fill="#64748b" font-size="12" font-weight="bold">1</text>
  <text x="195" y="373" fill="#64748b" font-size="12" font-weight="bold">n</text>

  <!-- attributes -> product_attribute_values -->
  <line x1="720" y1="300" x2="720" y2="410" stroke="#64748b" stroke-width="2"/>
  <line x1="720" y1="410" x2="642" y2="410" stroke="#64748b" stroke-width="2" marker-end="url(#erArrow)"/>
  <text x="730" y="340" fill="#64748b" font-size="12" font-weight="bold">1</text>
  <text x="650" y="403" fill="#64748b" font-size="12" font-weight="bold">n</text>

  <!-- Self-referencing: products.parent_id -->
  <path d="M 30 120 C -20 120, -20 180, 30 180" stroke="#64748b" stroke-width="2" fill="none" stroke-dasharray="5,3" marker-end="url(#erArrow)"/>
  <text x="-10" y="155" fill="#64748b" font-size="10" text-anchor="middle">parent</text>

  <!-- Labels -->
  <rect x="310" y="290" width="250" height="30" rx="5" fill="#fef3c7" stroke="#f59e0b" stroke-width="1"/>
  <text x="435" y="310" text-anchor="middle" fill="#92400e" font-size="11" font-weight="bold">Entity-Attribute-Value Zuordnung</text>
</svg>

### Funktionsweise

Die drei Tabellen arbeiten wie folgt zusammen:

1. **`products`** speichert die Kernidentitaet eines Produkts (SKU, Status, Elternreferenz fuer Varianten). Die Tabelle enthaelt bewusst keine produktspezifischen Spalten wie "Farbe" oder "Gewicht".

2. **`attributes`** definiert alle moeglichen Produkteigenschaften: ihren Code (Maschinenname), Typ (text, number, select, ...), ob sie uebersetzbar sind und welche Validierungsregeln gelten.

3. **`product_attribute_values`** verbindet beide: Jede Zeile stellt die Zuweisung eines konkreten Werts fuer ein bestimmtes Attribut an ein bestimmtes Produkt dar. Die Spalte `locale` ermoeglicht mehrsprachige Werte, die Spalte `value` speichert den eigentlichen Inhalt als JSON-Typ, was Flexibilitaet hinsichtlich der Datentypen bietet.

### Abfragebeispiel

```sql
-- Alle deutschsprachigen Werte eines Produkts laden
SELECT a.code, a.type, pav.value
FROM product_attribute_values pav
JOIN attributes a ON a.id = pav.attribute_id
WHERE pav.product_id = '550e8400-e29b-41d4-a716-446655440000'
  AND pav.locale = 'de';
```

Da diese Art von Query bei vielen Attributen zahlreiche JOINs erfordert, bietet der materialisierte Suchindex eine performante Alternative fuer Lese- und Suchanfragen.

---

## UUID-Primaerschluessel

Alle Tabellen verwenden UUIDs (`CHAR(36)`) als Primaerschluessel. Die Entscheidung fuer UUIDs basiert auf folgenden Ueberlegungen:

- **Konfliktfreiheit**: IDs koennen clientseitig generiert werden, ohne Absprache mit der Datenbank
- **Sicherheit**: UUIDs sind nicht vorhersagbar, im Gegensatz zu autoinkrementellen Ganzzahlen
- **Verteilbarkeit**: Bei zukuenftiger horizontaler Skalierung entstehen keine ID-Konflikte
- **Referenzstabilitaet**: IDs bleiben ueber Export/Import-Zyklen hinweg stabil

MySQL 8 unterstuetzt UUIDs effizient ueber `BINARY(16)`-Speicherung mit `UUID_TO_BIN()`-Funktionen, was den Speicherverbrauch und die Indexperformance optimiert.

---

## JSON-Spalten

Mehrere Tabellen nutzen MySQL-JSON-Spalten fuer halbstrukturierte Daten:

| Tabelle | Spalte | Verwendung |
|---|---|---|
| `product_attribute_values` | `value` | Flexibler Werttyp: String, Zahl, Array, Objekt |
| `products` | `metadata` | Produktbezogene Metadaten (Quellsystem, Import-Informationen) |
| `attributes` | `validation` | Validierungsregeln als JSON-Schema |
| `export_templates` | `config` | Template-Konfiguration mit Feldmappings |
| `import_jobs` | `error_log` | Strukturiertes Fehlerprotokoll |
| `system_settings` | `value` | Beliebige Konfigurationswerte |

MySQL 8 erlaubt Indizierung einzelner JSON-Pfade ueber generierte Spalten, was gezielte Abfragen auf JSON-Inhalte performant macht.

---

## Materialisierter Suchindex

Die Tabelle `products_search_index` ist eine denormalisierte Darstellung der Produktdaten, die fuer schnelle Lese- und Suchoperationen optimiert ist.

### Aufbau

```sql
CREATE TABLE products_search_index (
    product_id    CHAR(36) PRIMARY KEY,
    sku           VARCHAR(255),
    status        VARCHAR(50),
    hierarchy_path TEXT,
    searchable_text TEXT,          -- Aggregierter Volltext aller Attributwerte
    filter_data   JSON,            -- Facettierbare Attributwerte
    sort_data     JSON,            -- Vorberechnete Sortierwerte
    created_at    TIMESTAMP,
    updated_at    TIMESTAMP,
    FULLTEXT INDEX ft_search (searchable_text)
) ENGINE=InnoDB;
```

### Aktualisierungsstrategie

Der Index wird **ereignisgesteuert** aktualisiert:

1. Bei jeder Aenderung an `product_attribute_values` wird ein `ProductValueChanged`-Event ausgeloest
2. Ein Listener prueft, ob der betroffene Wert im Suchindex enthalten ist
3. Falls ja, wird ein Queue-Job zur Neuberechnung des Index-Eintrags erstellt
4. Der Job aggregiert alle relevanten Werte und aktualisiert die entsprechende Zeile

Bei Massenoperationen (Import, Batch-Updates) wird die Aktualisierung gebatcht, um die Datenbank nicht mit einzelnen Updates zu ueberlasten.

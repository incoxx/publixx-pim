# 25 — Attribut-Metadaten (Data Quality & Ownership)

Frei definierbare Metadaten-Felder an Attributdefinitionen — z. B. **Datenherkunft**,
**Dateneigentümer**, **Datenverbindung**. Sie beantworten die Governance-Fragen, die das
technische Attributmodell offen lässt: Woher kommen die Daten, wer ist zuständig, über
welchen Weg werden sie befüllt.

## Abgrenzung zu `attributes.source_system`

| | `source_system` & Co. | Attribut-Metadaten |
|---|---|---|
| Gepflegt von | Importern (`bmecat`, `anypim-sync`, Excel-Spalte R) | Anwendern in der GUI |
| Bedeutung | technische Import-Provenienz | fachliche Governance |
| Struktur | drei feste `varchar`-Spalten | frei definierbare Felder mit Typ |
| UI | keine | Menüpunkt *Attribute → Metadaten* |

Die beiden Konzepte werden bewusst **nicht** vermischt. `source_system` bleibt unverändert.

## Datenmodell

### `attribute_metadata_definitions`

| Spalte | Typ | Bedeutung |
|---|---|---|
| `id` | `char(36)` | UUID |
| `technical_name` | `varchar(100)` unique | Schlüssel der Metadaten-Map — **nach dem Anlegen unveränderlich** |
| `name_de` / `name_en` | `varchar(255)` | Feldlabel im Attribut-Panel |
| `description` | `text` | Hinweistext unter dem Feld |
| `value_type` | `varchar(30)` | siehe unten |
| `options` | `json` | Auswahloptionen, Format wie `attributes.simple_options` (`Label::Wert`) |
| `is_required` | `boolean` | Pflichtfeld beim Speichern über das Panel |
| `sort_order` | `integer` | Reihenfolge im Formular |

`value_type` ist bewusst **kein DB-`enum`**: `attributes.data_type` ist eines und musste
deshalb mehrfach per `ALTER TABLE` (mit MySQL/SQLite-Doppelstrategie) erweitert werden.
Einzige Wahrheitsquelle ist stattdessen `AttributeMetadataDefinition::VALUE_TYPES`:

```
text · textarea · number · date · boolean · select · multiselect · url · email
```

Alle Typen bilden 1:1 auf bereits vorhandene `PimAttributeInput`-Typen ab.

### `attribute_metadata_values`

| Spalte | Typ | Bedeutung |
|---|---|---|
| `id` | `char(36)` | UUID |
| `attribute_id` | `char(36)` FK → `attributes`, `ON DELETE CASCADE` | |
| `definition_id` | `char(36)` FK → `attribute_metadata_definitions`, `ON DELETE CASCADE` | |
| `value` | `text` | skalare Werte |
| `value_json` | `json` | nur `multiselect` |

`unique(attribute_id, definition_id)` — je Attribut und Definition höchstens ein Wert.
Es entstehen **keine Leerzeilen**: ein geleerter Wert löscht die Zeile.

Bewusst nicht das große EAV-Schema aus `product_attribute_values`: dort erzwingen
Sortierung, Filterung, Einheiten und Sprachen die typisierten Spalten. Metadaten sind
Governance-Stammdaten mit sehr kleiner Kardinalität. Trotzdem eine eigene Zeilenform statt
eines JSON-Blobs auf `attributes` — der FK erhält Löschkaskade, `dependencies`-Zähler und
Unique-Constraint und macht eine spätere Auswertung („welche Attribute haben keinen
Eigentümer") mit einem einfachen `LEFT JOIN` möglich.

## API

### Definitionen

```
GET    /api/v1/attribute-metadata-definitions
POST   /api/v1/attribute-metadata-definitions
GET    /api/v1/attribute-metadata-definitions/{id}
PUT    /api/v1/attribute-metadata-definitions/{id}
DELETE /api/v1/attribute-metadata-definitions/{id}[?force=true]
GET    /api/v1/attribute-metadata-definitions/{id}/dependencies
```

Löschen einer belegten Definition liefert **409** samt Anzahl betroffener Attribute;
`?force=true` löscht Definition und Werte, lässt die Attribute unversehrt.

### Werte am Attribut

Die Werte reisen als flache Map `technical_name => Wert` im **Attribut-Payload** mit — kein
eigener Endpoint. Grund: das Panel hat einen Speichern-Button, beim Anlegen existiert noch
keine Attribut-ID, und Validierungsfehler müssen im selben 422 landen.

```jsonc
// POST/PUT /api/v1/attributes/{id}
{
  "name_de": "Gewicht",
  "metadata": {
    "datenherkunft":    "ERP",
    "dateneigentuemer": "Produktmanagement",
    "datenverbindung":  ["SAP", "Manuell"]   // multiselect
  }
}
```

Lesen nur mit Include (verhindert N+1 in der Liste):

```
GET /api/v1/attributes/{id}?include=metadataValues
→ { "data": { …, "metadata": { "datenherkunft": "ERP" } } }
```

### Zwei Regeln, die man kennen muss

1. **Abwesenheit ≠ Leerung.** Fehlt der `metadata`-Key im Payload, bleiben alle Werte
   unangetastet. `PUT /attributes/{id}` wird auch mit Teil-Payloads aufgerufen (etwa nur
   `parent_attribute_id` beim Composite-Handling); ohne diese Regel würden Massenoperationen
   und Importer die Governance-Daten stillschweigend löschen. Ein im Payload enthaltener
   Key mit leerem Wert löscht dagegen gezielt die Zeile.
2. **`is_required` greift nur, wenn der `metadata`-Key mitgeschickt wird.** Sonst würden
   BMEcat-Import, `anypim-sync` und `bulkUpdate` sofort brechen, sobald irgendwo ein
   Pflicht-Metadatum definiert wird. Konsequenz: importierte Attribute können Pflichtfelder
   vermissen — genau das soll eine spätere Data-Quality-Auswertung sichtbar machen.

## Berechtigungen

`attribute-metadata.view|create|edit|delete`

- Vollzugriff: Sysadmin, Admin, Data Steward
- nur `.view`: Product Manager, Export Manager, API Designer, Marketing, Viewer

**Werte schreiben erfordert nur `attributes.edit`.** Die Werte sind Teil des
Attribut-Payloads; eine zusätzliche Anforderung würde jedem Product Manager beim Speichern
eines Attributs einen Fehler bescheren. `attribute-metadata.view` steuert im Frontend
lediglich die Sichtbarkeit des Menüpunkts.

## Frontend

| Ort | Datei |
|---|---|
| Menüpunkt (letzter Eintrag der Gruppe *Attribute*) | `components/layout/AppSidebar.vue` |
| Liste | `views/attributeMetadata/AttributeMetadataView.vue` |
| Anlegen/Bearbeiten | `components/panels/AttributeMetadataFormPanel.vue` |
| Wertepflege | `components/panels/AttributeFormPanel.vue` (Abschnitt *Metadaten*) |

Die Metadatenfelder werden als zusätzliche Einträge an das `fields`-Array von `PimForm`
angehängt, mit Key-Präfix `meta__`. Beim Speichern werden sie über die Definitionen (nicht
über die Payload-Keys) wieder heraussortiert — nur so geht ein geleertes Feld als `null`
mit und löscht die Zeile.

**Fallstrick:** `PimForm` klont `modelValue` einmalig beim Mount. Deshalb lädt
`AttributeAdminView` die Liste mit `include=…,metadataValues`, sodass die Werte beim Öffnen
des Panels bereits synchron vorliegen. Ein `watch` in `PimForm` ist keine Option — die
Komponente wird von rund 20 Panels genutzt und würde dort Nutzereingaben überschreiben.

## Bekannte Lücken

- **JSON-Konfig-Export/-Import** (`JsonFormatExporter` / `JsonFormatImporter`) kennt die
  Metadaten noch nicht. Ein Konfig-Transfer zwischen Installationen überträgt sie daher
  nicht. Zuschnitt für später: neue Sektion `attribute_metadata_definitions` vor
  `attributes` in `SECTION_ORDER`, plus ein `metadata`-Key je Attribut.
- **Filter und Data-Quality-Report** (z. B. „alle Attribute ohne Eigentümer") sind noch
  nicht umgesetzt; das Schema ist darauf vorbereitet.
- **`value_type: user`** (Eigentümer als echte PIM-Benutzerreferenz) fehlt bewusst: die
  Benutzerliste hängt an `users.view`, das die Read-Only-Rollen nicht haben. `select` mit
  gepflegten Optionen deckt den Fall ab.
- **BMEcat- und Excel-Import** übertragen keine Metadaten.

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

### Filtern

```
GET /api/v1/attributes?filter[meta:datenherkunft]=ERP
```

| Wert | Bedeutung |
|---|---|
| konkreter Wert | bei gepflegten Optionen exakt, bei `multiselect` „enthält" (`whereJsonContains`), bei Freitext „beginnt mit" |
| `__none__` | Attribute **ohne** Wert für diese Definition — der Data-Quality-Hebel |
| `__any__` | Attribute mit irgendeinem Wert |

Freitext beginnt-mit statt exakt, damit das Quick-Lookup-Feld in der Attributliste
benutzbar ist; die Textspalten daneben (`technical_name`, `name_de`) verhalten sich
genauso.

Mehrere Metadaten-Filter werden UND-verknüpft. Ein unbekannter technischer Name wird
still ignoriert, damit ein veralteter Filter im Frontend die Liste nicht mit einem Fehler
blockiert.

Metadaten stehen auch im **Quick Lookup** der Attributliste zur Verfügung. Dessen
Konfiguration wird aus den sichtbaren Spalten abgeleitet (`quickLookupFor()` in
`AttributeAdminView.vue`), damit jede Spalte — und damit jedes neu angelegte Metadatum —
automatisch ein Eingabefeld bekommt.

**Wichtig für neue Listenfilter:** `AttributeController::applyAllFilters()` ist der einzige
Ort, an dem Filter angewendet werden; `index()` und `allIds()` rufen ihn gemeinsam auf.
Wird ein Filter nur an einer der beiden Stellen ergänzt, markiert „Alle N auswählen" eine
andere Menge als die Liste zeigt — und eine anschließende Massenoperation trifft die
falschen Attribute. Ein datengetriebener Test in `AttributeMetadataValueTest` vergleicht
beide Mengen je Filter.

### Massenbearbeitung

`PUT /api/v1/attributes/bulk-update` nimmt die Metadaten unter `fields.metadata`:

```jsonc
{
  "ids": ["…", "…"],
  "fields": {
    "is_searchable": true,
    "metadata": { "datenherkunft": "ERP", "dateneigentuemer": "" }
  }
}
```

Ein **leerer Wert löscht** das Metadatum auf allen gewählten Attributen — im Dialog wird
das vor dem Anwenden ausdrücklich benannt. Ein Payload nur mit `metadata` ist zulässig.
Die Werte lassen sich nicht per Massen-`UPDATE` setzen (eigene Zeilen), deshalb läuft der
Abgleich je Attribut in Chunks à 500.

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

Drei Stellen müssen zusammenpassen, sonst driften Neu- und Bestandsinstallation
auseinander oder die Rechte sind in der Rollen-UI nicht sinnvoll zuweisbar:

| Stelle | Zweck |
|---|---|
| `database/migrations/2026_08_16_000003_add_attribute_metadata_permissions.php` | additiv für Bestandsinstallationen |
| `database/seeders/RoleAndPermissionSeeder.php` | Neuinstallationen |
| `app/Http/Controllers/Api/V1/PermissionController.php` | Label und Gruppe für den Rollen-Editor |

Sysadmin und Admin erhalten die Rechte automatisch über `Permission::all()`, Viewer über
den `%.view`-Filter im Seeder — dort ist nichts zu ergänzen. Ohne den Eintrag im
`PermissionController` landen die Rechte in der Rollen-UI unter „Sonstige" mit rohem
Schlüssel; ein Feature-Test sichert das ab.

## Frontend

| Ort | Datei |
|---|---|
| Menüpunkt (letzter Eintrag der Gruppe *Attribute*) | `components/layout/AppSidebar.vue` |
| Liste | `views/attributeMetadata/AttributeMetadataView.vue` |
| Anlegen/Bearbeiten | `components/panels/AttributeMetadataFormPanel.vue` |
| Wertepflege | `components/panels/AttributeFormPanel.vue` (Abschnitt *Metadaten*) |
| Spalten in der Attributliste | `views/attributes/AttributeAdminView.vue` + `ColumnConfigPopover` |
| Massenbearbeitung | `components/attributes/AttributeBulkUpdateDialog.vue` |

Metadaten erscheinen als **dynamische, standardmäßig ausgeblendete Spalten** in der
Attributliste und werden über das vorhandene `useColumnConfig` (localStorage) ein- und
ausgeblendet. Die Spaltenschlüssel lauten `metadata.<technical_name>`; damit sie einen
Reload überleben, kennt `useColumnConfig` neben `attributes.` auch das Präfix `metadata.`.
Die Darstellung läuft über `col.render()` von `PimTable` — nur so werden Mehrfachauswahl
(Liste), `boolean` (Ja/Nein) und leere Werte (`—`) korrekt angezeigt und bei
`Label::Wert`-Optionen das Label statt des Rohwerts.

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
- **Ein zusammenfassender Data-Quality-Report** (Vollständigkeitsquote je Definition über
  den gesamten Bestand) fehlt noch. Die Einzelabfrage „welche Attribute haben keinen
  Eigentümer" ist über `filter[meta:…]=__none__` bereits möglich.
- **`value_type: user`** (Eigentümer als echte PIM-Benutzerreferenz) fehlt bewusst: die
  Benutzerliste hängt an `users.view`, das die Read-Only-Rollen nicht haben. `select` mit
  gepflegten Optionen deckt den Fall ab.
- **BMEcat- und Excel-Import** übertragen keine Metadaten.

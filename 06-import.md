# Publixx PIM — Excel-Import

> **Zweck:** Excel-basierter Datenimport. Verwende diesen Skill beim Implementieren der Import-Engine, Validierung, Template-Generierung und Import-UI.

---

## Prinzip

Daten kommen aus Fachbereichen ohne JSON-Kenntnisse. Excel ist das universelle Austauschformat. Ein zentrales Template mit 14 Reitern — nicht alle müssen befüllt werden, jeder Reiter kann einzeln importiert werden.

---

## Template: 14 Reiter

| Nr | Reiter | Zweck | Abhängig von | Quelle |
|----|--------|-------|-------------|--------|
| 01 | Produkttypen | Typen definieren | - | PIM-Team |
| 02 | Attributgruppen | Attributtypen | - | Data Steward |
| 03 | Einheiten | Gruppen + Einheiten | - | Data Steward |
| 04 | Wertelisten | Listen + Werte | - | Data Steward |
| 05 | Attribute | Attributdefinitionen | 02, 03, 04 | Data Steward |
| 06 | Hierarchien | Baumstrukturen | - | Data Steward |
| 07 | Hierarchie_Attribute | Attribut → Knoten | 05, 06 | Data Steward |
| 08 | Produkte | Produkte anlegen | 01 | PM |
| 09 | Produktwerte | Attributwerte | 05, 08 | PM |
| 10 | Varianten | Varianten | 08 | PM |
| 11 | Produkt_Hierarchien | Produkt → Knoten | 06, 08 | PM |
| 12 | Produktbeziehungen | Zubehör etc. | 08 | PM |
| 13 | Preise | Preise | 08 | Vertrieb |
| 14 | Medien | Medien-Zuordnung | 08 | Marketing |

---

## Sheet-Spezifikationen (Spalten)

### 05_Attribute (Kern-Reiter)

| Spalte | Header | Pflicht | Typ | Beschreibung |
|--------|--------|---------|-----|--------------|
| A | Technischer Name* | Ja | String | Eindeutig |
| B | Name (Deutsch)* | Ja | String | Anzeigename |
| C | Name (Englisch) | Nein | String | |
| D | Beschreibung | Nein | Text | |
| E | Datentyp* | Ja | Enum | String/Number/Float/Date/Flag/Selection/Dictionary/Collection |
| F | Attributgruppe | Nein | String | Techn. Name aus Reiter 02 |
| G | Werteliste | Nein | String | Techn. Name aus Reiter 04 |
| H | Einheitengruppe | Nein | String | Techn. Name aus Reiter 03 |
| I | Standard-Einheit | Nein | String | Kürzel (z.B. kg) |
| J | Vermehrbar | Nein | Ja/Nein | |
| K | Max. Vermehrungen | Nein | Zahl | |
| L | Übersetzbar | Nein | Ja/Nein | |
| M | Pflicht | Nein | Optional/Pflicht | |
| N | Eindeutig | Nein | Ja/Nein | |
| O | Suchbar | Nein | Ja/Nein | |
| P | Vererbbar | Nein | Ja/Nein | |
| Q | Übergeordnetes Attribut | Nein | String | Techn. Name Eltern-Attribut |
| R | Quellsystem | Nein | String | PIM/SAP ERP/Other |
| S | Sichten | Nein | String | Kommasepariert |

### 06_Hierarchien

| Spalte | Header | Beschreibung |
|--------|--------|--------------|
| A | Hierarchie* | Technischer Name |
| B | Typ* | master / output |
| C-H | Ebene 1-6 | Tiefste befüllte Spalte = der Knoten |

### 08_Produkte

| Spalte | Header | Pflicht | Beschreibung |
|--------|--------|---------|--------------|
| A | SKU* | Ja | Artikelnummer (Identifier) |
| B | Produktname* | Ja | |
| C | Produktname (EN) | Nein | |
| D | Produkttyp* | Ja | Techn. Name |
| E | EAN | Nein | Nur bei physischen Produkten |
| F | Status | Nein | draft/active/inactive |

### 09_Produktwerte

| Spalte | Header | Pflicht | Beschreibung |
|--------|--------|---------|--------------|
| A | SKU* | Ja | Produktreferenz |
| B | Attribut* | Ja | Techn. Name ODER Anzeigename |
| C | Wert* | Ja | Passend zum Datentyp |
| D | Einheit | Nein | Kürzel (nur numerisch) |
| E | Sprache | Nein | ISO-Code (nur übersetzbar) |
| F | Index | Nein | Bei vermehrbaren Attributen |

---

## Import-Ablauf (3 Phasen)

### Phase 1: Upload
```
POST /api/v1/imports (multipart/form-data: file)
→ Datei speichern
→ Sheets erkennen
→ Status: "uploaded"
```

### Phase 2: Validierung
```
GET /api/v1/imports/{id} (automatisch nach Upload)
→ Schema-Prüfung (Pflichtfelder, Datentypen, Enums)
→ Referenz-Auflösung (techn. Namen → UUIDs)
→ Abhängigkeits-Check (existieren referenzierte Entitäten?)
→ Duplikat-Erkennung (Create vs. Update)
→ Fuzzy-Matching bei Nicht-Auflösung
→ Status: "validated"
```

### Phase 3: Ausführung
```
GET /api/v1/imports/{id}/preview → Diff-Vorschau
POST /api/v1/imports/{id}/execute → Bestätigen
→ Async via Laravel Queue (bei > 100 Zeilen)
→ Status: "executing" → "completed"
GET /api/v1/imports/{id}/result → Report
```

---

## Validierungs-Response

```json
{
  "import_id": "uuid",
  "status": "validated",
  "sheets_found": ["05_Attribute", "08_Produkte", "09_Produktwerte"],
  "summary": {
    "05_Attribute": { "total": 250, "valid": 247, "errors": 3, "creates": 180, "updates": 67 },
    "08_Produkte": { "total": 1200, "valid": 1198, "errors": 2, "creates": 800, "updates": 398 }
  },
  "errors": [
    {
      "sheet": "05_Attribute", "row": 45, "column": "E", "field": "Datentyp",
      "value": "Texxt",
      "error": "Ungültiger Datentyp. Erlaubt: String, Number, Float, ...",
      "suggestion": null
    },
    {
      "sheet": "09_Produktwerte", "row": 8401, "column": "B", "field": "Attribut",
      "value": "Gwicht",
      "error": "Attribut nicht gefunden.",
      "suggestion": "Gewicht"
    }
  ]
}
```

---

## Smart-Matching (Fuzzy-Auflösung)

- Levenshtein-Distanz (Threshold: 85% Ähnlichkeit)
- Case-insensitive: "gewicht" = "Gewicht" = "GEWICHT"
- Trim + Normalisierung (Leerzeichen)
- Vorschläge: "Meinten Sie: ...?"
- Strict-Mode deaktiviert Fuzzy

---

## Update-Logik (Upsert)

| Entität | Identifikation über | Bei Existenz |
|---------|-------------------|-------------|
| Produkttyp | technical_name | Update |
| Attribut | technical_name | Update |
| Einheitengruppe | technical_name | Update |
| Werteliste | technical_name | Update |
| Hierarchieknoten | Pfad (Ebenen) | Skip |
| Produkt | SKU | Update |
| Produktwert | SKU + Attribut + Sprache + Index | Update |
| Preis | SKU + Preisart + Währung + Gültigkeit | Update |

---

## Laravel-Klassen

```php
App\Services\Import\ImportService          // Orchestrierung
App\Services\Import\SheetParser            // Excel → strukturierte Daten
App\Services\Import\SheetValidator         // Validierung + Fehler
App\Services\Import\ReferenceResolver      // Techn. Namen → UUIDs
App\Services\Import\FuzzyMatcher           // Tippfehler-Erkennung
App\Services\Import\ImportExecutor         // Daten schreiben (Queue-Job)
App\Services\Import\TemplateGenerator      // Leeres Template erzeugen
```

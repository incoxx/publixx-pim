---
title: Validierung
---

# Validierung

Die Validierung ist die zentrale Sicherheitsschicht des Import-Prozesses. Sie stellt sicher, dass ausschliesslich konsistente und korrekte Daten in die Datenbank geschrieben werden. Dieser Abschnitt beschreibt die einzelnen Validierungsstufen, das Fuzzy Matching, das Antwortformat und die Vorschau-Funktion.

## Validierungsstufen

Die Validierung durchläuft mehrere aufeinanderfolgende Stufen. Jede Stufe baut auf den Ergebnissen der vorherigen auf.

### 1. Schema-Validierung

Die Schema-Validierung prüft die strukturelle Korrektheit jeder Zeile:

| Prüfung | Beschreibung | Beispiel-Fehler |
|---|---|---|
| **Pflichtfelder** | Alle als Pflicht markierten Spalten müssen befüllt sein | `Zeile 5: Feld 'sku' ist ein Pflichtfeld` |
| **Datentypen** | Werte müssen dem erwarteten Datentyp entsprechen | `Zeile 8: 'abc' ist kein gültiger Wert für Typ 'number'` |
| **Enum-Werte** | Bei Aufzählungsfeldern müssen die Werte gültig sein | `Zeile 12: 'aktiv' ist kein gültiger Status (erwartet: draft, active, inactive)` |
| **Formate** | Datumsangaben, EAN-Nummern etc. müssen dem Format entsprechen | `Zeile 3: 'abc123' ist keine gültige EAN` |
| **Längen** | Zeichenketten dürfen die maximale Länge nicht überschreiten | `Zeile 7: 'technical_name' darf maximal 255 Zeichen lang sein` |

### 2. Referenzauflösung

In dieser Stufe werden alle textuellen Referenzen (technische Namen) in die entsprechenden UUIDs aufgelöst:

- **Attributgruppen** -- Technischer Name wird in die UUID der Attributgruppe aufgelöst
- **Einheitengruppen und Einheiten** -- Zuordnung der Einheit zur richtigen Gruppe
- **Wertelisten** -- Prüfung, ob die referenzierte Werteliste existiert
- **Hierarchieknoten** -- Auflösung des Pfads in die Knoten-UUID
- **Produkte (SKU)** -- Zuordnung der SKU zur Produkt-UUID

Die Auflösung berücksichtigt sowohl Daten, die bereits im System vorhanden sind, als auch Daten, die in der aktuellen Importdatei definiert werden. Dadurch können neue Entitäten in derselben Datei sowohl definiert als auch referenziert werden.

### 3. Abhängigkeitsprüfung

Die Abhängigkeitsprüfung stellt sicher, dass alle referenzierten Entitäten tatsächlich existieren:

| Prüfung | Beispiel-Fehler |
|---|---|
| Referenzierte Attributgruppe existiert nicht | `Zeile 3, Tab 'Attribute': Attributgruppe 'technische_datten' nicht gefunden` |
| Referenzierte Einheitengruppe existiert nicht | `Zeile 5, Tab 'Attribute': Einheitengruppe 'laenge' nicht gefunden` |
| Referenziertes Produkt existiert nicht | `Zeile 12, Tab 'Produktwerte': SKU 'BM-999' nicht gefunden` |
| Referenziertes Attribut existiert nicht | `Zeile 8, Tab 'Produktwerte': Attribut 'gewicht_neto' nicht gefunden` |
| Einheit passt nicht zur Einheitengruppe | `Zeile 4, Tab 'Produktwerte': Einheit 'kg' gehört nicht zur Gruppe 'laenge'` |

### 4. Duplikaterkennung

Die Duplikaterkennung bestimmt, ob ein Datensatz neu angelegt oder aktualisiert werden soll:

| Situation | Aktion | Markierung |
|---|---|---|
| Identifikator existiert **nicht** im System und **nicht** in der Datei | **Erstellen** | `CREATE` |
| Identifikator existiert **bereits** im System | **Aktualisieren** | `UPDATE` |
| Identifikator kommt in der Datei **mehrfach** vor | **Fehler** | `DUPLICATE_ERROR` |
| Identifikator existiert im System, aber keine Änderungen | **Überspringen** | `SKIP` |

## Fuzzy Matching

Das Fuzzy-Matching-System erkennt Tippfehler in Referenzfeldern automatisch und bietet Korrekturvorschläge an. Dies reduziert die Fehlerquote beim Import erheblich.

### Algorithmus

Das Matching verwendet folgende Strategie:

1. **Exakter Vergleich** -- Wird der Wert exakt gefunden, ist keine Korrektur nötig.
2. **Case-Insensitive Vergleich** -- Grossschreibung wird ignoriert (`Gewicht_Netto` findet `gewicht_netto`).
3. **Whitespace-Trimming** -- Führende und nachfolgende Leerzeichen werden entfernt.
4. **Levenshtein-Distanz** -- Bei keiner exakten Übereinstimmung wird die normalisierte Levenshtein-Ähnlichkeit berechnet. Liegt sie bei **85 % oder höher**, wird der beste Treffer als Vorschlag angezeigt.

### Beispiel

| Eingabe (fehlerhaft) | Bester Treffer | Ähnlichkeit | Vorschlag |
|---|---|---|---|
| `gewicht_neto` | `gewicht_netto` | 93 % | Meinten Sie `gewicht_netto`? |
| `techniche_daten` | `technische_daten` | 94 % | Meinten Sie `technische_daten`? |
| `Bohrmaschne` | `Bohrmaschine` | 92 % | Meinten Sie `Bohrmaschine`? |
| `xyz_unbekannt` | -- | < 85 % | Kein Vorschlag (Fehler) |

::: info Hinweis
Fuzzy-Matching-Vorschläge werden nur in der Validierungsantwort angezeigt. Sie werden **nicht** automatisch angewendet. Der Benutzer muss die Korrekturen in der Excel-Datei selbst vornehmen und die Datei erneut hochladen.
:::

## Validierungsantwort

Die Validierungs-API gibt eine strukturierte JSON-Antwort zurück, die alle Ergebnisse pro Tab zusammenfasst.

### Erfolgreiche Validierung

```json
{
  "status": "valid",
  "summary": {
    "total_rows": 245,
    "creates": 180,
    "updates": 60,
    "skips": 5,
    "errors": 0,
    "warnings": 3
  },
  "tabs": {
    "05_Attribute": {
      "rows": 42,
      "creates": 35,
      "updates": 7,
      "skips": 0,
      "errors": [],
      "warnings": []
    },
    "08_Produkte": {
      "rows": 120,
      "creates": 95,
      "updates": 25,
      "skips": 0,
      "errors": [],
      "warnings": []
    }
  }
}
```

### Validierung mit Fehlern

```json
{
  "status": "invalid",
  "summary": {
    "total_rows": 245,
    "creates": 0,
    "updates": 0,
    "skips": 0,
    "errors": 4,
    "warnings": 2
  },
  "tabs": {
    "09_Produktwerte": {
      "rows": 83,
      "errors": [
        {
          "row": 12,
          "column": "attribute",
          "value": "gewicht_neto",
          "code": "REFERENCE_NOT_FOUND",
          "message": "Attribut 'gewicht_neto' nicht gefunden.",
          "suggestion": {
            "match": "gewicht_netto",
            "similarity": 0.93,
            "message": "Meinten Sie 'gewicht_netto'?"
          }
        },
        {
          "row": 45,
          "column": "sku",
          "value": "UNBEKANNT-999",
          "code": "REFERENCE_NOT_FOUND",
          "message": "Produkt mit SKU 'UNBEKANNT-999' nicht gefunden.",
          "suggestion": null
        }
      ],
      "warnings": [
        {
          "row": 67,
          "column": "unit",
          "value": "",
          "code": "MISSING_OPTIONAL_UNIT",
          "message": "Attribut 'gewicht_netto' hat eine Einheitengruppe, aber keine Einheit wurde angegeben. Standardeinheit 'kg' wird verwendet."
        }
      ]
    }
  }
}
```

### Fehlercodes

| Code | Beschreibung |
|---|---|
| `REQUIRED_FIELD_MISSING` | Pflichtfeld ist nicht ausgefüllt |
| `INVALID_DATA_TYPE` | Wert passt nicht zum erwarteten Datentyp |
| `INVALID_ENUM_VALUE` | Wert ist kein gültiger Enum-Wert |
| `INVALID_FORMAT` | Wert entspricht nicht dem erwarteten Format |
| `MAX_LENGTH_EXCEEDED` | Zeichenkette überschreitet die maximale Länge |
| `REFERENCE_NOT_FOUND` | Referenzierte Entität nicht gefunden |
| `DEPENDENCY_MISSING` | Abhängige Entität existiert nicht |
| `DUPLICATE_IN_FILE` | Identifikator kommt in der Datei mehrfach vor |
| `UNIT_GROUP_MISMATCH` | Einheit passt nicht zur Einheitengruppe des Attributs |
| `VALUE_NOT_UNIQUE` | Wert verletzt eine Eindeutigkeitsbedingung |

## Vorschau (Diff-Ansicht)

Nach erfolgreicher Validierung bietet das System eine Vorschau der geplanten Änderungen. Die Diff-Ansicht zeigt für jeden Tab übersichtlich, welche Datensätze angelegt, aktualisiert oder übersprungen werden.

### Vorschau-Kategorien

| Kategorie | Symbol | Beschreibung |
|---|---|---|
| **Anlegen** | `CREATE` | Neue Datensätze, die im System noch nicht existieren |
| **Aktualisieren** | `UPDATE` | Bestehende Datensätze, bei denen sich mindestens ein Feld ändert |
| **Überspringen** | `SKIP` | Bestehende Datensätze ohne Änderungen |

### Detailansicht bei Updates

Für Aktualisierungen zeigt die Vorschau die geänderten Felder im Vergleich:

```json
{
  "action": "UPDATE",
  "identifier": "BM-2000-PRO",
  "changes": [
    {
      "field": "name_de",
      "old_value": "Bohrmaschine Pro 2000",
      "new_value": "Bohrmaschine Pro 2000 (Neuauflage)"
    },
    {
      "field": "status",
      "old_value": "draft",
      "new_value": "active"
    }
  ]
}
```

Diese Vorschau ermöglicht es dem Benutzer, die Auswirkungen des Imports zu überprüfen, bevor die Ausführung gestartet wird. Unerwartete Änderungen können so frühzeitig erkannt und die Excel-Datei bei Bedarf korrigiert werden.

## Ablauf in der Benutzeroberfläche

1. **Datei hochladen** -- Excel-Datei per Drag-and-Drop oder Dateiauswahl hochladen.
2. **Validierung starten** -- Die Validierung wird automatisch nach dem Upload gestartet.
3. **Ergebnis prüfen** -- Fehler und Warnungen werden tabellarisch angezeigt.
4. **Korrekturen vornehmen** (bei Fehlern) -- Excel-Datei korrigieren und erneut hochladen.
5. **Vorschau prüfen** (bei erfolgreicher Validierung) -- Diff-Ansicht der geplanten Änderungen.
6. **Import ausführen** -- Bestätigung durch den Benutzer startet die transaktionsgesicherte Ausführung.

## Weiterführende Dokumentation

- [Import-Übersicht](/de/import/) -- Prozessübersicht und Konzept
- [Excel-Format](/de/import/excel-format) -- Detaillierte Spaltendokumentation aller 14 Tabs

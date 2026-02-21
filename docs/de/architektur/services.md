---
title: Services und Events
---

# Services und Events

Die Service-Schicht bildet das Herzstueck der Geschaeftslogik in Publixx PIM. Alle Operationen, die ueber einfache CRUD-Vorgaenge hinausgehen, werden in dedizierten Services gekapselt. Controller delegieren an Services, Services orchestrieren Models und loesen Events aus.

## Service-Architektur

### Designprinzipien

Services in Publixx PIM folgen einheitlichen Architekturprinzipien:

1. **Single Responsibility**: Jeder Service deckt einen klar abgegrenzten fachlichen Bereich ab
2. **Dependency Injection**: Services erhalten ihre Abhaengigkeiten ueber Constructor Injection und werden ueber den Laravel Service Container aufgeloest
3. **Transaktionale Integritaet**: Operationen, die mehrere Tabellen betreffen, werden in Datenbanktransaktionen gekapselt
4. **Event-Emission**: Nach erfolgreicher Durchfuehrung einer Operation loest der Service die entsprechenden Events aus
5. **Cache-Bewusstsein**: Services invalidieren betroffene Caches gezielt ueber Tags

### Aufrufhierarchie

```
Controller
  └─> FormRequest (Validierung)
  └─> Service (Geschaeftslogik)
        └─> Model (Datenzugriff)
        └─> Event (Benachrichtigung)
              └─> Listener (Reaktion)
                    └─> Queue Job (asynchron)
```

---

## Zentrale Services

### ExportService

Der `ExportService` koordiniert den gesamten Exportprozess von der Template-Auswertung bis zur Dateigenerierung.

**Verantwortlichkeiten:**
- Laden und Interpretieren von PXF-Export-Templates
- Aufloesen der Attribut-Mappings (Quell-Attribut zu Ziel-Feldname)
- Aggregation der Produktdaten gemaess Template-Konfiguration
- Anwendung von Transformationsregeln (Formatierung, Einheitenkonvertierung, Textbegrenzung)
- Generierung der Ausgabedatei (JSON fuer Publixx-Integration)
- Protokollierung des Export-Vorgangs

**Interaktion mit anderen Services:**
- Nutzt den `InheritanceService`, um vollstaendig aufgeloeste Attributwerte zu erhalten (inkl. geerbter Werte)
- Nutzt den `PqlService` fuer die optionale Filterung der zu exportierenden Produkte

```php
class ExportService
{
    public function execute(ExportTemplate $template, array $options): ExportResult
    {
        // 1. Produkte gemaess Template-Scope laden
        // 2. Attributwerte inkl. Vererbung aufloesen
        // 3. Mappings anwenden
        // 4. Transformationen ausfuehren
        // 5. Ausgabe generieren
        // 6. ExportCompleted-Event ausloesen
    }
}
```

### ImportService

Der `ImportService` steuert den dreiphasigen Importprozess und ist einer der komplexesten Services im System.

**Phase 1 -- Parsing:**
- Einlesen der Excel-Datei mit 14 Tabellenblaettern
- Strukturerkennung pro Tabellenblatt (Header-Zeile, Datenzeilen)
- Erstellung eines strukturierten Import-Modells im Speicher

**Phase 2 -- Validierung und Zuordnung:**
- Abgleich der Spaltennamen gegen vorhandene Attribute (exakt und per Fuzzy-Matching)
- Validierung der Datenwerte gegen die Attributtypen und -regeln
- Erkennung von Referenzen (Hierarchie-Pfade, Elternprodukte, Medien)
- Erstellung eines Validierungsberichts mit Warnungen und Fehlern

**Phase 3 -- Ausfuehrung:**
- Transaktionaler Import aller validierten Daten
- Erstellung oder Aktualisierung von Produkten, Werten und Zuordnungen
- Ausloesung der entsprechenden Events fuer Cache-Invalidierung und Index-Aktualisierung

**Fehlerbehandlung:**
Fehler werden nicht als Abbruch behandelt, sondern als strukturiertes Protokoll im JSON-Format in der `import_jobs`-Tabelle gespeichert. Der Benutzer erhaelt nach dem Import einen detaillierten Bericht mit Zeilen- und Spaltenbezug.

### InheritanceService

Der `InheritanceService` ist fuer die Auflosung von Attributwerten unter Beruecksichtigung der zweistufigen Vererbung verantwortlich.

**Verantwortlichkeiten:**
- Aufloesen der Hierarchie-Vererbung (Attribute aus uebergeordneten Knoten)
- Aufloesen der Varianten-Vererbung (Werte vom Elternprodukt)
- Bestimmung der korrekten Aufloesungsreihenfolge
- Beruecksichtigung des `dont_inherit`-Flags bei knotenspezifischen Attributen
- Bereitstellung von Metadaten ueber die Herkunft jedes Werts (eigener Wert, geerbt von Variante, geerbt von Hierarchie)

```php
class InheritanceService
{
    public function resolveValue(
        Product $product,
        Attribute $attribute,
        string $locale
    ): ResolvedValue {
        // 1. Eigenen Wert pruefen
        // 2. Varianten-Vererbungsregel pruefen (falls Variante)
        // 3. Elternwert laden (falls inherit-Regel aktiv)
        // 4. Hierarchie-Standardwert pruefen
        // 5. ResolvedValue mit Herkunftsinformation zurueckgeben
    }
}
```

Detaillierte Dokumentation der Vererbungslogik findet sich unter [Vererbung](./vererbung).

### PqlService

Der `PqlService` uebersetzt PQL-Abfragen in optimierte SQL-Queries.

**Verarbeitungsschritte:**
1. **Lexing**: Zerlegung des PQL-Strings in Token (Operatoren, Werte, Attributnamen)
2. **Parsing**: Aufbau eines abstrakten Syntaxbaums (AST) aus den Token
3. **Validierung**: Pruefung, ob die referenzierten Attribute existieren und die Operatoren fuer den jeweiligen Attributtyp zulaessig sind
4. **SQL-Generierung**: Uebersetzung des AST in eine SQL-Query gegen den `products_search_index`
5. **Ausfuehrung**: Absetzen der Query und Rueckgabe der Ergebnismenge

**Besondere Operatoren:**
- `FUZZY` wird in eine Kombination aus `SOUNDEX()` und Levenshtein-Distanzberechnung uebersetzt
- `SOUNDS_LIKE` nutzt die MySQL-eigene `SOUNDEX()`-Funktion
- `SEARCH_FIELDS` wird in eine `MATCH ... AGAINST`-Query ueber den FULLTEXT-Index uebersetzt

### PreviewService

Der `PreviewService` generiert Vorschau-Darstellungen von Produkten fuer die Frontend-Anzeige.

**Verantwortlichkeiten:**
- Zusammenstellung aller sichtbaren Attributwerte unter Beruecksichtigung der Benutzerrechte (Attribut-View)
- Markierung geerbter Werte mit Herkunftsinformation
- Aufbereitung von Medien-Thumbnails
- Beruecksichtigung der aktiven Sprache und Fallback-Logik

### ProductVersioningService

Der `ProductVersioningService` verwaltet die Aenderungshistorie von Produkten.

**Verantwortlichkeiten:**
- Erstellung von Versionssnapshots vor Aenderungen
- Vergleich zwischen Versionen (Diff-Berechnung)
- Bereitstellung der Versionshistorie fuer die Oberflaeche
- Optionale Wiederherstellung frueherer Versionen

---

## Event-System

Publixx PIM nutzt das Laravel-Event-System intensiv, um lose gekoppelte Reaktionen auf Geschaeftsereignisse zu ermoeglichen. Events werden synchron ausgeloest, die zugehoerigen Listener koennen jedoch asynchrone Queue-Jobs dispatchen.

### Kern-Events

| Event | Ausgelöst durch | Beschreibung |
|---|---|---|
| `ProductCreated` | ProductService | Neues Produkt wurde angelegt |
| `ProductUpdated` | ProductService | Produktstammdaten wurden geaendert |
| `ProductDeleted` | ProductService | Produkt wurde geloescht |
| `ProductValueChanged` | ProductService | Ein Attributwert wurde gesetzt oder geaendert |
| `VariantInheritanceChanged` | InheritanceService | Vererbungsregel einer Variante wurde geaendert |
| `HierarchyNodeMoved` | HierarchyService | Ein Knoten wurde in der Hierarchie verschoben |
| `HierarchyAttributeAssigned` | HierarchyService | Einem Knoten wurde ein Attribut zugewiesen |
| `ImportCompleted` | ImportService | Ein Importvorgang wurde abgeschlossen |
| `ExportCompleted` | ExportService | Ein Exportvorgang wurde abgeschlossen |
| `SearchIndexStale` | diverse | Ein Suchindex-Eintrag muss aktualisiert werden |

### Event-Listener-Zuordnungen

```
ProductValueChanged
  ├─> InvalidateProductCacheListener      (sync)
  ├─> UpdateSearchIndexListener           (async, Queue)
  └─> PropagateToVariantsListener         (async, Queue)

HierarchyNodeMoved
  ├─> RecalculateInheritedAttributesListener  (async, Queue)
  ├─> InvalidateHierarchyCacheListener        (sync)
  └─> UpdateAffectedSearchIndexListener       (async, Queue)

VariantInheritanceChanged
  ├─> RecalculateVariantValuesListener    (async, Queue)
  └─> InvalidateVariantCacheListener      (sync)
```

---

## Cache-Invalidierung

Die Cache-Invalidierung folgt einem **Tag-basierten System**. Jeder Cache-Eintrag ist mit einem oder mehreren Tags versehen, die seine fachliche Zugehoerigkeit beschreiben.

### Tag-Konventionen

| Tag-Muster | Beispiel | Beschreibung |
|---|---|---|
| `product:{id}` | `product:550e8400-...` | Alle Caches eines bestimmten Produkts |
| `products` | `products` | Alle produktbezogenen Caches (Listen, Uebersichten) |
| `hierarchy:{id}` | `hierarchy:a1b2c3d4-...` | Alle Caches einer Hierarchie |
| `node:{id}` | `node:x9y8z7w6-...` | Alle Caches eines Hierarchieknotens |
| `attributes` | `attributes` | Alle attributbezogenen Caches |
| `search_index` | `search_index` | Suchindex-bezogene Caches |

### Invalidierungskaskaden

Bestimmte Aenderungen loesen kaskadierende Invalidierungen aus:

1. **Attributwert-Aenderung am Elternprodukt**: Invalidiert den Cache des Elternprodukts und aller Varianten, die den Wert erben
2. **Hierarchie-Knotenverschiebung**: Invalidiert alle Caches der betroffenen Knoten und deren Kindprodukte
3. **Attribut-Definition-Aenderung**: Invalidiert alle produktbezogenen Caches, da sich die Validierung oder Darstellung aendern kann

---

## Queue-Jobs und Hintergrundverarbeitung

Asynchrone Jobs werden ueber Redis-Queues verarbeitet und von Laravel Horizon ueberwacht.

### Job-Kategorien

**Prioritaet hoch (Queue: `high`):**
- `UpdateSearchIndexJob` -- Aktualisierung einzelner Suchindex-Eintraege
- `PropagateInheritedValueJob` -- Weitergabe geaenderter Werte an Varianten
- `InvalidateCascadingCacheJob` -- Kaskadierende Cache-Invalidierung

**Prioritaet normal (Queue: `default`):**
- `ProcessImportJob` -- Verarbeitung eines Excel-Imports
- `GenerateExportJob` -- Erstellung einer Exportdatei
- `RecalculateInheritedAttributesJob` -- Neuberechnung nach Hierarchie-Aenderungen

**Prioritaet niedrig (Queue: `low`):**
- `GenerateMediaThumbnailJob` -- Thumbnail-Erstellung fuer hochgeladene Medien
- `CleanupExpiredExportsJob` -- Bereinigung abgelaufener Exportdateien
- `WarmCacheJob` -- Proaktives Befuellen von Caches nach Invalidierung

### Horizon-Konfiguration

Horizon verteilt Worker auf die Queues nach Prioritaet:

```php
'environments' => [
    'production' => [
        'supervisor-high' => [
            'queue' => ['high'],
            'processes' => 4,
            'tries' => 3,
            'timeout' => 120,
        ],
        'supervisor-default' => [
            'queue' => ['default'],
            'processes' => 2,
            'tries' => 3,
            'timeout' => 600,
        ],
        'supervisor-low' => [
            'queue' => ['low'],
            'processes' => 1,
            'tries' => 1,
            'timeout' => 300,
        ],
    ],
],
```

### Wiederholungsstrategie

Fehlgeschlagene Jobs werden automatisch wiederholt. Die Wiederholungslogik unterscheidet zwischen transienten Fehlern (Datenbankverbindung unterbrochen, Redis nicht erreichbar) und fachlichen Fehlern (ungueltige Daten). Transiente Fehler werden mit exponentiellem Backoff bis zu dreimal wiederholt. Fachliche Fehler werden sofort als endgueltig fehlgeschlagen markiert und im Horizon-Dashboard angezeigt.

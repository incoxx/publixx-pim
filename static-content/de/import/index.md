---
title: Import - Übersicht
---

# Import

Das Import-Modul des Publixx PIM ermöglicht die Massenübernahme von Produktdaten über standardisierte Excel-Dateien. Es richtet sich an Produktmanager und Data Stewards, die grosse Datenmengen effizient in das System einpflegen möchten -- ohne technische API-Kenntnisse.

## Konzept

Der Import folgt dem Prinzip **"Validierung vor Ausführung"**: Daten werden niemals direkt in die Datenbank geschrieben, ohne zuvor vollständig geprüft worden zu sein. Dadurch wird sichergestellt, dass fehlerhafte Datensätze keine inkonsistenten Zustände verursachen.

## Dreiphasiger Importprozess

Der Import durchläuft drei klar getrennte Phasen:

<svg viewBox="0 0 900 320" xmlns="http://www.w3.org/2000/svg" style="max-width: 100%; height: auto; margin: 2rem 0;">
  <defs>
    <marker id="arrow-import" viewBox="0 0 10 7" refX="10" refY="3.5" markerWidth="10" markerHeight="7" orient="auto-start-reverse">
      <path d="M 0 0 L 10 3.5 L 0 7 z" fill="#6366f1"/>
    </marker>
    <filter id="shadow-import" x="-5%" y="-5%" width="115%" height="115%">
      <feDropShadow dx="0" dy="2" stdDeviation="3" flood-opacity="0.1"/>
    </filter>
  </defs>

  <!-- Phase 1: Upload -->
  <rect x="20" y="40" width="240" height="240" rx="16" fill="#f0f0ff" stroke="#6366f1" stroke-width="2" filter="url(#shadow-import)"/>
  <rect x="20" y="40" width="240" height="50" rx="16" fill="#6366f1"/>
  <rect x="20" y="74" width="240" height="16" fill="#6366f1"/>
  <text x="140" y="72" text-anchor="middle" fill="white" font-size="18" font-weight="bold" font-family="system-ui, sans-serif">Phase 1: Upload</text>

  <text x="140" y="120" text-anchor="middle" fill="#1e1b4b" font-size="14" font-family="system-ui, sans-serif">Excel-Datei hochladen</text>
  <text x="140" y="145" text-anchor="middle" fill="#4b5563" font-size="12" font-family="system-ui, sans-serif">14-Tab-Vorlage mit</text>
  <text x="140" y="163" text-anchor="middle" fill="#4b5563" font-size="12" font-family="system-ui, sans-serif">Stamm- und Produktdaten</text>

  <rect x="60" y="185" width="160" height="36" rx="8" fill="white" stroke="#6366f1" stroke-width="1"/>
  <text x="140" y="208" text-anchor="middle" fill="#6366f1" font-size="12" font-family="system-ui, sans-serif">Datei wird gespeichert</text>

  <rect x="60" y="230" width="160" height="36" rx="8" fill="white" stroke="#6366f1" stroke-width="1"/>
  <text x="140" y="253" text-anchor="middle" fill="#6366f1" font-size="12" font-family="system-ui, sans-serif">Tabs werden erkannt</text>

  <!-- Arrow 1→2 -->
  <line x1="270" y1="160" x2="320" y2="160" stroke="#6366f1" stroke-width="2" marker-end="url(#arrow-import)"/>

  <!-- Phase 2: Validierung -->
  <rect x="330" y="40" width="240" height="240" rx="16" fill="#fefce8" stroke="#eab308" stroke-width="2" filter="url(#shadow-import)"/>
  <rect x="330" y="40" width="240" height="50" rx="16" fill="#eab308"/>
  <rect x="330" y="74" width="240" height="16" fill="#eab308"/>
  <text x="450" y="72" text-anchor="middle" fill="white" font-size="18" font-weight="bold" font-family="system-ui, sans-serif">Phase 2: Validierung</text>

  <text x="450" y="120" text-anchor="middle" fill="#713f12" font-size="14" font-family="system-ui, sans-serif">Datenprüfung</text>
  <text x="450" y="145" text-anchor="middle" fill="#4b5563" font-size="12" font-family="system-ui, sans-serif">Schema, Referenzen,</text>
  <text x="450" y="163" text-anchor="middle" fill="#4b5563" font-size="12" font-family="system-ui, sans-serif">Abhängigkeiten, Duplikate</text>

  <rect x="370" y="185" width="160" height="36" rx="8" fill="white" stroke="#eab308" stroke-width="1"/>
  <text x="450" y="208" text-anchor="middle" fill="#a16207" font-size="12" font-family="system-ui, sans-serif">Fehler + Vorschläge</text>

  <rect x="370" y="230" width="160" height="36" rx="8" fill="white" stroke="#eab308" stroke-width="1"/>
  <text x="450" y="253" text-anchor="middle" fill="#a16207" font-size="12" font-family="system-ui, sans-serif">Vorschau (Diff-Ansicht)</text>

  <!-- Arrow 2→3 -->
  <line x1="580" y1="160" x2="630" y2="160" stroke="#6366f1" stroke-width="2" marker-end="url(#arrow-import)"/>

  <!-- Phase 3: Ausführung -->
  <rect x="640" y="40" width="240" height="240" rx="16" fill="#f0fdf4" stroke="#22c55e" stroke-width="2" filter="url(#shadow-import)"/>
  <rect x="640" y="40" width="240" height="50" rx="16" fill="#22c55e"/>
  <rect x="640" y="74" width="240" height="16" fill="#22c55e"/>
  <text x="760" y="72" text-anchor="middle" fill="white" font-size="18" font-weight="bold" font-family="system-ui, sans-serif">Phase 3: Ausführung</text>

  <text x="760" y="120" text-anchor="middle" fill="#14532d" font-size="14" font-family="system-ui, sans-serif">Daten schreiben</text>
  <text x="760" y="145" text-anchor="middle" fill="#4b5563" font-size="12" font-family="system-ui, sans-serif">Upsert-Logik: Anlegen</text>
  <text x="760" y="163" text-anchor="middle" fill="#4b5563" font-size="12" font-family="system-ui, sans-serif">oder Aktualisieren</text>

  <rect x="680" y="185" width="160" height="36" rx="8" fill="white" stroke="#22c55e" stroke-width="1"/>
  <text x="760" y="208" text-anchor="middle" fill="#15803d" font-size="12" font-family="system-ui, sans-serif">Transaktionsgesichert</text>

  <rect x="680" y="230" width="160" height="36" rx="8" fill="white" stroke="#22c55e" stroke-width="1"/>
  <text x="760" y="253" text-anchor="middle" fill="#15803d" font-size="12" font-family="system-ui, sans-serif">Ergebnisbericht</text>
</svg>

### Phase 1: Upload

Der Benutzer lädt eine Excel-Datei über die Import-Oberfläche hoch. Das System erkennt automatisch die enthaltenen Tabellenblätter (Tabs) und ordnet sie den internen Datentypen zu. Die Datei wird serverseitig gespeichert und steht für die Validierung bereit.

### Phase 2: Validierung

In der Validierungsphase prüft das System jeden Datensatz auf:

- **Schema-Konformität** -- Pflichtfelder, Datentypen, gültige Enum-Werte
- **Referenzauflösung** -- Technische Namen werden in UUIDs aufgelöst
- **Abhängigkeiten** -- Referenzierte Entitäten müssen existieren (in der Datei oder im System)
- **Duplikaterkennung** -- Bereits vorhandene Datensätze werden für Upsert markiert
- **Fuzzy Matching** -- Tippfehler in Referenzen werden erkannt und Korrekturvorschläge angezeigt

Das Ergebnis ist ein detaillierter Validierungsbericht mit Fehlern, Warnungen und einer Vorschau der geplanten Änderungen (Diff-Ansicht: Anlegen, Aktualisieren, Überspringen).

### Phase 3: Ausführung

Erst nach erfolgreicher Validierung und expliziter Bestätigung durch den Benutzer werden die Daten in die Datenbank geschrieben. Die Ausführung erfolgt **transaktionsgesichert** -- entweder werden alle Datensätze eines Tabs erfolgreich verarbeitet oder der gesamte Tab wird zurückgerollt. Nach Abschluss erhält der Benutzer einen Ergebnisbericht.

## Excel-Vorlage: 14 Tabs

Die Importdatei besteht aus 14 Tabellenblättern, die in einer definierten Reihenfolge und Abhängigkeitsstruktur verarbeitet werden:

| Nr. | Tab | Beschreibung |
|---|---|---|
| 01 | `Sprachen` | Verfügbare Inhaltssprachen |
| 02 | `Attributgruppen` | Logische Gruppierung von Attributen |
| 03 | `Einheitengruppen` | Gruppen physikalischer Einheiten |
| 04 | `Einheiten` | Einzelne Masseinheiten mit Umrechnungsfaktoren |
| 05 | `Attribute` | Attributdefinitionen (19 Spalten) |
| 06 | `Hierarchien` | Master- und Ausgabehierarchien mit bis zu 6 Ebenen |
| 07 | `Wertelisten` | Vordefinierte Auswahlwerte für Attribute |
| 08 | `Produkte` | Stammdaten der Produkte (SKU, Name, Typ, Status) |
| 09 | `Produktwerte` | Attributwerte je Produkt, Sprache und Wiederholung |
| 10 | `Varianten` | Produktvarianten mit Zuordnung zum Elternprodukt |
| 11 | `Variantenwerte` | Attributwerte der Varianten |
| 12 | `Medien` | Medienzuordnungen zu Produkten |
| 13 | `Preise` | Preisinformationen mit Währung und Gültigkeit |
| 14 | `Relationen` | Beziehungen zwischen Produkten |

::: tip Hinweis
Nicht alle Tabs müssen befüllt sein. Sie können gezielt nur die Tabs verwenden, die für Ihren Import relevant sind. Abhängigkeiten werden sowohl innerhalb der Datei als auch gegen den bestehenden Datenbestand aufgelöst.
:::

## Upsert-Logik

Der Import arbeitet nach dem **Upsert-Prinzip** (Update or Insert):

- **Neuer Datensatz**: Wird angelegt, wenn kein Datensatz mit dem gleichen Identifikator (z. B. technischer Name, SKU) existiert.
- **Bestehender Datensatz**: Wird aktualisiert, wenn bereits ein Datensatz mit demselben Identifikator vorhanden ist. Dabei werden nur die in der Importdatei enthaltenen Felder überschrieben.

Dieses Verhalten ermöglicht sowohl initiale Datenübernahmen als auch inkrementelle Aktualisierungen mit derselben Importdatei.

## Smart Matching

Das System nutzt intelligentes Matching, um Tippfehler in Referenzen automatisch zu erkennen:

- **Levenshtein-Distanz** mit einem Schwellwert von 85 %
- **Case-Insensitive** Vergleich
- **Whitespace-Trimming** vor dem Vergleich

Wird ein ähnlicher, aber nicht exakt übereinstimmender Wert gefunden, zeigt das System einen Korrekturvorschlag in der Validierungsvorschau an.

## Weiterführende Dokumentation

- [Excel-Format](/de/import/excel-format) -- Detaillierte Spaltendokumentation aller 14 Tabs
- [Validierung](/de/import/validierung) -- Validierungsregeln, Fehlermeldungen und Vorschau-Ansicht

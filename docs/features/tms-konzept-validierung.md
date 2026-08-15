# Übersetzungen in anyPIM — Validierung des Gesamtkonzepts

**Stand:** 2026-08-15 · **Anlass:** Testszenario „neue Sprache Finnisch"

Ergänzt `tms-bestandsaufnahme.md` (Einzelbefunde). Dieses Dokument bewertet das
**Konzept**, nicht die einzelnen Fehler.

---

## 1. Urteil vorweg

| Teilziel | Trägt das Konzept? |
|----------|--------------------|
| Neue Sprache anlegen | **Ja** — seit der neuen `languages`-Tabelle. Vorher gar nicht. |
| Metadaten nach Finnisch übersetzen | **Ja** — Ingest → `translate-missing` → Sync deckt das vollständig ab. |
| Übersetzungsrelevante Attributwerte übersetzen | **Nur eingeschränkt** — siehe Abschnitt 3. Der Nutzen ist real, aber ungleich verteilt. |
| Produkte per Suchprofil eingrenzen | **Ja**, konzeptionell sauber — `SearchProfileQueryBuilder` existiert und ist der richtige Hebel. |
| Austausch mit Trados/Across | **Ja**, aber nur mit beiden Formaten: XLIFF für Aufträge, TMX für Bestände. |

Das Fundament trägt. Die drei Strukturprobleme in Abschnitt 2 sind aber real und
werden mit jeder weiteren Sprache teurer zu beheben.

---

## 2. Strukturelle Befunde

### 2.1 Zwei parallele Übersetzungssysteme

anyPIM hat **zwei** voneinander unabhängige Übersetzungswege:

| | Translation Memory (`tms/`) | Übersetzungs-Jobs (`TranslationJobService`) |
|---|---|---|
| Gegenstand | Metadaten (11 Typen) | Produkt-Attributwerte |
| Provider | `tms/…/DeepLProvider` (97 Z.) | `app/…/DeepLTranslationService` (141 Z.) |
| Speicher | `tms_units` / `tms_translations` | `ProductAttributeValue` |
| Auslöser | Cron (`tms:ingest`, `tms:sync`) | Job-Anlage in der UI |
| Datei-Austausch | keiner | XLIFF 1.2 |
| Kennt Review-Status | ja (`auto` / `reviewed`) | nein |

Es gibt **zwei DeepL-Clients** für denselben Dienst. Sie unterscheiden sich in
Timeout, Fehlerbehandlung und Sprachcode-Mapping (`EN` → `EN-US` nur im TMS).
Wer eine Provider-Einstellung ändert, muss daran denken, dass es sie zweimal gibt.

Verbunden sind die beiden Wege nur an einer einzigen Stelle, und nur in einer
Richtung: `TranslationJobService::syncToTms()` schiebt fertige Übersetzungen ins
TM — **nachdem** DeepL bereits bezahlt wurde. Vor der Übersetzung fragt niemand
das TM. Jeder Job zahlt erneut für Text, den das TM längst kennt.

> **Bewertung:** Das ist die teuerste Struktureigenschaft des heutigen Systems.
> Ein TM, das erst *nach* der Übersetzung befragt wird, ist kein TM, sondern ein
> Archiv.

### 2.2 Drei Speicherorte, zwei Sonderfälle

Übersetzungen liegen an drei verschiedenen Orten:

1. Feste Spalten `name_de` / `name_en` an den Metadaten-Modellen
2. `name_json` (bzw. `display_value_json`) für alle übrigen Sprachen
3. Zeilen in `ProductAttributeValue` mit `language`-Spalte

Daraus folgt eine Sonderbehandlung, die sich durch den Code zieht:

```php
if (in_array($job->target_language, ['de', 'en'])) {   // feste Spalte
} else {                                                // JSON
}
```

**Deutsch und Englisch sind im Datenmodell privilegiert, alle anderen Sprachen
sind Bürger zweiter Klasse.** Für das Finnisch-Szenario ist das folgenlos —
Finnisch landet sauber in `name_json`. Relevant wird es in zwei Fällen:

- Ein Kunde mit **englischer Quellsprache**: `SyncTmsTranslationsJob::ENTITY_MAP`
  verdrahtet `'source_field' => 'name_de'` an **10** Stellen. Der Metadaten-Sync
  ist auf deutsche Quelltexte festgelegt. (Die Hash-Berechnung liest die
  Quellsprache inzwischen aus der `languages`-Tabelle — das Quellfeld noch nicht.)
- Sprachen abschalten: ein Wert in `name_json` verschwindet nicht, wenn die
  Sprache deaktiviert wird. Das ist gewollt, aber nirgends sichtbar.

### 2.3 Der Hash kennt keinen Kontext — und keine Sätze

Der Schlüssel des TM ist `sha256("{sprache}|{text}")`. Die Spalte `domain`
existiert, geht aber **nicht** in den Hash ein.

Zwei Konsequenzen:

**Homonyme kollidieren.** „Bank", „Schloss", „Golf", „Absatz" bekommen genau eine
Übersetzung — global, über alle Domänen hinweg. Bei Metadaten ist das
verschmerzbar und sogar erwünscht (ein Attributname *soll* überall gleich
übersetzt sein). Bei Produktinhalten ist es ein echtes Risiko.

**Es gibt keine Segmentierung und kein Fuzzy-Matching.** Ein professionelles TM
zerlegt Texte in Sätze und findet auch 85-%-Treffer. Dieses TM hasht das
**gesamte Feld** und trifft nur exakt. Ändert sich ein einziges Zeichen, ist der
Treffer weg und der Text wird komplett neu bezahlt.

> **Bewertung:** Für kurze, standardisierte Texte ist das genau richtig und
> hocheffizient. Für lange Fließtexte ist die Trefferquote nahe null. Das ist der
> entscheidende Punkt für die Attributwert-Übersetzung.

---

## 3. Was das für „alle übersetzungsrelevanten Attributwerte" heißt

Die Trefferquote des TM hängt vollständig an der Textsorte:

| Feldtyp | Beispiel | Wiederholung | TM-Nutzen |
|---------|----------|--------------|-----------|
| Wertelisten-Einträge, Materialien, Normbezeichnungen | „Rostfreier Edelstahl" | sehr hoch | **sehr hoch** |
| Kurzbeschreibungen, Pflege-/Sicherheitshinweise | „Nicht für Kinder unter 3 Jahren geeignet." | hoch | **hoch** |
| Technische Fließtexte | Montageanleitung | mittel | mittel |
| Marketing-/Langtexte, USPs | individuelle Produkttexte | gering | **nahe null** |

Empfehlung daraus:

- **TM-Weg** für kurze und standardisierte Felder. Dort ist die Ersparnis groß und
  die Homonym-Gefahr klein, weil die Texte fachlich eindeutig sind.
- **Auftragsweg (Job + XLIFF)** für Langtexte. Dort gehört ein Mensch bzw. eine
  Agentur in die Schleife, und die Agentur bringt ihr eigenes, segmentierendes TM
  mit — genau die Fähigkeit, die diesem TM fehlt.
- Die Steuerung dafür existiert bereits nicht: `Attribute.is_translatable` ist ein
  reines Ja/Nein. Es bräuchte eine zweite Stufe („per TM" vs. „per Auftrag"), sonst
  landen Marketingtexte im TM und blähen es ohne Gegenwert auf.

**Zur Suchprofil-Eingrenzung:** konzeptionell richtig und über
`SearchProfileQueryBuilder` sauber umsetzbar. Ein Hinweis: das Suchprofil grenzt
ein, *welche Produkte* ingestiert werden — nicht, welche Texte übersetzt werden.
Taucht derselbe Text auch bei einem ausgeschlossenen Produkt auf, wird er trotzdem
übersetzt, sobald irgendein eingeschlossenes Produkt ihn enthält. Das ist richtig
so (das TM ist textbasiert, nicht produktbasiert), sollte aber niemanden
überraschen, der eine Kostenschätzung macht.

---

## 4. Das Finnisch-Szenario, Schritt für Schritt

| # | Schritt | Status |
|---|---------|--------|
| 1 | Sprache „Finnisch" anlegen | **API fertig** (`POST /languages`), Vue-Oberfläche fehlt noch |
| 2 | `tms/.env` um `fi` ergänzen | **noch nötig** — die TMS-Seite liest weiter ihre eigene Env |
| 3 | Metadaten ingesten (`tms:ingest`) | funktioniert; zieht fehlende Sprachen jetzt nach |
| 4 | Bestand ausrollen (`tms:translate-missing --lang=fi`) | **neu gebaut**, gedeckelt und wiederaufnehmbar |
| 5 | Fortschritt prüfen (`tms:status`) | **neu gebaut** |
| 6 | Zurückschreiben (`tms:sync`) | funktioniert, schreibt nach `name_json['fi']` |
| 7 | Attributwerte übersetzen | **fehlt** — Ingest/Sync kennen `ProductAttributeValue` nicht |
| 8 | Eingrenzung per Suchprofil | **fehlt** |
| 9 | Austausch mit Agentur | XLIFF nur PIM-seitig für Produktwerte; TM-Austausch fehlt |

Schritte 1–6 tragen das Szenario für **Metadaten** vollständig. Schritt 2 ist der
verbliebene Stolperstein: die Sprachliste ist noch nicht wirklich einzügig, das
TMS liest weiterhin `TMS_TARGET_LANGUAGES` aus seiner eigenen `.env`. `tms:status`
warnt inzwischen, wenn beide auseinanderlaufen — das ist eine Krücke, keine Lösung.

---

## 5. Empfohlene Reihenfolge

1. **Vue-Oberfläche für das Sprachen-CRUD** — ohne sie ist Schritt 1 nicht „per GUI".
2. **Sprachliste wirklich einzügig machen:** Das PIM schickt die Zielsprachen im
   Ingest-Payload mit, das TMS hört auf, seine eigene Env zu lesen. Damit
   verschwindet die Drift-Klasse von Fehlern ganz statt nur überwacht zu werden.
3. **TM-Abfrage vor DeepL** in `TranslationJobService`. Größter Sofort-Effekt,
   kleinster Eingriff — und Voraussetzung dafür, dass die beiden Systeme
   überhaupt zusammenarbeiten.
4. **Attributwerte anbinden**, inkrementell (Wasserzeichen über `updated_at`),
   gefiltert über `is_translatable`, eingegrenzt per Suchprofil, mit Längenlimit
   (der Ingest validiert `max:10000` Zeichen — längere Werte kippen sonst den
   ganzen Batch).
5. **Zweistufiges `is_translatable`** („per TM" / „per Auftrag"), damit Langtexte
   das TM nicht verwässern.
6. **XLIFF im TMS** für den Auftragsweg, **TMX** für den Bestandsaustausch. TMX
   ist der einzige Weg, ein vorhandenes Kunden-TM zu übernehmen — bei einer neuen
   Sprache ist das der größte einzelne Kostenhebel.
7. **Kontext in den Hash** (`domain` mit einbeziehen) — nur zusammen mit einem
   vollständigen Neu-Ingest, deshalb bewusst zuletzt. Vorher entscheiden, ob die
   globale Vereinheitlichung von Metadaten-Begriffen erhalten bleiben soll.

---

## 6. Was ich nicht validieren konnte

- Kein MySQL in der Prüfumgebung: die PIM-seitigen Feature-Tests liefen nicht.
  Geprüft wurden die TMS-Suite (45 Tests, SQLite) und die PIM-Unit-Tests (30).
- Die `languages`-Migration wurde nicht gegen eine echte Datenbank ausgeführt.
- Mengengerüst: die Aussagen zur Trefferquote in Abschnitt 3 sind aus der
  Textsorte abgeleitet, nicht an echten Kundendaten gemessen. Vor Schritt 4 wäre
  eine Zählung sinnvoll — `SELECT COUNT(*), COUNT(DISTINCT value_string)` über die
  `is_translatable`-Attributwerte gibt die Ersparnis exakt an.

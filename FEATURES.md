# anyPIM — Feature-Übersicht

> Komplettes Product Information Management für Teams, die Produktdaten zentral pflegen, anreichern und verteilen.

---

## Tägliche Arbeit

| Feature | Beschreibung | Nutzen |
|---------|-------------|--------|
| **Berichte** (Reports) | Konfigurierbarer Report-Designer mit Drag-and-Drop-Feldern, Filtern und Gruppierungen. | Datenqualität und Vollständigkeit auf einen Blick erkennen — ohne Export in Excel. |
| **Bildtypen** (Media Usage Types) | Definition von Bildverwendungsarten (Teaser, Galerie, Technische Zeichnung, Dokument). | Medien werden nicht nur hochgeladen, sondern kanalspezifisch klassifiziert — Shop, Print und Katalog erhalten automatisch die richtigen Assets. |
| **Dashboard** | Persönliches Dashboard mit Widgets: offene Workflow-Aufgaben, zuletzt bearbeitete Produkte, Datenqualitäts-KPIs. | Sofortiger Überblick über den eigenen Arbeitsbereich beim Login — kein Suchen nach dem Einstiegspunkt. |
| **Hierarchien** (Hierarchies) | Master- und Ausgabe-Hierarchien mit Materialized-Path-Algorithmus, Drag-and-Drop-Sortierung und Attribut-Zuweisung pro Knoten. | Produkte lassen sich in beliebig tiefe Kategoriebäume einordnen. Ausgabe-Hierarchien ermöglichen kanalspezifische Strukturen (Shop, Print, Marktplatz) ohne Daten zu duplizieren. |
| **Medien** (Media) | Upload, automatische Thumbnail-Generierung, Vorschau, Metadaten-Pflege. Unterstützt Bilder, PDFs, Videos und sonstige Dateien. | Alle Produkt-Assets an einem Ort — kein Dateichaos auf Netzlaufwerken. Thumbnails und Vorschau direkt im PIM. |
| **Merkliste** (Watchlist) | Produkte auf eine persönliche Merkliste setzen und schnell wieder aufrufen. | Häufig benötigte oder in Bearbeitung befindliche Produkte sind mit einem Klick erreichbar — spart Suchzeit im Alltag. |
| **PDF-Vorlagen** (PDF Templates) | Visueller Template-Designer für Produktdatenblätter mit Platzhaltern für Attribute, Medien und Preise. | Produktdatenblätter werden automatisch aus PIM-Daten generiert — kein manuelles Layout in InDesign oder Word mehr nötig. |
| **Planungskalender** (Planning Calendar) | Kalenderansicht für geplante Produktlaunches, Saisonwechsel und Meilensteine. | Redaktionelle Planung direkt im PIM — alle Beteiligten sehen, wann welche Produkte live gehen. |
| **Produkte** (Products) | Produktliste mit Inline-Filtern, Spaltenanpassung, Massenauswahl. Detailansicht mit Tabs für Attribute, Medien, Preise, Relationen und Varianten. | Zentrale Datenpflege mit allen Informationen auf einer Seite. Varianten erben Attribute automatisch — nur Abweichungen müssen gepflegt werden. |
| **Suche** (Search) | Google-ähnliche Volltextsuche über Produktnamen, SKU, EAN und Attributwerte. Unterstützt PQL (Product Query Language) für komplexe Abfragen. | Jedes Produkt ist in Sekunden gefunden — auch bei Tippfehlern dank phonetischer Suche und Fuzzy-Matching. |
| **Workflow** | Aufgabenverwaltung mit Status-Tracking, Zuweisung an Benutzer und Verlaufshistorie. | Datenpflege wird steuerbar: Wer muss was bis wann erledigen? Aufgaben gehen nicht mehr in E-Mails unter. |

---

## Konfiguration

| Feature | Beschreibung | Nutzen |
|---------|-------------|--------|
| **Attribute** | Verwaltung aller Produktattribute mit 12 Datentypen: String, Number, Float, Date, Flag, Selection, Dictionary, Composite, RichText, Hyperlink, ImageLink, PdfLink, VideoLink. | Jede Produkteigenschaft lässt sich ohne Datenbankänderung abbilden — von einfachen Texten über strukturierte Links bis zu wiederholbaren Datengruppen (Collections). |
| **Attributgruppen** (Attribute Types) | Logische Gruppierung von Attributen (z. B. „Technische Daten", „Marketing", „Logistik"). | Übersichtliche Strukturierung auch bei hunderten von Attributen — Anwender sehen nur die für sie relevante Gruppe. |
| **Attribut-Sichten** (Attribute Views) | Definierbare Teilmengen von Attributen für verschiedene Anwendungsfälle und Abteilungen. | Marketing sieht Marketing-Attribute, Logistik sieht Logistik-Attribute — ohne sich gegenseitig in die Quere zu kommen. |
| **Einheiten** (Units) | Einheitengruppen und Einheiten mit Umrechnungsfaktoren (z. B. mm → cm → m). | Werte werden einheitlich gespeichert und bei Bedarf automatisch umgerechnet — keine manuellen Konvertierungen mehr. |
| **Hersteller** (Manufacturers) | Herstellerstammdaten mit Logo, Kontaktdaten und Zuordnung zu Produkten. | Herstellerinformationen werden zentral gepflegt und stehen allen Produkten konsistent zur Verfügung. |
| **Preise** (Prices) | Preistypen (UVP, EK, VK, Aktionspreis) mit Staffelpreisen und Währungsunterstützung. | Flexible Preisstrukturen für unterschiedliche Vertriebskanäle und Kundengruppen — alles am Produkt gepflegt. |
| **Preisregionen** (Price Regions) | Regionale Preiszuordnung für unterschiedliche Märkte und Länder. | Internationale Preisgestaltung direkt im PIM — ohne separate Tabellen pro Land pflegen zu müssen. |
| **Produktbeziehungen** (Relation Types) | Definierbare Beziehungstypen zwischen Produkten (Zubehör, Ersatzteile, Cross-Selling, Up-Selling) mit beziehungsspezifischen Attributen. | Produktempfehlungen und Zubehörlisten werden im PIM gepflegt und automatisch an alle Kanäle ausgespielt. |
| **Produkttypen** (Product Types) | Vordefinierte Typen (Physisches Produkt, Schulung, Service, Software, Bundle, Digitales Asset) plus eigene Typen. | Unterschiedliche Produktarten erhalten unterschiedliche Pflichtfelder und Validierungsregeln — ein Bundle hat andere Anforderungen als eine Software. |
| **Übersetzungen** (Translations) | Mehrsprachige Verwaltung aller Texte mit XLIFF-Export für Übersetzungsbüros. | Übersetzungsworkflow direkt im PIM — Export an Übersetzer, Re-Import der Ergebnisse. Kein Hin- und Herschicken von Excel-Dateien. |
| **Wertelisten** (Value Lists) | Hierarchische Auswahllisten mit mehrsprachigen Anzeigewerten und Verschachtelung. | Einheitliche Dropdown-Werte über alle Produkte hinweg — „Rot" heißt überall „Rot", nicht mal „rot", mal „ROT", mal „Red". |
| **Wörterbuch** (Dictionary) | Nachschlagetabelle für Fachbegriffe, Abkürzungen und Übersetzungen. | Konsistente Terminologie im gesamten Produktsortiment — wichtig für SEO und Markenkonsistenz. |

---

## Administration

| Feature | Beschreibung | Nutzen |
|---------|-------------|--------|
| **API Tester** | Integrierter API-Tester zum direkten Aufruf aller 188 REST-Endpoints mit Authentifizierung. | Schnittstellen können direkt im PIM getestet werden — ohne Postman oder externe Tools. |
| **Benutzer** (Users) | Benutzerverwaltung mit Sanctum-Authentifizierung, Passwort-Richtlinien und Profilpflege. | Vollständige Benutzerkontrolle ohne externes Identity Management — sofort einsatzbereit. |
| **Benutzer-Audit** (User Audit Trail) | Lückenlose Protokollierung aller Benutzeraktionen mit Zeitstempel, Benutzer und geänderten Feldern. | Nachvollziehbarkeit: Wer hat wann was geändert? Wichtig für Qualitätssicherung und Compliance. |
| **BMEcat Import/Export** | Branchenstandard-Format für B2B-Produktdatenaustausch — bidirektional. | Nahtloser Datenaustausch mit Lieferanten und Kunden, die BMEcat nutzen — ohne manuelle Konvertierung. |
| **Datenbank** (Database) | Direkter Einblick in die Datenbankstruktur und Tabelleninhalte für Administratoren. | Schnelle Fehleranalyse und Datenprüfung ohne Kommandozeilen-Zugang zum Server. |
| **Einstellungen** (Settings) | Systemweite Konfiguration: Sprachen, Module, Lizenzierung, Suchindex-Neuaufbau. | Zentrale Systemsteuerung — alle Einstellungen an einem Ort, nicht verstreut in Config-Dateien. |
| **Export** | Gefilterte Produktexporte in konfigurierbaren Formaten mit Mapping-Templates (PXF). | Jeder Kanal bekommt die Daten im gewünschten Format — Shop, Marktplatz und Print aus einer Quelle. |
| **Export-Jobs** | Benannte, wiederverwendbare Export-Konfigurationen mit Zeitplanung (Cron). | Regelmäßige Exporte laufen automatisch — z. B. jeden Morgen aktualisierte Produktdaten an den Shop. |
| **Import** | Excel-Import mit 14-Tab-Struktur, dreistufiger Validierung (Parse → Validate → Execute) und Fuzzy-Matching bei Tippfehlern. | Große Datenmengen können per Excel eingespielt werden — mit automatischer Fehlererkennung und Korrekturvorschlägen statt stummem Abbruch. |
| **Journal** (Change Log) | Systemweites Änderungsprotokoll aller Datenänderungen. | Vollständige Datenhistorie — jede Änderung ist nachvollziehbar und bei Bedarf umkehrbar. |
| **JSON Export/Import** | Vollständiger Datenexport/-import in 18 abhängigkeitsgeordneten Sektionen. Unterstützt Filter nach Status, Produkttyp und Hierarchie. | Komplette PIM-Instanzen können gesichert, migriert oder zwischen Umgebungen übertragen werden — inklusive aller Beziehungen und Abhängigkeiten. |
| **Rollen** (Roles) | Feingranulares Berechtigungssystem mit vordefinierten Rollen (Admin, Data Steward, Product Manager, Viewer, Export Manager) und individueller Anpassung. | Jeder Benutzer sieht und darf genau das, was er braucht — bis auf Attribut- und Hierarchieknoten-Ebene einschränkbar. |
| **Zugangslinks** (Access Links) | Temporäre, tokenbasierte Links für externen Zugriff auf den Produktkatalog ohne Benutzeraccount. | Externe Partner, Agenturen oder Kunden können Produktdaten einsehen — ohne Benutzeranlage und mit automatischem Ablaufdatum. |

---

## Öffentliche Bereiche (ohne Login)

| Feature | Beschreibung | Nutzen |
|---------|-------------|--------|
| **Asset-Katalog** (Asset Catalog) | Öffentlich zugänglicher Medien-Katalog mit Ordnerstruktur und Download-Funktion. | Agenturen und Partner können sich Produktbilder und Dokumente selbst herunterladen — ohne Nachfragen per E-Mail. |
| **Produktkatalog** (Catalog Preview) | Öffentlicher Produktkatalog mit Kategorie-Navigation, Facetten-Filtern, Produktdetailseiten und Kontaktformular. | Sofort einsatzbereiter Online-Katalog direkt aus dem PIM — als Zwischenlösung bis zum Shop oder als dauerhafter B2B-Katalog. |

---

## Zusätzliche Produktfunktionen

| Feature | Beschreibung | Nutzen |
|---------|-------------|--------|
| **Bulk-Editor** | Tabellarische Massenbearbeitung von Attributwerten über mehrere Produkte gleichzeitig. | Hunderte von Produkten in Minuten statt Stunden aktualisieren — wie in Excel, aber direkt im PIM. |
| **Massendatenpflege** (Bulk Update) | Gleichen Wert auf eine Auswahl von Produkten in einem Schritt anwenden. | „Setze bei allen 500 Sommerprodukten den Status auf aktiv" — ein Klick statt 500. |
| **Produktversionierung** (Versioning) | Versionshistorie mit Zeitplanung (Publish-Datum) und One-Click-Rollback. | Produktänderungen können vorbereitet und terminiert werden — mit Sicherheitsnetz durch sofortige Rücknahme. |
| **Varianten-Vererbung** (Variant Inheritance) | Pro Attribut steuerbar: erben oder überschreiben. Änderungen am Stammprodukt propagieren automatisch. | Varianten pflegen sich praktisch von selbst — nur echte Abweichungen (Farbe, Größe) müssen manuell gesetzt werden. |

---

## Technische Alleinstellungsmerkmale

### PQL — Product Query Language
Eigene Abfragesprache mit SQL-ähnlicher Syntax für komplexe Produktsuchen:
- Vergleichsoperatoren: `=`, `!=`, `>`, `<`, `>=`, `<=`
- Muster: `LIKE`, `NOT LIKE` (Volltext-indiziert)
- Listen: `IN`, `NOT IN`
- Bereiche: `BETWEEN`, `NOT BETWEEN`
- Existenz: `EXISTS`, `NOT EXISTS`
- **Fuzzy-Suche:** `FUZZY 'text' [threshold]` — Levenshtein + Trigramm (60%+40% gewichtet)
- **Phonetische Suche:** `SOUNDS_LIKE 'text'` — Kölner Phonetik (Deutsch) + Soundex (Englisch)
- **Gewichtete Feldsuche:** `SEARCH_FIELDS(field^weight, ...)`
- **Relevanz-Ranking:** `ORDER BY SCORE DESC`

**Nutzen:** Produktmanager finden auch bei unscharfen Anfragen die richtigen Produkte — Tippfehler, Dialektschreibweisen und Synonyme werden erkannt.

### Zweistufiges Vererbungssystem
1. **Hierarchie-Vererbung:** Produkte erben Attribute vom zugewiesenen Knoten und allen Vorfahren
2. **Varianten-Vererbung:** Per-Attribut-Steuerung (erben vs. überschreiben)

**Nutzen:** Drastische Reduzierung des Pflegeaufwands — gemeinsame Daten werden nur einmal gepflegt und automatisch propagiert.

### EAV-Architektur mit materialisiertem Suchindex
- Entity-Attribute-Value-Modell für unbegrenzte Attributflexibilität
- Denormalisierte Suchindextabelle für Volltextsuche mit Phonetik-Feld
- Automatische Invalidierung bei Datenänderungen

**Nutzen:** Neue Attribute werden über die Oberfläche angelegt — ohne Datenbankmigrationen, Deployments oder Entwickler.

### REST-API mit 188 Endpoints
Vollständige API-Abdeckung aller Funktionen mit Sanctum-Authentifizierung (Bearer Token + SPA Cookie).

**Nutzen:** Jedes Drittsystem (Shop, ERP, POS, Marktplatz) kann Produktdaten lesen und schreiben — das PIM wird zur zentralen Datendrehscheibe.

---

## Technische Eckdaten

| Eigenschaft | Wert |
|------------|------|
| **Backend** | PHP 8.4 / Laravel 11 |
| **Frontend** | Vue 3 / Vite / Tailwind CSS |
| **Datenbank** | MySQL 8+ |
| **Cache & Queue** | Redis / Laravel Horizon |
| **Authentifizierung** | Laravel Sanctum |
| **API-Endpoints** | 188 RESTful |
| **Datentypen** | 12 |
| **Eloquent-Modelle** | 48 |
| **Vue-Komponenten** | 116+ |
| **Migrationen** | 63 |
| **Sprachen (UI)** | Deutsch, Englisch |
| **Lizenz** | GPL-3.0 (Open Source) |
| **Installation** | Ein Befehl (`setup.sh`) auf Ubuntu 24.04 |

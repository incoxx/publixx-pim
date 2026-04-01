# anyPIM — Von 0 auf 100

Schritt für Schritt vom leeren System zum ersten Export.

---

## Inhaltsverzeichnis

1. [Einheitengruppen und Einheiten anlegen](#1-einheitengruppen-und-einheiten-anlegen)
2. [Attributgruppen anlegen](#2-attributgruppen-anlegen)
3. [Wertelisten anlegen](#3-wertelisten-anlegen)
4. [Attribute anlegen](#4-attribute-anlegen)
5. [Hierarchien aufbauen](#5-hierarchien-aufbauen)
6. [Produkte anlegen und pflegen](#6-produkte-anlegen-und-pflegen)
7. [Varianten](#7-varianten)
8. [Medien](#8-medien)
9. [Preise](#9-preise)
10. [Übersetzungen](#10-übersetzungen)
11. [Export](#11-export)
12. [Checkliste](#12-checkliste)

---

## Bevor es losgeht

Dieses Dokument setzt voraus, dass anyPIM installiert und erreichbar ist. Die Installation ist in der Verkaufs-Mappe beschrieben.

Melden Sie sich als Administrator an. Nach dem Login sehen Sie das Dashboard mit einer Übersicht der Produkte, offenen Aufgaben und den letzten Änderungen. Alle Funktionsbereiche erreichen Sie über die **Sidebar** links.

**Reihenfolge ist wichtig.** Die Kapitel bauen aufeinander auf. Attribute brauchen Einheiten und Wertelisten. Produkte brauchen Attribute und Hierarchien. Diese Anleitung folgt der korrekten Abhängigkeitskette.

---

## 1. Einheitengruppen und Einheiten anlegen

Einheiten werden von Float-Attributen referenziert — z.B. "Gewicht in kg" oder "Länge in cm". Deshalb legen wir sie als erstes an.

### Einheitengruppe erstellen

**Sidebar → Einheiten → + Neue Einheitengruppe**

Eine Einheitengruppe fasst zusammengehörige Einheiten zusammen.

| Feld | Eingabe |
|---|---|
| Technischer Name | `gewicht` |
| Anzeigename (DE) | Gewicht |
| Anzeigename (EN) | Weight |

Speichern.

### Einheiten hinzufügen

In der soeben erstellten Gruppe: **+ Neue Einheit**

Jede Gruppe hat genau eine **Basiseinheit** (Umrechnungsfaktor = 1). Alle anderen Einheiten werden relativ dazu definiert.

| Einheit | Kürzel | Faktor | Basiseinheit |
|---|---|---|---|
| Kilogramm | kg | 1 | Ja |
| Gramm | g | 0.001 | Nein |
| Tonne | t | 1000 | Nein |
| Pfund | lb | 0.45359 | Nein |

Wiederholen Sie den Vorgang für weitere Gruppen:

**Gruppe "Länge":**

| Einheit | Kürzel | Faktor | Basiseinheit |
|---|---|---|---|
| Meter | m | 1 | Ja |
| Zentimeter | cm | 0.01 | Nein |
| Millimeter | mm | 0.001 | Nein |

Das System rechnet automatisch um, wenn der Benutzer später eine andere Einheit wählt.

---

## 2. Attributgruppen anlegen

Attributgruppen organisieren die Attribute in der Produktbearbeitung. Ohne Gruppen gibt es nur eine lange Liste — mit Gruppen sieht der Benutzer übersichtliche Abschnitte.

**Sidebar → Attribute → Attributgruppen → + Neue Attributgruppe**

Legen Sie die Gruppen an, die zu Ihrem Produktsortiment passen:

| Technischer Name | Anzeigename (DE) | Anzeigename (EN) | Zweck |
|---|---|---|---|
| `stammdaten` | Stammdaten | Master Data | Name, SKU, Hersteller, Status |
| `technische_daten` | Technische Daten | Technical Data | Maße, Gewicht, Materialien |
| `marketing` | Marketing | Marketing | Beschreibungen, USPs, SEO-Texte |
| `logistik` | Logistik | Logistics | Verpackung, Versand, Mindestbestellmenge |

Der **technische Name** ist nach dem Speichern nicht mehr änderbar. Wählen Sie ihn sorgfältig (snake_case, keine Sonderzeichen).

---

## 3. Wertelisten anlegen

Wertelisten definieren die Auswahloptionen für Selection-Attribute. Beispiel: Das Attribut "Farbe" bietet die Optionen Rot, Blau, Grün — diese Optionen kommen aus einer Werteliste.

**Sidebar → Attribute → Wertelisten → + Neue Werteliste**

### Beispiel: Werteliste "Farben"

| Feld | Eingabe |
|---|---|
| Technischer Name | `farben` |
| Anzeigename (DE) | Farben |
| Anzeigename (EN) | Colors |

Speichern. Dann Werte hinzufügen:

**+ Wert hinzufügen**

| Technischer Schlüssel | Anzeigename (DE) | Anzeigename (EN) |
|---|---|---|
| `rot` | Rot | Red |
| `blau` | Blau | Blue |
| `gruen` | Grün | Green |
| `schwarz` | Schwarz | Black |
| `weiss` | Weiß | White |

Die Reihenfolge der Werte bestimmt die Sortierung im Dropdown. Per Drag-and-Drop umsortierbar.

### Weitere Wertelisten (Beispiele)

- **Material:** Holz, Metall, Kunststoff, Glas, Textil
- **Schutzklasse:** IP20, IP44, IP65, IP67, IP68
- **Energieeffizienz:** A+++, A++, A+, A, B, C, D

Legen Sie alle Wertelisten an, die Sie für Ihre Attribute benötigen. Im nächsten Schritt verknüpfen wir sie.

---

## 4. Attribute anlegen

Jetzt haben wir alles, was Attribute benötigen: Einheitengruppen, Attributgruppen und Wertelisten. Attribute sind das Datenmodell Ihres PIM — sie definieren, welche Informationen zu einem Produkt erfasst werden.

**Sidebar → Attribute → + Neues Attribut**

### Die wichtigsten Datentypen in der Praxis

**String — Text:**

| Feld | Eingabe |
|---|---|
| Technischer Name | `beschreibung` |
| Anzeigename (DE) | Beschreibung |
| Datentyp | String |
| Attributgruppe | Marketing |
| Übersetzbar | Ja |
| Pflichtfeld | Ja |
| Vererbbar | Ja |

Ergebnis: Ein Textfeld, das pro Sprache gepflegt wird. Varianten erben den Text vom Elternprodukt.

**Float — Dezimalzahl mit Einheit:**

| Feld | Eingabe |
|---|---|
| Technischer Name | `gewicht` |
| Anzeigename (DE) | Gewicht |
| Datentyp | Float |
| Attributgruppe | Technische Daten |
| Einheitengruppe | Gewicht |
| Standardeinheit | kg |
| Pflichtfeld | Nein |
| Vererbbar | Ja |

Ergebnis: Ein Zahlenfeld mit Einheiten-Dropdown (kg, g, t, lb). Automatische Umrechnung.

**Selection — Auswahl aus Werteliste:**

| Feld | Eingabe |
|---|---|
| Technischer Name | `farbe` |
| Anzeigename (DE) | Farbe |
| Datentyp | Selection |
| Attributgruppe | Stammdaten |
| Werteliste | Farben |
| Übersetzbar | Nein |
| Vererbbar | Nein |

Ergebnis: Dropdown mit den Farboptionen aus der Werteliste.

**Number — Ganzzahl:**

| Feld | Eingabe |
|---|---|
| Technischer Name | `mindestbestellmenge` |
| Anzeigename (DE) | Mindestbestellmenge |
| Datentyp | Number |
| Attributgruppe | Logistik |

**Date — Datum:**

| Feld | Eingabe |
|---|---|
| Technischer Name | `erscheinungsdatum` |
| Anzeigename (DE) | Erscheinungsdatum |
| Datentyp | Date |
| Attributgruppe | Stammdaten |

**Flag — Ja/Nein:**

| Feld | Eingabe |
|---|---|
| Technischer Name | `ist_gefahrgut` |
| Anzeigename (DE) | Ist Gefahrgut |
| Datentyp | Flag |
| Attributgruppe | Logistik |

### Weitere Datentypen

| Datentyp | Wofür | Beispiel |
|---|---|---|
| Dictionary | Schlüssel-Wert-Paare als JSON | Technische Spezifikationen |
| Collection | Wiederholbare Einträge | Zertifikate, Normen |
| RichText | Formatierter HTML-Text | Marketingbeschreibung |
| Hyperlink | URL | Herstellerseite |
| ImageLink | Bild-URL | Produktbild-Referenz |
| PdfLink | PDF-URL | Datenblatt-Link |
| VideoLink | Video-URL | Produktvideo |

### Eigenschaften im Überblick

| Eigenschaft | Bedeutung |
|---|---|
| **Übersetzbar** | Wert kann pro Sprache unterschiedlich sein |
| **Pflichtfeld** | Muss ausgefüllt sein, bevor das Produkt auf "Aktiv" gesetzt werden kann |
| **Vererbbar** | Varianten erben den Wert vom Elternprodukt |
| **Durchsuchbar** | Wird in der Volltextsuche berücksichtigt |
| **Eindeutig** | Wert muss über alle Produkte hinweg einmalig sein |

**Wichtig:** Der technische Name eines Attributs kann nach dem Anlegen nicht mehr geändert werden.

---

## 5. Hierarchien aufbauen

Hierarchien klassifizieren Ihre Produkte in eine Baumstruktur. anyPIM kennt zwei Typen:

- **Master-Hierarchie:** Die interne Produktklassifizierung. Jedes Produkt gehört zu genau einem Knoten.
- **Ausgabe-Hierarchie:** Für Exporte und Kanäle. Ein Produkt kann in mehreren Knoten erscheinen.

### Master-Hierarchie erstellen

**Sidebar → Hierarchien → + Neue Hierarchie**

| Feld | Eingabe |
|---|---|
| Name (DE) | Produktkatalog |
| Typ | Master |

Nach dem Speichern sehen Sie den Wurzelknoten. Jetzt die Struktur aufbauen:

**Rechtsklick auf Wurzelknoten → + Neuer Knoten**

Beispielstruktur:

```
Produktkatalog
├── Elektrowerkzeuge
│   ├── Bohrmaschinen
│   ├── Sägen
│   └── Schleifgeräte
├── Handwerkzeuge
│   ├── Hämmer
│   ├── Schraubendreher
│   └── Zangen
└── Zubehör
    ├── Bohrer
    ├── Sägeblätter
    └── Schleifpapier
```

Maximal 6 Ebenen. Knoten per Drag-and-Drop umsortierbar.

### Attributgruppen an Knoten zuweisen

Das ist der entscheidende Schritt: Durch die Zuweisung von Attributgruppen an Hierarchieknoten bestimmen Sie, welche Felder bei den zugeordneten Produkten erscheinen.

**Rechtsklick auf Knoten → Attributgruppe zuordnen**

Beispiel:
- Knoten "Elektrowerkzeuge" → Attributgruppen: Stammdaten, Technische Daten, Marketing
- Knoten "Bohrmaschinen" → zusätzlich: spezifische Bohrmaschinen-Attribute

Die Zuweisung **vererbt sich nach unten**: Produkte im Knoten "Bohrmaschinen" erben automatisch alle Attributgruppen von "Elektrowerkzeuge" plus die eigenen.

### Knotenattribute

Auf jedem Knoten können auch Werte hinterlegt werden, die an alle zugeordneten Produkte vererbt werden — z.B. eine Kategoriebeschreibung oder ein Kategoriebild.

---

## 6. Produkte anlegen und pflegen

Jetzt steht das Datenmodell. Zeit für das erste Produkt.

### Produkt erstellen

**Sidebar → Produkte → + Neues Produkt**

| Feld | Eingabe | Pflicht |
|---|---|---|
| SKU | `BM-PRO-2000` | Ja |
| Name | Bohrmaschine Pro 2000 | Ja |
| Produkttyp | Standard | Ja |
| EAN | 4012345678901 | Nein |
| Status | Entwurf | Ja |
| Hierarchieknoten | Bohrmaschinen | Ja |

Nach dem Speichern öffnet sich die **Produktdetailansicht**.

**Tipp: Massenimport.** Für große Produktbestände müssen Sie nicht jedes Produkt einzeln anlegen. anyPIM bietet einen Excel-Import mit 14-Tab-Struktur (Sprachen, Einheiten, Attribute, Produkte, Varianten, Medien, Preise, ...). Details im Hilfe-Portal. In diesem Leitfaden zeigen wir den manuellen Weg, damit Sie die Zusammenhänge verstehen.

### Attributwerte pflegen

Die Detailansicht zeigt die Attribute, gruppiert nach den zugewiesenen Attributgruppen. Füllen Sie die Felder aus:

**Tab: Attribute**

Die Attribute sind nach Gruppen geordnet (Stammdaten, Technische Daten, Marketing, ...). Bei übersetzbaren Attributen können Sie über den **Sprachumschalter** zwischen den Sprachen wechseln.

Beispielwerte für unser Produkt:

| Attribut | Wert |
|---|---|
| Beschreibung (DE) | Professionelle Schlagbohrmaschine mit 850W Motor |
| Beschreibung (EN) | Professional hammer drill with 850W motor |
| Gewicht | 2,5 kg |
| Farbe | Blau |
| Ist Gefahrgut | Nein |
| Mindestbestellmenge | 1 |
| Erscheinungsdatum | 2025-03-01 |

Speichern mit dem Button **Speichern** oder `Strg + S`.

### Status ändern

Ein Produkt kann erst auf **Aktiv** gesetzt werden, wenn alle Pflichtfelder ausgefüllt sind. Der Statuswechsel:

```
Entwurf  →  Aktiv  →  Inaktiv
                    →  Eingestellt (Discontinued)
```

- **Entwurf:** Produkt wird bearbeitet, noch nicht sichtbar
- **Aktiv:** Produkt wird exportiert und ist in Katalogen sichtbar
- **Inaktiv:** Temporär deaktiviert, kann reaktiviert werden
- **Eingestellt:** Dauerhaft aus dem Sortiment genommen

Nur aktive Produkte werden exportiert.

### Weitere Tabs

| Tab | Funktion |
|---|---|
| **Varianten** | Varianten anlegen und verwalten (siehe Kapitel 7) |
| **Medien** | Bilder und Dokumente zuordnen (siehe Kapitel 8) |
| **Preise** | Preise pflegen (siehe Kapitel 9) |
| **Relationen** | Beziehungen zu anderen Produkten (Zubehör, Ersatzteile, ...) |
| **Versionen** | Änderungshistorie einsehen, ältere Versionen wiederherstellen |
| **Hierarchien** | Zuordnung zu Hierarchieknoten anzeigen und ändern |

### Relationen

Über den Tab **Relationen** verknüpfen Sie Produkte miteinander. Typische Anwendungsfälle:

- **Zubehör:** Bohrer als Zubehör zur Bohrmaschine
- **Ersatzteile:** Kohlebürsten als Ersatzteil
- **Cross-Selling:** Ähnliche Produkte aus einer anderen Kategorie

**Produktdetail → Tab "Relationen" → + Relation hinzufügen**

Wählen Sie den Relationstyp und das Zielprodukt. Relationen können einseitig oder beidseitig sein.

### Versionen

Jede Speicherung erzeugt automatisch eine neue Version. Über den Tab **Versionen** sehen Sie die komplette Änderungshistorie: Wer hat wann welche Werte geändert?

Um eine ältere Version wiederherzustellen: Version auswählen und **Wiederherstellen** klicken. Die aktuelle Version wird dabei nicht gelöscht — es entsteht eine neue Version mit den alten Werten.

---

## 7. Varianten

Varianten sind Ausprägungen eines Produkts — z.B. verschiedene Farben oder Größen. Sie erben alle Attribute vom Elternprodukt und überschreiben nur die abweichenden Werte.

### Variantenattribute definieren

Zuerst festlegen, welche Attribute die Varianten unterscheiden.

**Produktdetail → Tab "Variant-Attribute"**

Wählen Sie die Attribute, die zwischen Varianten variieren. Beispiel: Farbe, Größe.

### Variante anlegen

**Produktdetail → Tab "Varianten" → + Variante anlegen**

| Feld | Eingabe |
|---|---|
| SKU | `BM-PRO-2000-ROT` |
| Name | Bohrmaschine Pro 2000 Rot |

Nach dem Speichern zeigt die Variantenansicht alle Attribute. Die Werte kommen vom Elternprodukt und sind als **vererbt** gekennzeichnet.

### Werte überschreiben

Klicken Sie auf ein vererbtes Feld und geben Sie einen eigenen Wert ein. Beispiel:

| Attribut | Elternprodukt | Variante Rot |
|---|---|---|
| Farbe | Blau | **Rot** (überschrieben) |
| Gewicht | 2,5 kg | 2,5 kg (vererbt) |
| Beschreibung | Professionelle Schlag... | Professionelle Schlag... (vererbt) |
| EAN | 4012345678901 | **4012345678902** (überschrieben) |

### Vererbung zurücksetzen

Wenn Sie eine Überschreibung löschen, gilt wieder der Wert des Elternprodukts. Änderungen am Elternprodukt werden automatisch an alle Varianten weitergegeben, deren Werte nicht überschrieben sind.

**Merke:** Preise werden **nicht** vererbt. Jede Variante braucht eigene Preise.

---

## 8. Medien

### Medien hochladen

**Sidebar → Medien → + Medien hochladen** (oder Drag-and-Drop in die Medienbibliothek)

Unterstützte Formate: JPG, PNG, GIF, SVG, WebP, PDF, DOCX, XLSX, MP4, WebM.

Jedes Medium erhält automatisch:
- Dateiname, Dateigröße, Abmessungen, MIME-Typ
- Optionale Felder: Alt-Text (übersetzbar), Titel (übersetzbar)

### Asset-Ordner

Medien können in Ordnern organisiert werden — z.B. "Produktbilder", "Datenblätter", "Videos". Die Ordnerstruktur hat keinen Einfluss auf die Zuordnung zu Produkten.

### Verwendungstypen

Jedes zugeordnete Medium erhält einen Verwendungstyp:

| Typ | Bedeutung |
|---|---|
| Teaser | Hauptbild (wird als erstes angezeigt) |
| Galerie | Weitere Produktbilder |
| Datenblatt | Technisches PDF |
| Anleitung | Bedienungsanleitung |
| Video | Produktvideo |
| Sonstige | Alles andere |

### Medien einem Produkt zuordnen

**Produktdetail → Tab "Medien"**

Per Drag-and-Drop aus der Medienbibliothek oder über **+ Medium zuweisen**. Die Reihenfolge bestimmt die Darstellung — das erste Bild ist das Hauptbild.

### Auto-Match

Das System kann Medien anhand des Dateinamens automatisch Produkten zuordnen. Wenn der Dateiname die SKU enthält (z.B. `BM-PRO-2000_front.jpg`), wird die Zuordnung vorgeschlagen.

---

## 9. Preise

### Preisarten definieren

Bevor Preise an Produkten gepflegt werden, müssen die Preisarten definiert sein.

**Sidebar → Preise** (nur als Admin sichtbar)

Beispielhafte Preisarten:

| Preisart | Beschreibung |
|---|---|
| Listenpreis (UVP) | Unverbindliche Preisempfehlung |
| Nettopreis | Einkaufspreis netto |
| Bruttopreis | Endkundenpreis inkl. MwSt. |
| Aktionspreis | Temporärer Sonderpreis |
| Staffelpreis | Preis ab bestimmter Menge |

### Preise am Produkt pflegen

**Produktdetail → Tab "Preise" → + Preis hinzufügen**

| Feld | Eingabe |
|---|---|
| Preisart | Listenpreis (UVP) |
| Betrag | 189,90 |
| Währung | EUR |
| Gültig von | 2025-01-01 (optional) |
| Gültig bis | — (optional) |

Ein Produkt kann mehrere Preise haben — z.B. Listenpreis in EUR, Listenpreis in CHF, Aktionspreis in EUR.

**Wichtig:** Varianten erben keine Preise. Jede Variante benötigt eigene Preiseinträge.

---

## 10. Übersetzungen

### Produktdaten übersetzen

Attribute, die als **übersetzbar** konfiguriert sind, können in verschiedenen Sprachen gepflegt werden.

**Produktdetail → Tab "Attribute" → Sprachumschalter**

Wechseln Sie z.B. von DE auf EN und pflegen Sie die englischen Texte. Die Oberflächensprache bleibt dabei unverändert — Sie können die deutsche Oberfläche nutzen und gleichzeitig englische Produkttexte bearbeiten.

### Metadaten übersetzen (TMS)

Neben Produktdaten gibt es Metadaten: Attributnamen, Wertelisten-Einträge, Hierarchieknoten-Bezeichnungen. Diese werden über das Translation Management System übersetzt.

**Sidebar → Übersetzungen**

Der Ablauf in drei Schritten:

1. **Ingest** — Alle Metadaten werden an den Übersetzungsdienst gesendet
2. **Translate** — Automatische Übersetzung über DeepL, Google Translate oder KI-gestützte Übersetzung
3. **Sync** — Übersetzungen werden zurück in die Datenbank geschrieben

Überprüfte Übersetzungen können als **"Geprüft"** markiert werden. Geprüfte Einträge werden bei der nächsten automatischen Übersetzung nicht überschrieben.

---

## 11. Export

Wenn Produkte gepflegt, Medien zugeordnet und Preise hinterlegt sind, können die Daten exportiert werden.

### Manueller Export über die API

```
GET /api/v1/export/products?status=active&lang=de
```

Filteroptionen: Status, Hierarchieknoten, Attributwerte, Sprache, Zeitraum.

### Export-Jobs einrichten

Für regelmäßige Exporte: Wiederverwendbare Jobs mit Zeitplan.

**Sidebar → Export → + Neuer Export-Job**

| Feld | Eingabe |
|---|---|
| Name | Täglicher Shopexport |
| Format | JSON |
| Filter: Status | Aktiv |
| Filter: Hierarchie | Produktkatalog |
| Zeitplan | Täglich, 06:00 Uhr |
| Zustellung | SFTP |

Jede Ausführung wird protokolliert (Zeitstempel, Anzahl Produkte, Ergebnis).

### Delta-Export

Nur geänderte Produkte exportieren:

```
GET /api/v1/export/products?updated_after=2025-03-20T08:00:00Z
```

Geeignet für regelmäßige inkrementelle Aktualisierungen.

### Publixx-Mappings

Für den Publixx-Export wird definiert, welches PIM-Feld auf welches Zielfeld gemappt wird.

**Sidebar → Export → Publixx-Mappings**

Jedes Mapping hat:
- **Quellfeld** — das PIM-Attribut
- **Zielfeld** — der Feldname im Publixx-Datensatz
- **Typ** — text, unit_value, media_url, price, variant_array

### Offline-Katalog

Ein statischer HTML/JS-Katalog, der ohne Server funktioniert. Geeignet für Messen, E-Mail-Verteilung oder USB-Sticks.

---

## 12. Checkliste

Was wir eingerichtet haben:

- [ ] Einheitengruppen und Einheiten (Gewicht, Länge, ...)
- [ ] Attributgruppen (Stammdaten, Technische Daten, Marketing, Logistik)
- [ ] Wertelisten (Farben, Material, ...)
- [ ] Attribute mit Datentypen und Eigenschaften
- [ ] Master-Hierarchie mit Kategorien
- [ ] Attributgruppen an Hierarchieknoten zugewiesen
- [ ] Erstes Produkt angelegt und Attributwerte gepflegt
- [ ] Varianten erstellt
- [ ] Medien hochgeladen und zugeordnet
- [ ] Preisarten definiert und Preise gepflegt
- [ ] Übersetzungen gepflegt
- [ ] Export konfiguriert

### Nächste Schritte

- **Massenimport:** Produkte per Excel importieren (14-Tab-Struktur). Siehe Hilfe-Portal.
- **Benutzer anlegen:** Weitere Benutzer einladen und Rollen zuweisen. Siehe Verkaufs-Mappe.
- **Workflow nutzen:** Aufgaben erstellen und im Team koordinieren. Siehe Verkaufs-Mappe.

### Hilfe

| Ressource | Link |
|---|---|
| Hilfe-Portal (DE) | [smartentities.de/web/help/de/](https://smartentities.de/web/help/de/) |
| Hilfe-Portal (EN) | [smartentities.de/web/help/en/](https://smartentities.de/web/help/en/) |
| Quellcode | [github.com/incoxx/publixx-pim](https://github.com/incoxx/publixx-pim) |

---

*anyPIM ist ein Produkt von Smart Entities.*

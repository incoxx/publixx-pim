# anyPIM — Verkaufs-Mappe

---

## Inhaltsverzeichnis

1. [Das Produkt](#1-das-produkt)
2. [Architektur](#2-architektur)
3. [Technische Voraussetzungen](#3-technische-voraussetzungen)
4. [Installation](#4-installation)
5. [Einrichtung (Quick Guide)](#5-einrichtung-quick-guide)
6. [Teams und Rollen](#6-teams-und-rollen)
7. [Workflow und Aufgaben](#7-workflow-und-aufgaben)
8. [Daily Business — Datenpflege im Alltag](#8-daily-business--datenpflege-im-alltag)
9. [Kurz erklärt: Einrichtung Attribute](#9-kurz-erklärt-einrichtung-attribute)
10. [Einrichtung Export](#10-einrichtung-export)
11. [Einrichtung Website](#11-einrichtung-website)
12. [Troubleshooting](#12-troubleshooting)
13. [FAQ](#13-faq)

---

## 1. Das Produkt

### Was ist anyPIM?

anyPIM ist ein Open-Source Product Information Management System (PIM). Es zentralisiert Produktdaten, die sonst verstreut in Tabellen, ERP-Systemen und Shop-Backends liegen, an einem Ort.

**Lizenz:** GPL-3.0-only — kostenlos nutzbar, veränderbar und weitergebbar.

### Wofür?

- **Ein System, eine Wahrheit.** Produktnamen, Beschreibungen, technische Daten, Bilder, Preise — alles wird zentral gepflegt und von dort an alle Kanäle verteilt.
- **Multi-Channel-Verteilung.** Dasselbe Produkt in unterschiedlichen Formaten an Webshops, Marktplätze, Printkataloge und B2B-Partner ausspielen.
- **Teamarbeit.** Verschiedene Abteilungen (Einkauf, Marketing, Produktmanagement) arbeiten gleichzeitig am selben Produktstamm — mit rollenbasiertem Zugriff.

### Für wen?

- Produktteams mit 100 bis 200.000+ Produkten
- Händler, Großhändler, Hersteller, Distributoren
- Unternehmen mit komplexen Produkthierarchien und Varianten
- Multi-Channel-Vertrieb mit unterschiedlichen Datenformaten

### In Zahlen

| Kennzahl | Wert |
|---|---|
| Datenbank-Tabellen | 85 |
| Eloquent Models | 70 |
| REST-API-Endpoints | ~377 |
| Attribut-Datentypen | 13 |
| Migrationen | 104 |
| Vue-Komponenten | 195+ |
| Unterstützte Sprachen (UI) | Deutsch, Englisch |
| Produktsprachen (Inhalte) | Unbegrenzt |

---

## 2. Architektur

### Tech-Stack

| Schicht | Technologie | Version |
|---|---|---|
| Backend | PHP / Laravel | 8.3+ / 11 |
| Frontend | Vue.js / Vite | 3 |
| CSS-Framework | Tailwind CSS / DaisyUI | 4 / 5 |
| Datenbank | MySQL | 8.0+ |
| Cache & Queue | Redis | 6+ |
| Queue-Worker | Laravel Horizon | 5 |
| Webserver | Apache | 2.4 |
| Authentifizierung | Laravel Sanctum | 4 |
| Berechtigungen | Spatie Permissions | 6 |
| PDF-Erzeugung | DomPDF | — |

### Systemaufbau

```
                    Browser (Vue 3 SPA)
                          |
                     HTTPS / REST
                          |
               +----- Apache 2.4 -----+
               |                      |
         Laravel 11               Static Assets
         (PHP 8.4)                (Vite Build)
               |
    +----------+----------+
    |          |           |
 Controllers  Services   Jobs
    |          |           |
    +----Models (55)-------+
         |           |
      MySQL 8      Redis
    (58 Tabellen)  (Cache, Queue, Session)
```

### Datenmodell

- **EAV-Architektur (Entity-Attribute-Value):** Beliebig viele Attribute ohne Schema-Änderung. Produkte, Hierarchieknoten und Medien nutzen EAV.
- **Materialized Path:** Hierarchieknoten speichern den vollständigen Pfad — schnelle Baumabfragen in O(1).
- **UUID-Primärschlüssel:** Alle Geschäftstabellen verwenden UUIDs (CHAR(36)).
- **Zweistufige Vererbung:** Hierarchieknoten vererben an Produkte, Produkte vererben an Varianten.

---

## 3. Technische Voraussetzungen

### Systemanforderungen

| Komponente | Anforderung |
|---|---|
| Betriebssystem | Ubuntu 24.04 LTS (empfohlen), 22.04+ |
| PHP | 8.4+ |
| MySQL | 8.0+ |
| Redis | 6+ |
| Node.js | 20 LTS (mit npm) |
| Composer | 2.x |
| Apache | 2.4 (mit mod_rewrite, mod_headers, mod_alias) |
| Supervisor | Für Laravel Horizon |
| Git | Für Deployment und Updates |

### PHP-Erweiterungen

`mysql`, `redis`, `mbstring`, `xml`, `zip`, `gd`, `bcmath`, `curl`, `intl`

### Hardware-Empfehlungen

| | Minimum | Empfohlen |
|---|---|---|
| RAM | 2 GB | 4 GB+ |
| CPU | 1 vCPU | 4 vCPU |
| Festplatte | 10 GB | 160+ GB SSD |

Für Bestände über 100.000 Produkte: 8 vCPU, 16 GB RAM, NVMe-SSD.

---

## 4. Installation

### Quick Start (ca. 10 Minuten auf einem frischen Ubuntu-Server)

**Schritt 1 — Klonen:**

```bash
git clone https://github.com/incoxx/publixx-pim.git /var/www/publixx-pim
cd /var/www/publixx-pim
```

**Schritt 2 — Installieren:**

```bash
sudo bash setup.sh
```

**Schritt 3 — Prüfen:**

```bash
bash healthcheck.sh
```

**Schritt 4 — Anmelden:**

URL im Browser öffnen. Standard-Zugangsdaten:

| E-Mail | Passwort |
|---|---|
| `admin@example.com` | `password` |

Nach dem ersten Login das Passwort ändern.

### Was `setup.sh` macht

| Schritt | Aktion |
|---|---|
| 1/10 | System-Pakete aktualisieren |
| 2/10 | PHP 8.4 + Erweiterungen installieren |
| 3/10 | Apache installieren und konfigurieren |
| 4/10 | MySQL installieren, Datenbank und Benutzer anlegen |
| 5/10 | Redis installieren und konfigurieren |
| 6/10 | Node.js 20 LTS installieren |
| 7/10 | Composer 2 installieren |
| 8/10 | Laravel einrichten (.env, Migrationen, Seeder, Admin-Benutzer) |
| 9/10 | Frontend bauen (npm ci + npm run build) |
| 10/10 | Apache VHost, Supervisor/Horizon, Cron, Berechtigungen konfigurieren |

### Deployment-Modi

**Root-Modus** — PIM ist die einzige Anwendung auf der Domain:

```
https://pim.example.com → /var/www/publixx-pim/public
```

**Subdirectory-Modus** — PIM läuft unter einem bestehenden VHost:

```
https://example.com/web → /var/www/publixx-pim/public
```

Beide Modi werden durch `setup.sh` automatisch konfiguriert, inklusive optionalem Let's Encrypt SSL.

### Updates

```bash
sudo bash update.sh
```

Automatischer Ablauf: Maintenance-Modus, Git Pull, Composer Install, Migrationen, Frontend-Build, Cache-Clear, Service-Neustart.

---

## 5. Einrichtung (Quick Guide)

### Erste Schritte nach der Installation

**1. Passwort ändern**

Nach dem ersten Login über den Menüpunkt **Benutzer** das Standardpasswort ändern.

**2. Attributgruppen anlegen**

Unter **Attribute > Attributgruppen** die Gruppen definieren, die zur Produktstruktur passen. Beispiele: "Technische Daten", "Marketing", "Logistik".

**3. Attribute anlegen**

Unter **Attribute** mit **+ Neues Attribut** die benötigten Felder erstellen. Pro Attribut definieren:

- Technischer Name (snake_case, z.B. `gewicht_kg`)
- Anzeigename (DE/EN)
- Datentyp (String, Number, Float, Date, Flag, Selection, ...)
- Übersetzbar ja/nein
- Pflichtfeld ja/nein
- Vererbbar ja/nein

**4. Hierarchie erstellen**

Unter **Hierarchien** eine Master-Hierarchie anlegen (z.B. Produktkategorien). Knoten per Drag-and-Drop sortieren.

**5. Erstes Produkt anlegen**

Unter **Produkte** mit **+ Neues Produkt** ein Produkt erstellen. SKU vergeben, Hierarchieknoten zuweisen, Attributwerte pflegen.

**6. Benutzer und Rollen einrichten**

Unter **Benutzer** weitere Benutzer anlegen und Rollen zuweisen:

| Rolle | Rechte |
|---|---|
| Admin | Vollzugriff |
| Data Steward | Datenmodell verwalten (Attribute, Hierarchien) |
| Product Manager | Produkte bearbeiten |
| Viewer | Nur Lesen |
| Export Manager | Exporte konfigurieren und ausführen |
| API Designer | API-Templates und Zugangslinks verwalten |
| Project Management | Projekte und Produktzuordnungen verwalten |

---

## 6. Teams und Rollen

### Rollenbasierte Zugriffskontrolle

anyPIM steuert den Zugriff über ein RBAC-System (Role-Based Access Control). Jeder Benutzer erhält genau eine Rolle, die bestimmt, welche Bereiche sichtbar und welche Aktionen erlaubt sind.

### Die sieben Systemrollen

| Rolle | Verantwortung | Typische Aufgaben |
|---|---|---|
| **Admin** | Vollzugriff auf alle Bereiche | Benutzer verwalten, Systemkonfiguration, alle Datenbereiche |
| **Data Steward** | Datenmodell und Struktur | Attribute, Hierarchien, Wertelisten, Einheiten anlegen und pflegen |
| **Product Manager** | Produktdaten | Produkte anlegen, bearbeiten, Varianten pflegen, Medien zuordnen |
| **Viewer** | Nur Lesen | Produkte und Kataloge einsehen, keine Änderungen möglich |
| **Export Manager** | Datenexport | Export-Jobs konfigurieren, ausführen und überwachen |
| **API Designer** | API-Schnittstellen | API-Templates und Zugangslinks verwalten |
| **Project Management** | Projektsteuerung | Projekte anlegen, Produkte zuordnen, Teamzusammenarbeit |

### Zugriff auf Hierarchieknoten einschränken

Rollen können auf bestimmte Bereiche der Master-Hierarchie beschränkt werden. Ein Product Manager, der nur den Hierarchieknoten "Elektrowerkzeuge" zugewiesen hat, sieht ausschließlich die Produkte in diesem Bereich.

### Benutzer anlegen

1. Als Admin unter **Benutzer** > **+ Neuer Benutzer**
2. Vorname, Nachname, E-Mail, Passwort, Rolle und Sprache (DE/EN) festlegen
3. Optional: Zugriff auf bestimmte Hierarchieknoten einschränken

Der Benutzer kann sich sofort anmelden.

### Berechtigungen im Detail

Berechtigungen sind pro Entität und Aktion definiert (z.B. `products.create`, `products.update`, `products.delete`). Die Rollen bündeln diese Berechtigungen zu sinnvollen Profilen. Menüpunkte und Schaltflächen (Speichern, Löschen) werden automatisch ein- oder ausgeblendet.

---

## 7. Workflow und Aufgaben

### Aufgabenmanagement

Das Workflow-Modul koordiniert die Zusammenarbeit im Team. Aufgaben können erstellt, zugewiesen und nachverfolgt werden — direkt im PIM, ohne externes Ticketsystem.

### Aufgabe erstellen

Unter **Workflow** > **+ Neue Aufgabe**:

| Feld | Beschreibung | Pflicht |
|---|---|---|
| Titel | Kurze Bezeichnung der Aufgabe | Ja |
| Beschreibung | Detaillierte Beschreibung | Nein |
| Zugewiesen an | Verantwortlicher Benutzer | Ja |
| Fällig am | Fertigstellungsdatum | Nein |
| Produkt | Verknüpfung mit einem Produkt | Nein |
| Priorität | Niedrig, Normal, Hoch | Ja |

Aufgaben können auch direkt aus der Produktdetailansicht heraus erstellt werden — das Produkt wird dann automatisch verknüpft.

### Statusworkflow

```
Offen  ──>  In Bearbeitung  ──>  Erledigt
```

Jeder Status kann manuell gesetzt werden. Ein Wechsel von "Erledigt" zurück zu "Offen" ist möglich.

### Fälligkeitsanzeige

Aufgaben mit Fälligkeitsdatum werden farblich gekennzeichnet:

- **Grün** — innerhalb des Zeitrahmens
- **Gelb** — fällig in den nächsten 48 Stunden
- **Rot** — überfällig

### Kommentare

Innerhalb einer Aufgabe können Kommentare hinterlassen werden, um den Fortschritt zu dokumentieren oder Rückfragen zu stellen. Jeder Kommentar zeigt Benutzer, Zeitstempel und Text.

### Filtern und Suchen

Die Aufgabenübersicht bietet Filter nach:

- Status (Offen, In Bearbeitung, Erledigt)
- Zugewiesener Benutzer
- Fälligkeit (überfällig, diese Woche, etc.)
- Volltextsuche in Titel und Beschreibung

---

## 8. Daily Business — Datenpflege im Alltag

### Die Oberfläche

Nach dem Login gelangen Sie auf das Dashboard. Alle Funktionsbereiche sind über die Sidebar erreichbar:

| Bereich | Funktion |
|---|---|
| Suche | Globale Produktsuche und PQL-Abfragen |
| Produkte | Produktverwaltung mit Varianten und Versionen |
| Hierarchien | Baumstrukturen für Kategorien und Ausgabekanäle |
| Attribute | Attributdefinitionen und Konfiguration |
| Produkttypen | Zuordnung von Attributen zu Produkttypen |
| Attributgruppen | Logische Gruppierung von Attributen |
| Wertelisten | Auswahllisten für Selection-Attribute |
| Import / Export | Datenimport und -export |
| Medien | Medienbibliothek |
| Preise | Preisarten und Produktpreise |
| Workflow | Aufgabenmanagement |
| Benutzer | Benutzerverwaltung (nur Admin) |

### Produkte suchen und filtern

Die Produktliste bietet mehrere Filtermöglichkeiten:

- **Volltextsuche** — nach SKU, Name und durchsuchbaren Attributen
- **Statusfilter** — Entwurf, Aktiv, Inaktiv
- **Produkttypfilter** — nur Produkte eines bestimmten Typs anzeigen
- **Sortierung** — Klick auf Spaltenüberschrift (SKU, Name, Status, Geändert)

Aktive Filter werden als Chips angezeigt und können einzeln entfernt werden.

### Produkt anlegen und bearbeiten

**Anlegen:** **+ Neues Produkt** > SKU, Name, Produkttyp, optional EAN und Status vergeben.

**Bearbeiten:** Produktdetailansicht mit Registerreitern (Tabs):

- **Attribute** — Attributwerte in der gewählten Sprache pflegen
- **Varianten** — Varianten anlegen, Werte überschreiben
- **Medien** — Bilder und Dokumente per Drag-and-Drop zuordnen
- **Preise** — Preise nach Preisart und Region pflegen
- **Relationen** — Beziehungen zu anderen Produkten (Zubehör, Ersatzteile, etc.)
- **Versionen** — Versionshistorie einsehen, Zeitplanung für Veröffentlichungen
- **Hierarchien** — Zuordnung zu Hierarchieknoten

### Varianten verwalten

Varianten erben Attributwerte vom Elternprodukt. In der Praxis:

1. Elternprodukt anlegen und Attributwerte pflegen
2. Varianten erzeugen (z.B. Farbvarianten)
3. Auf Variantenebene nur die abweichenden Werte überschreiben (z.B. Farbe, EAN)
4. Alle nicht überschriebenen Werte kommen automatisch vom Elternprodukt

### Medien zuordnen

Bilder, PDFs und andere Dateien werden über die Medienbibliothek verwaltet. Zuordnung zu Produkten per Drag-and-Drop. Medien können mit Verwendungstypen klassifiziert werden (Teaser, Galerie, Technische Zeichnung, etc.).

### Preise pflegen

Preise werden pro Preisart (UVP, Einkaufspreis, Händlerpreis), Region und Währung gepflegt. Staffelpreise und Gültigkeitszeiträume werden unterstützt.

### Bulk-Operationen

Für Massenänderungen steht ein Spreadsheet-ähnlicher Bulk-Editor zur Verfügung. Mehrere Produkte gleichzeitig bearbeiten, ohne jedes einzeln öffnen zu müssen.

### Tastenkombinationen

| Kürzel | Aktion |
|---|---|
| `Strg + S` | Aktuellen Datensatz speichern |
| `Strg + K` | Befehlspalette (Command Palette) öffnen |
| `Esc` | Dialog oder Modal schließen |
| `Strg + F` | Suche im aktuellen Bereich öffnen |

### Sprachumschaltung

Die Oberflächensprache (DE/EN) ist unabhängig von der Inhaltssprache der Produktdaten. Sie können z.B. die Oberfläche auf Deutsch nutzen und gleichzeitig englische Produkttexte bearbeiten.

---

## 9. Kurz erklärt: Einrichtung Attribute

### Das EAV-Prinzip

anyPIM speichert Produktdaten nach dem Entity-Attribute-Value-Modell. Das bedeutet: Attribute werden nicht als Datenbankspalten angelegt, sondern als konfigurierbare Datensätze. Ein neues Attribut erfordert keinen Eingriff in die Datenbank — es wird über die Oberfläche definiert und steht sofort zur Verfügung.

### 13 Datentypen

| Datentyp | Beschreibung | Beispiel |
|---|---|---|
| String | Text (ein-/mehrzeilig) | Produktname, Beschreibung |
| Number | Ganzzahl | Stückzahl, Mindestbestellmenge |
| Float | Dezimalzahl mit optionaler Einheit | 2,5 kg, 120 cm |
| Date | Datum (YYYY-MM-DD) | Erscheinungsdatum |
| Flag | Ja/Nein | "Ist Gefahrgut" |
| Selection | Auswahl aus Werteliste | Farbe, Material |
| Dictionary | Schlüssel-Wert-Paare (JSON) | Technische Spezifikationen |
| Collection | Wiederholbare Einträge (JSON-Array) | Zertifikate, Normen |
| RichText | Formatierter HTML-Text | Marketingbeschreibung |
| Hyperlink | URL | Herstellerseite |
| ImageLink | Bild-URL | Produktbild-Referenz |
| PdfLink | PDF-URL | Datenblatt |
| VideoLink | Video-URL | Produktvideo |

### Attributgruppen

Attribute werden in logische Gruppen zusammengefasst (z.B. "Technische Daten", "Marketing", "Logistik"). Gruppen steuern die Reihenfolge und Übersichtlichkeit in der Produktbearbeitung.

### Wertelisten

Für Attribute vom Typ **Selection**: Eine Werteliste definiert die erlaubten Optionen (z.B. Farbe: Rot, Blau, Grün). Werte sind mehrsprachig pflegbar.

### Einheiten und Einheitengruppen

Für Attribute vom Typ **Float**: Einheiten werden in Gruppen organisiert (z.B. Gruppe "Gewicht" mit kg, g, lb). Umrechnungsfaktoren ermöglichen automatische Konvertierung.

### Attributansichten

Attributansichten definieren eine Teilmenge der Attribute für bestimmte Anwendungsfälle. So sieht z.B. die Marketing-Abteilung nur die für sie relevanten Felder, ohne von technischen Daten abgelenkt zu werden.

### Vererbung

Die Vererbung funktioniert auf zwei Ebenen:

1. **Hierarchie → Produkt:** Attributwerte, die auf einem Hierarchieknoten definiert sind, werden automatisch an alle zugeordneten Produkte vererbt.
2. **Produkt → Variante:** Varianten erben Werte vom Elternprodukt. Vererbte Werte können auf Variantenebene überschrieben werden — wird die Überschreibung gelöscht, gilt wieder der Elternwert.

Ob ein Attribut vererbt wird, steuert die Eigenschaft `inheritable` pro Attribut.

---

## 10. Einrichtung Export

### Exportformate

| Format | Beschreibung |
|---|---|
| JSON | Vollständiger PIM-Export mit 18 geordneten Abschnitten. Geeignet für Systemsicherung und Migration. |
| Excel | Konfigurierbares Tabellenformat. |
| CSV | Flaches Format für Altsysteme. |
| Publixx | Spezialisierter Export mit Mapping-Konfiguration für die Publixx-Plattform. |
| BMEcat | B2B-Industriestandard (XML). |
| Offline-Katalog | Statisches HTML/JS — als eigenständiger Produktkatalog verteilbar. |
| PDF | Produktdatenblätter und Reports. |

### Export-Pipeline

Jeder Export durchläuft vier Phasen:

1. **Anfrage:** API-Endpoint oder PQL-Query mit Filtern
2. **Filterung:** Nach Status, Hierarchie, Attributen, Delta-Zeitstempel
3. **Anreicherung:** Attributwerte, Medien, Preise, Relationen, Varianten laden
4. **Transformation:** Mapping anwenden, Zielformat erzeugen

### Export-Jobs

Export-Jobs sind wiederverwendbare, geplante Exporte:

- **Zeitplan:** Cron-Expression (z.B. täglich, stündlich)
- **Zustellung:** Dateisystem, SFTP oder Webhook
- **Filter:** Status, Hierarchieknoten, Attributwerte, Datumsbereich
- **Format:** Frei wählbar pro Job
- **Protokoll:** Jede Ausführung wird mit Zeitstempel und Ergebnis protokolliert

### Delta-Export

Nur geänderte Produkte exportieren:

```
GET /api/v1/export/products?updated_after=2025-01-15T08:00:00Z
```

### Publixx-Mappings

Für den Publixx-Export werden Quellfelder aus dem PIM auf Zielfelder der Publixx-Plattform gemappt. Konfiguration über **Export > Publixx-Mappings** in der Oberfläche.

---

## 11. Einrichtung Website

### Öffentlicher Produktkatalog

anyPIM enthält einen integrierten, öffentlichen Produktkatalog. Dieser ist ohne Authentifizierung zugänglich und bietet:

- Kategorienavigation (basierend auf Ausgabe-Hierarchien)
- Facettierte Filter (nach beliebigen Attributen)
- Produktdetailseiten
- Kontaktformular

Der Katalog wird über die API unter `/api/v1/catalog/...` bereitgestellt.

### Asset-Katalog

Ein separater Medienkatalog mit Ordnerstruktur und Download-Funktion. Geeignet für die Verteilung von Produktbildern, Datenblättern und technischen Dokumenten.

### Zugangslinks

Temporäre, token-basierte Links für externen Zugriff — ohne Benutzerkonto. Praktisch für Einkäufer, Agenturen oder Partner, die Zugriff auf einen definierten Produktbereich benötigen.

### Offline-Katalog

Ein vollständig statischer HTML/JS-Katalog, der ohne Server funktioniert. Kann per E-Mail, USB-Stick oder Download verteilt werden.

### Shopware 6 Connector

anyPIM enthält einen Connector für Shopware 6:

- Produktdaten synchronisieren
- Medien hochladen
- OAuth-basierte Authentifizierung

Konfiguration über die Connector-Einstellungen in der Administration.

---

## 12. Troubleshooting

### Systemprüfung

```bash
bash healthcheck.sh
```

Prüft alle Dienste (Apache, MySQL, Redis, Horizon, Cron) und meldet den Status.

API-Healthcheck:

```bash
curl https://pim.example.com/api/v1/health
```

### Log-Dateien

| Datei | Inhalt |
|---|---|
| `storage/logs/laravel.log` | Anwendungsfehler und Warnungen |
| `storage/logs/horizon.log` | Queue-Worker-Logs (Import/Export) |
| `/var/log/apache2/pim-error.log` | Webserver-Fehler |
| `/var/log/mysql/error.log` | Datenbank-Fehler |

Echtzeit-Überwachung:

```bash
tail -f storage/logs/laravel.log
```

### Häufige Probleme

**Passwort vergessen:**

Als Admin: **Benutzer** > Benutzer auswählen > **Passwort zurücksetzen**.

Über die Kommandozeile:

```bash
php artisan tinker
```

```php
$user = \App\Models\User::where('email', 'benutzer@example.com')->first();
$user->password = bcrypt('neues_passwort');
$user->save();
```

**Import-Fehler:**

Der Import ist dreistufig (Upload > Validierung > Ausführung). Fehler werden in der Validierungsphase erkannt und mit Zeile, Spalte und Fehlerbeschreibung ausgegeben — bevor Daten geschrieben werden. Excel-Datei korrigieren und erneut hochladen.

**Vererbung greift nicht:**

Prüfen, ob das betreffende Attribut als `inheritable` konfiguriert ist. Nur vererbbare Attribute werden von Hierarchieknoten und Elternprodukten an Varianten weitergegeben.

**Performance-Probleme:**

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

Redis-Speicher und Horizon-Worker-Konfiguration prüfen.

### Dienste neu starten

```bash
sudo systemctl restart apache2
sudo systemctl restart mysql
sudo systemctl restart redis
sudo supervisorctl restart horizon
```

---

## 13. FAQ

### Wie viele Produkte kann das System verwalten?

anyPIM ist für 100.000+ Produkte mit zahlreichen Attributwerten, Varianten und Medien ausgelegt. Auf empfohlener Hardware (8 vCPU, 16 GB RAM, NVMe-SSD) wurden in Tests über 200.000 Produkte performant verwaltet.

### Welche Rollen gibt es?

Admin, Data Steward, Product Manager, Viewer, Export Manager. Die Zugriffskontrolle ist feingranular — Benutzer können auf bestimmte Hierarchieknoten eingeschränkt werden.

### Kann ich neue Sprachen hinzufügen?

Ja. Inhaltssprachen für Produktdaten sind unbegrenzt. Neue Sprache über die Excel-Importdatei (Tab `01_Sprachen`) oder per API anlegen. Attribute müssen als `translatable` konfiguriert sein.

### Was ist PQL?

PQL (Product Query Language) ist eine SQL-ähnliche Abfragesprache für Produktsuchen über alle Attribute. Unterstützt Vergleichsoperatoren, logische Verknüpfungen, Fuzzy-Suche, phonetische Suche (Kölner Phonetik) und gewichtete Volltextsuche.

```sql
SELECT sku, name, preis FROM products
WHERE kategorie = 'Elektrowerkzeuge'
  AND preis BETWEEN 50 AND 200
ORDER BY SCORE
```

### Unter welcher Lizenz steht anyPIM?

GPL-3.0-only. Kostenlos nutzbar, veränderbar und weitergebbar. Bei Weitergabe (auch verändert) muss der Quellcode unter derselben Lizenz verfügbar gemacht werden.

### Kann ich anyPIM in Docker betreiben?

anyPIM ist primär für den nativen Betrieb auf Linux-Servern konzipiert. Ein Docker-Setup ist möglich, wird aber derzeit nicht offiziell bereitgestellt.

### Wie sichere ich das System?

Drei Komponenten: tägliches Datenbank-Backup (`mysqldump`), tägliches Medien-Backup (`rsync`), Konfigurationssicherung nach Änderungen. Automatisierung über Cron-Jobs empfohlen.

---

## Hilfe und Ressourcen

| Ressource | Link |
|---|---|
| Quellcode | [github.com/incoxx/publixx-pim](https://github.com/incoxx/publixx-pim) |
| Hilfe-Portal (DE) | [smartentities.de/web/help/de/](https://smartentities.de/web/help/de/) |
| Hilfe-Portal (EN) | [smartentities.de/web/help/en/](https://smartentities.de/web/help/en/) |
| API-Dokumentation | [API Reference](https://github.com/incoxx/publixx-pim/blob/main/docs/reference/api.md) |
| Feature-Übersicht | [Features](https://github.com/incoxx/publixx-pim/blob/main/docs/features/features.md) |
| Installationsanleitung | [Installation](https://github.com/incoxx/publixx-pim/blob/main/docs/operations/install.md) |
| Datenbankschema | [Database Schema](https://github.com/incoxx/publixx-pim/blob/main/docs/reference/database.md) |

---

*anyPIM ist ein Produkt von Smart Entities. Alle Angaben basieren auf dem aktuellen Entwicklungsstand.*

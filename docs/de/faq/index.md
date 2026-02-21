---
title: Häufig gestellte Fragen (FAQ)
---

# Häufig gestellte Fragen (FAQ)

Hier finden Sie Antworten auf die am häufigsten gestellten Fragen zum Publixx PIM. Die Fragen sind thematisch gruppiert und decken Bereiche von der alltäglichen Nutzung bis zur Systemadministration ab.

## Benutzerverwaltung

### Wie setze ich ein vergessenes Passwort zurück?

Es gibt zwei Möglichkeiten, ein vergessenes Passwort zurückzusetzen:

**Als Administrator:**
Navigieren Sie in der Benutzeroberfläche zu **Benutzer**, wählen Sie den betroffenen Benutzer aus und klicken Sie auf **Passwort zurücksetzen**. Das neue Passwort wird dem Benutzer per E-Mail zugesendet.

**Über die Kommandozeile:**
Falls kein Administrator-Zugang verfügbar ist, können Sie das Passwort direkt über Artisan zurücksetzen:

```bash
php artisan tinker
```

```php
$user = \App\Models\User::where('email', 'benutzer@example.com')->first();
$user->password = bcrypt('neues_passwort');
$user->save();
```

::: warning Hinweis
Fordern Sie den Benutzer auf, das Passwort nach dem ersten Login umgehend zu ändern.
:::

### Wie kann ich Benutzer auf bestimmte Produktkategorien einschränken?

Das Publixx PIM unterstützt eine feingranulare Zugriffskontrolle über das Rollen- und Berechtigungssystem. So richten Sie eine Einschränkung ein:

1. Erstellen Sie unter **Benutzer > Rollen** eine neue Rolle (z. B. "Produktmanager Elektro").
2. Weisen Sie der Rolle die gewünschten Berechtigungen zu (z. B. Produkte lesen, bearbeiten -- aber nicht löschen).
3. Beschränken Sie den Zugriff über Hierarchie-Knoten, indem Sie der Rolle nur bestimmte Knoten der Master-Hierarchie zuweisen.
4. Weisen Sie die Rolle dem entsprechenden Benutzer zu.

Benutzer mit eingeschränkten Rollen sehen ausschliesslich die Produkte, die den zugewiesenen Hierarchie-Knoten zugeordnet sind.

## Produktdaten

### Wie viele Produkte kann das System verwalten?

Das Publixx PIM ist für Produktbestände von **100.000+ Produkten** mit jeweils zahlreichen Attributwerten, Varianten und Medien ausgelegt. Auf der empfohlenen Hardware-Konfiguration (8 vCPU, 16 GB RAM, NVMe-SSD) wurden in internen Tests Bestände mit über 200.000 Produkten und mehreren Millionen Attributwerten performant verwaltet.

Die tatsächliche Leistungsfähigkeit hängt von folgenden Faktoren ab:

- Anzahl der Attribute pro Produkt
- Anzahl der Varianten pro Produkt
- Komplexität der PQL-Abfragen
- Gleichzeitige Benutzeranzahl
- Hardware-Ausstattung des Servers

::: tip Empfehlung
Für Bestände über 100.000 Produkte empfehlen wir die in den [Voraussetzungen](/de/installation/voraussetzungen) beschriebene erweiterte Hardware-Konfiguration.
:::

### Wie erben Varianten die Werte vom Elternprodukt?

Die Vererbung funktioniert nach dem Prinzip der **Überschreibung auf Variantenebene**:

1. **Standardverhalten**: Eine Variante erbt automatisch alle Attributwerte des Elternprodukts, sofern das Attribut als **vererbbar** (`inheritable`) konfiguriert ist.
2. **Eigene Werte**: Sobald ein Attributwert direkt auf der Variante gesetzt wird, überschreibt dieser den vererbten Wert.
3. **Zurücksetzen**: Wird ein überschriebener Wert auf der Variante gelöscht, greift wieder der Wert des Elternprodukts.
4. **Propagierung**: Änderungen am Elternprodukt werden automatisch an alle Varianten weitergegeben, deren Attributwerte nicht überschrieben wurden.

Dieses Verhalten lässt sich pro Attribut über die Eigenschaft `inheritable` steuern. Attribute, die nicht als vererbbar markiert sind, müssen auf jeder Variante individuell gepflegt werden.

## Import

### Was kann ich tun, wenn beim Import Fehler auftreten?

Der Importprozess im Publixx PIM ist dreistufig aufgebaut (Upload, Validierung, Ausführung). Fehler werden in der Validierungsphase erkannt und detailliert gemeldet, **bevor** Daten in die Datenbank geschrieben werden.

Bei Validierungsfehlern gehen Sie wie folgt vor:

1. **Fehlerbericht prüfen**: Nach der Validierung erhalten Sie einen detaillierten Bericht, der jeden Fehler mit Zeile, Spalte und Fehlerbeschreibung auflistet.
2. **Excel-Datei korrigieren**: Beheben Sie die gemeldeten Fehler in Ihrer Excel-Datei. Häufige Ursachen sind:
   - Fehlende Pflichtfelder (z. B. SKU, Produktname)
   - Ungültige Datentypen (z. B. Text statt Zahl)
   - Unbekannte Referenzen (z. B. Attributname existiert nicht)
   - Doppelte Einträge innerhalb derselben Datei
3. **Erneut hochladen**: Laden Sie die korrigierte Datei erneut hoch und starten Sie die Validierung.
4. **Vorschau nutzen**: In der Vorschau sehen Sie vor der Ausführung, welche Datensätze angelegt, aktualisiert oder übersprungen werden.

::: info Fuzzy Matching
Das System erkennt Tippfehler bei Referenzen (z. B. Attributnamen, Hierarchieknoten) automatisch. Liegt die Ähnlichkeit über 85 %, wird ein Korrekturvorschlag angezeigt.
:::

### Welche Tabs enthält die Excel-Importdatei?

Die Importdatei besteht aus 14 Tabellenblättern (Tabs), die in einer definierten Abhängigkeitsreihenfolge verarbeitet werden. Eine vollständige Beschreibung finden Sie unter [Excel-Format](/de/import/excel-format). Die Tabs umfassen unter anderem Sprachen, Attributgruppen, Einheiten, Wertelisten, Attribute, Hierarchien, Produkte und Produktwerte.

## Export

### Wie konfiguriere ich Export-Mappings?

Export-Mappings definieren, wie PIM-Daten in das Zielformat transformiert werden. Für den Publixx-Export konfigurieren Sie die Mappings wie folgt:

1. Navigieren Sie zu **Export > Publixx-Mappings**.
2. Erstellen Sie ein neues Mapping oder bearbeiten Sie ein bestehendes.
3. Definieren Sie für jedes Zielfeld die Quell-Zuordnung:
   - **Quellfeld** (`source`): Das PIM-Attribut oder Systemfeld
   - **Zielfeld** (`target`): Der Feldname im Publixx-Datensatz
   - **Typ** (`type`): Der Mapping-Typ (z. B. `text`, `unit_value`, `media_url`, `price`)
4. Speichern Sie das Mapping.

Mappings können auch über die API verwaltet werden. Details finden Sie unter [Publixx-Export](/de/export/publixx-export).

### Was ist der Unterschied zwischen Vollexport und Delta-Export?

- **Vollexport**: Exportiert alle Produkte, die den angegebenen Filterkriterien entsprechen. Geeignet für die initiale Datenübertragung oder vollständige Synchronisationen.
- **Delta-Export**: Exportiert nur Produkte, die seit einem bestimmten Zeitpunkt geändert wurden. Nutzen Sie dafür den Filter `updated_after` mit einem ISO-8601-Zeitstempel. Geeignet für regelmässige inkrementelle Aktualisierungen.

```
GET /api/v1/export/products?updated_after=2025-01-15T08:00:00Z
```

## Suche und PQL

### Was ist der Unterschied zwischen der normalen Suche und PQL?

Die **normale Suche** ist ein einfaches Freitextfeld, das Produktnamen und SKUs durchsucht. Sie eignet sich für schnelles Auffinden bekannter Produkte.

**PQL (Publixx Query Language)** ist eine SQL-ähnliche Abfragesprache, mit der Sie beliebig komplexe Filterkriterien über alle Produktattribute formulieren können. PQL unterstützt:

- Vergleichsoperatoren (`=`, `>`, `<`, `LIKE`, `IN`, `BETWEEN`)
- Logische Verknüpfungen (`AND`, `OR`, `NOT`)
- Fuzzy-Suche (`FUZZY` mit konfigurierbarem Schwellwert)
- Phonetische Suche (`SOUNDS_LIKE` mit Kölner Phonetik für deutsche Texte)
- Gewichtete Volltextsuche (`SEARCH_FIELDS` mit Boost-Faktoren)
- Sortierung nach Relevanz (`ORDER BY SCORE`)

Beispiel einer PQL-Abfrage:

```sql
SELECT sku, name, preis FROM products
WHERE kategorie = 'Elektrowerkzeuge'
  AND preis BETWEEN 50 AND 200
  AND FUZZY(name, 'Bohrmaschine', 0.8)
ORDER BY SCORE
```

Ausführliche Dokumentation finden Sie unter [PQL](/de/api/pql).

## System und Administration

### Wie sichere ich das System am besten?

Eine zuverlässige Backup-Strategie umfasst drei Komponenten:

**1. Datenbank-Backup (täglich):**

```bash
mysqldump -u pim_user -p publixx_pim | gzip > /backup/pim_$(date +%Y%m%d).sql.gz
```

**2. Medien-Backup (täglich):**

```bash
rsync -avz /var/www/publixx-pim/storage/app/public/ /backup/media/
```

**3. Konfiguration (nach Änderungen):**

```bash
cp /var/www/publixx-pim/.env /backup/env_$(date +%Y%m%d).env
```

Automatisieren Sie die Backups über Cron-Jobs und bewahren Sie mindestens die Backups der letzten 30 Tage auf. Testen Sie die Wiederherstellung regelmässig.

### Wie kann ich die Performance optimieren?

Folgende Massnahmen verbessern die Systemleistung spürbar:

**Caching aktivieren:**

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

**Datenbank optimieren:**

```bash
php artisan db:optimize  # Indizes und Statistiken aktualisieren
```

**Redis-Cache prüfen:**
Stellen Sie sicher, dass Redis genügend Arbeitsspeicher hat und die `maxmemory`-Einstellung angemessen ist. Überwachen Sie `evicted_keys` -- ein hoher Wert deutet auf zu wenig Speicher hin.

**PHP OPcache aktivieren:**
Stellen Sie sicher, dass OPcache in der Produktionsumgebung aktiviert ist (siehe [Deployment](/de/installation/deployment)).

**Bilder optimieren:**
Laden Sie Bilder in angemessener Auflösung hoch. Das System erzeugt Thumbnails automatisch, aber die Originaldateien beeinflussen den Speicherverbrauch.

**Queue-Verarbeitung:**
Stellen Sie sicher, dass Laravel Horizon mit ausreichend Workern konfiguriert ist, damit Import- und Export-Jobs zeitnah verarbeitet werden.

### Wie füge ich eine neue Sprache hinzu?

Das Publixx PIM unterstützt beliebig viele Inhaltssprachen für Produktdaten. So fügen Sie eine neue Sprache hinzu:

1. **Sprache über den Import anlegen**: Fügen Sie die Sprache im Tab `01_Sprachen` der Excel-Importdatei hinzu (ISO-639-1-Code und Bezeichnung).
2. **Alternativ über die API**:
   ```bash
   curl -X POST /api/v1/languages \
     -H "Authorization: Bearer {token}" \
     -d '{"code": "fr", "name_de": "Französisch", "name_en": "French"}'
   ```
3. **Übersetzbare Attribute pflegen**: Navigieren Sie zu den Produkten und pflegen Sie die Attributwerte in der neuen Sprache. Nur Attribute, die als `translatable` konfiguriert sind, unterstützen mehrsprachige Werte.

::: info Hinweis
Die Oberflächensprache des PIM (Deutsch, Englisch) ist unabhängig von den Inhaltssprachen der Produktdaten. Neue Oberflächensprachen erfordern eine Anpassung der Frontend-Übersetzungsdateien.
:::

### Wo finde ich die Log-Dateien bei Problemen?

Die wichtigsten Log-Dateien für die Fehlerdiagnose:

| Datei | Inhalt |
|---|---|
| `storage/logs/laravel.log` | Anwendungsfehler, Warnungen und Debug-Informationen |
| `storage/logs/horizon.log` | Queue-Worker-Logs (Import, Export) |
| `/var/log/nginx/pim-error.log` | Webserver-Fehler |
| `/var/log/mysql/error.log` | Datenbank-Fehler |

Für die Echtzeit-Überwachung verwenden Sie:

```bash
tail -f storage/logs/laravel.log
```

### Kann ich das System in einer Docker-Umgebung betreiben?

Das Publixx PIM ist primär für den nativen Betrieb auf einem Linux-Server konzipiert und dokumentiert. Ein Docker-Setup ist grundsätzlich möglich, wird aber derzeit nicht offiziell bereitgestellt. Bei einer Containerisierung müssen Sie folgende Dienste als separate Container oder Services berücksichtigen:

- PHP-FPM (mit allen erforderlichen Erweiterungen)
- Nginx
- MySQL 8.0+
- Redis
- Supervisor/Horizon (als separater Worker-Container)

## Weitere Fragen?

Falls Ihre Frage hier nicht beantwortet wurde, konsultieren Sie die detaillierten Abschnitte der Dokumentation:

- [Installation und Deployment](/de/installation/)
- [Import-Dokumentation](/de/import/)
- [Export-Dokumentation](/de/export/)
- [API-Referenz](/de/api/)
- [PQL-Abfragesprache](/de/api/pql)

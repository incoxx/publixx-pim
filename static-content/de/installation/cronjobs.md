# Cronjobs & Geplante Aufgaben

anyPIM nutzt Laravels Task-Scheduler für wiederkehrende Hintergrundprozesse. Ein einzelner System-Cronjob delegiert die gesamte Planung an die Anwendung.

## Einrichtung

Folgenden Cronjob auf dem Server einrichten (z. B. via `crontab -e`):

```bash
* * * * * cd /pfad-zum-projekt && php artisan schedule:run >> /dev/null 2>&1
```

Dieser Eintrag ruft den Scheduler jede Minute auf. Laravel entscheidet dann, welche Befehle fällig sind.

::: warning
Ohne diesen Cronjob werden **keine geplanten Aufgaben ausgeführt** — Export-Jobs laufen nicht, Produktversionen werden nicht veröffentlicht und Kalender-Aktionen werden nicht ausgelöst.
:::

## Geplante Befehle

Folgende Befehle sind in `routes/console.php` registriert:

### Horizon-Snapshots

```
horizon:snapshot — alle 5 Minuten
```

Erfasst Queue-Metriken (verarbeitete Jobs, Wartezeiten, Durchsatz) für das Horizon-Monitoring-Dashboard.

### Queue-Batch-Bereinigung

```
queue:prune-batches --hours=48 — täglich
```

Löscht abgeschlossene Job-Batch-Einträge älter als 48 Stunden aus der Datenbank.

### Geplante Versionsaktivierung

```
versions:activate-scheduled — jede Minute
```

Prüft auf Produktversionen mit `status = scheduled` und einem `publish_at`-Zeitstempel in der Vergangenheit. Gefundene Versionen werden automatisch aktiviert:

- Die Attributwerte des Produkts werden durch den Versions-Snapshot ersetzt
- Der Versionsstatus wechselt von `scheduled` zu `active`
- Die vorherige aktive Version wird archiviert

Dies ermöglicht die **Produkt-Versionierung**, bei der Redakteure Inhaltsänderungen für ein zukünftiges Datum planen können (z. B. "neue Beschreibung am 1. März veröffentlichen").

**Zugehörige UI:** Produkt-Editor → Versionen-Tab → "Planen für"-Datumsauswahl

### Geplante Export-Jobs

```
pim:export-job --run-scheduled — jede Minute
```

Findet aktive Export-Jobs mit Cron-Ausdruck, deren `next_run_at` überschritten ist, und führt sie aus:

1. Produkte anhand der Job-Filter und Suchprofile abfragen
2. Ausgabedatei generieren (CSV, JSON, XML oder Excel)
3. Datei über die konfigurierte Methode ausliefern (SFTP, Webhook, E-Mail oder Download)
4. `last_run_at`, `last_status` aktualisieren und `next_run_at` berechnen
5. Alte Ausführungsprotokolle bereinigen (max. 50 pro Job)

**Cron-Ausdrücke — Beispiele:**

| Ausdruck | Bedeutung |
|----------|-----------|
| `0 0 * * *` | Täglich um Mitternacht |
| `0 */6 * * *` | Alle 6 Stunden |
| `0 2 * * 1` | Jeden Montag um 02:00 |
| `30 8 1 * *` | Am 1. jedes Monats um 08:30 |
| `0 0 * * 1-5` | Werktags um Mitternacht |

**Zugehörige UI:** Export-Jobs → Erstellen/Bearbeiten → Abschnitt "Zeitplan"

### Geplante Aktionen (Kalender)

```
actions:process-scheduled — jede Minute
```

Verarbeitet ausstehende geplante Aktionen aus dem Planungskalender. Jede Aktion hat einen `scheduled_at`-Zeitstempel und einen `action_type`:

| Aktionstyp | Wirkung |
|-----------|---------|
| `activate_product` | Setzt Produktstatus auf `active` |
| `deactivate_product` | Setzt Produktstatus auf `inactive` |
| `price_change` | Aktualisiert Produktpreise aus dem Payload |
| `data_change` | Aktualisiert Produkt-Attributwerte |
| `bulk_update` | Wendet Änderungen auf mehrere Produkte an |
| `export` | Löst einen Export-Job aus |
| `import` | Löst einen Import-Job aus |
| `version_publish` | Veröffentlicht eine bestimmte Produktversion |

Nach Ausführung ändert sich der Status von `pending` zu `completed` (oder `failed` mit Fehlermeldung).

**Zugehörige UI:** Planungskalender → Datum klicken → "Neue Aktion"-Dialog

### TMS-Aufnahme

```
tms:ingest — täglich
```

Sendet neue oder aktualisierte übersetzbare Inhalte an den Translation Memory Service (TMS). Nur aktiv wenn `TMS_ENABLED=true`.

### TMS-Synchronisation

```
tms:sync — täglich um 04:00
```

Holt abgeschlossene Übersetzungen vom TMS in die PIM-Datenbank zurück. Läuft um 04:00, um Stoßzeiten zu vermeiden. Nur aktiv wenn `TMS_ENABLED=true`.

## Kalender-Integration

Alle geplanten Aktivitäten werden im **Planungskalender** (`/calendar`) visualisiert. Vier Ereignisquellen:

1. **Geplante Aktionen** — benutzerdefinierte Zeitereignisse (farbcodiert nach Typ)
2. **Export-Jobs** — wiederkehrende Exporte (berechnet aus Cron-Ausdruck)
3. **Produktversions-Veröffentlichungen** — für die Zukunft geplante Versionen
4. **Projekt-Deadlines** — Start- und Enddaten aktiver Projekte

Der Kalender unterstützt Monats-, Wochen-, Tages- und Zeitleisten-Ansichten. Ausstehende Aktionen können per Drag-and-Drop verschoben werden.

## Überwachung

### Scheduler-Status prüfen

```bash
# Cronjob prüfen
crontab -l | grep schedule:run

# Anstehende Befehle anzeigen
php artisan schedule:list

# Scheduler manuell ausführen (zum Testen)
php artisan schedule:run
```

### Befehle manuell ausführen

```bash
# Alle fälligen Export-Jobs jetzt ausführen
php artisan pim:export-job --run-scheduled

# Alle konfigurierten Export-Jobs auflisten
php artisan pim:export-job --list

# Fällige Produktversionen aktivieren
php artisan versions:activate-scheduled

# Ausstehende Kalender-Aktionen verarbeiten
php artisan actions:process-scheduled
```

### Queue-Worker

Der Scheduler löst Befehle aus, aber Hintergrund-Jobs benötigen **Queue-Worker**. anyPIM verwendet Laravel Horizon:

```bash
# Horizon starten (empfohlen für Produktion)
php artisan horizon

# Oder einfachen Worker starten
php artisan queue:work redis --tries=3 --timeout=300
```

Für Produktion Horizon als Systemdienst via Supervisor konfigurieren:

```ini
[program:horizon]
process_name=%(program_name)s
command=php /pfad-zum-projekt/artisan horizon
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/pfad-zum-projekt/storage/logs/horizon.log
stopwaitsecs=3600
```

## Fehlerbehebung

| Symptom | Ursache | Lösung |
|---------|---------|--------|
| Export-Jobs laufen nie | Kein Cronjob eingerichtet | `schedule:run` in crontab eintragen |
| Versionen werden nicht aktiviert | Queue-Worker läuft nicht | Horizon oder Queue-Worker starten |
| TMS-Übersetzungen fehlen | `TMS_ENABLED=false` | `TMS_ENABLED=true` in `.env` setzen |
| Aktionen bleiben "pending" | Scheduler läuft nicht | Crontab und `schedule:list` prüfen |
| Horizon-Dashboard leer | Keine Snapshots | `horizon:snapshot` im Schedule prüfen |

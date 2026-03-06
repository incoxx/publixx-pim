# anyPIM — Production-Betrieb

Vollstaendige Anleitung fuer Installation, Updates und Betrieb auf einem Production-Server.

---

## Uebersicht

anyPIM liefert zwei Scripts fuer den kompletten Server-Lifecycle:

| Script | Zweck | Ausfuehrung |
|---|---|---|
| `setup.sh` | Erstinstallation (einmalig) | `sudo bash setup.sh` |
| `update.sh` | Updates einspielen (wiederholt) | `sudo bash update.sh` |

Beide Scripts laufen interaktiv, koennen aber auch automatisiert werden (`--force`).

---

## Architektur

```
┌─────────────────────────────────────────────────────────┐
│  Ubuntu 24.04 LTS Server                                │
│                                                         │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐              │
│  │  Apache   │  │  MySQL   │  │  Redis   │              │
│  │  :80/:443 │  │  :3306   │  │  :6379   │              │
│  └─────┬─────┘  └────┬─────┘  └────┬─────┘              │
│        │              │             │                    │
│  ┌─────┴──────────────┴─────────────┴──────┐             │
│  │         Laravel (PHP 8.4)               │             │
│  │         /var/www/publixx-pim            │             │
│  └─────────────────┬───────────────────────┘             │
│                    │                                     │
│  ┌─────────────────┴───────────────────────┐             │
│  │  Supervisor                             │             │
│  │  └── Horizon (Queue Worker)             │             │
│  │      ├── indexing   (Search-Index)      │             │
│  │      ├── cache      (Cache-Invalidierung)│            │
│  │      ├── warmup     (Cache-Warmup)      │             │
│  │      └── default    (Import/Export/etc.) │             │
│  └─────────────────────────────────────────┘             │
│                                                         │
│  ┌─────────────────────────────────────────┐             │
│  │  Cron (www-data)                        │             │
│  │  * * * * * php artisan schedule:run     │             │
│  └─────────────────────────────────────────┘             │
└─────────────────────────────────────────────────────────┘
```

---

## 1. Erstinstallation mit setup.sh

### Voraussetzungen

- Frisches **Ubuntu 24.04 LTS** (oder 22.04)
- **Root-Zugang** (sudo)
- Mindestens 2 GB RAM, 10 GB Speicher
- Empfohlen: 4 vCPU, 8 GB RAM, 160 GB SSD

### Ausfuehrung

```bash
# Repository klonen
git clone <repository-url> /tmp/publixx-pim
cd /tmp/publixx-pim

# Setup starten
sudo bash setup.sh
```

### Interaktive Abfragen

| Abfrage | Beispiel | Standard |
|---|---|---|
| Domain / IP | `pim.example.com` | — (Pflicht) |
| Apache-Port | `80` | 80 |
| SSL (Let's Encrypt) | `j/N` | Nein |
| Web-Pfad | `/web` oder leer | `/` (Root) |
| MySQL Datenbankname | `publixx_pim` | `publixx_pim` |
| MySQL Benutzername | `pim` | `pim` |
| MySQL Passwort | `*****` | — (Pflicht) |
| Installationspfad | `/var/www/publixx-pim` | `/var/www/publixx-pim` |
| Extra-Admin anlegen | `j/N` | Nein |

### Was setup.sh installiert und konfiguriert

```
Schritt  1/10  System aktualisieren (apt update/upgrade)
Schritt  2/10  PHP 8.4 + Extensions (mysql, redis, mbstring, xml, zip, gd, bcmath, curl, intl)
Schritt  3/10  Apache + Module (rewrite, headers, ssl, alias)
Schritt  4/10  MySQL 8 (Datenbank + Benutzer anlegen)
Schritt  5/10  Redis (maxmemory 512mb, allkeys-lru)
Schritt  6/10  Node.js 20 LTS
Schritt  7/10  Composer 2
Schritt  8/10  Laravel einrichten (.env, Composer Install, Migrationen, Seeder, Storage-Link)
Schritt  9/10  Frontend bauen (npm ci + npm run build → public/)
Schritt 10/10  Apache VHost, Supervisor/Horizon, Cron, Berechtigungen, Firewall
```

### Nach der Installation laufen automatisch

| Dienst | Verwaltung | Autostart |
|---|---|---|
| **Apache** | `systemctl` | Ja (systemd) |
| **MySQL** | `systemctl` | Ja (systemd) |
| **Redis** | `systemctl` | Ja (systemd) |
| **Supervisor** | `systemctl` | Ja (systemd) |
| **Horizon** (Queue Worker) | `supervisorctl` | Ja (`autostart=true`) |
| **Laravel Scheduler** | Cron (`www-data`) | Ja (`* * * * *`) |

---

## 2. Updates mit update.sh

### Ausfuehrung

```bash
cd /var/www/publixx-pim
sudo bash update.sh
```

### Optionen

```bash
sudo bash update.sh [optionen]
```

| Option | Beschreibung |
|---|---|
| `--branch=NAME` | Anderen Branch als `main` verwenden |
| `--skip-frontend` | Frontend-Build ueberspringen (nur Backend-Aenderungen) |
| `--skip-composer` | Composer Install ueberspringen |
| `--seed` | Nach Migrationen auch Seeders ausfuehren |
| `--force` | Keine Bestaetigung, direkt ausfuehren |

### Beispiele

```bash
# Standard-Update
sudo bash update.sh

# Schnelles Backend-Update
sudo bash update.sh --skip-frontend

# Feature-Branch testen
sudo bash update.sh --branch=feature/new-import

# Vollautomatisch (CI/CD)
sudo bash update.sh --force

# Mit neuen Testdaten
sudo bash update.sh --seed
```

### Ablauf (8 Schritte)

```
Schritt 1/8  Wartungsmodus aktivieren (artisan down)
Schritt 2/8  Git Pull vom Branch (zeigt Aenderungen an)
Schritt 3/8  Composer Install (--no-dev, optimized autoloader)
Schritt 4/8  Datenbank-Migrationen (artisan migrate --force)
Schritt 5/8  Frontend-Build (npm ci + build, Subdirectory-aware)
Schritt 6/8  Laravel-Caches neu erstellen (config, route, view, event)
Schritt 7/8  Berechtigungen + Horizon/Queue Worker + Apache neu starten
Schritt 8/8  Wartungsmodus deaktivieren + Healthcheck
```

### Fehler-Handling

- Bei jedem Fehler wird der **Wartungsmodus automatisch deaktiviert** (trap)
- Am Ende laeuft ein **Healthcheck** gegen `/api/v1/health`
- Falls Supervisor/Horizon nicht verfuegbar ist, startet das Script einen **Fallback Queue Worker** direkt

---

## 3. Queue-System (Horizon)

### Queues und ihre Aufgaben

| Queue | Zweck | Prioritaet | Max Workers |
|---|---|---|---|
| `indexing` | Search-Index aktualisieren (produktkritisch) | Hoch | 4 |
| `cache` | Cache-Invalidierung nach Aenderungen | Mittel | 2 |
| `default` | Import, Export, allgemeine Jobs | Normal | 4 |
| `warmup` | Cache-Warmup nach Imports | Niedrig | 2 |

### Supervisor-Konfiguration

Wird automatisch von `setup.sh` erstellt unter `/etc/supervisor/conf.d/horizon.conf`:

```ini
[program:horizon]
process_name=%(program_name)s
command=php /var/www/publixx-pim/artisan horizon
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/publixx-pim/storage/logs/horizon.log
stopwaitsecs=3600
```

### Horizon-Konfiguration

Die Queue-Worker-Einstellungen fuer Production stehen in `config/horizon.php`:

```php
'environments' => [
    'production' => [
        'supervisor-indexing' => [
            'maxProcesses' => 4,    // Bis zu 4 Worker fuer indexing
            'minProcesses' => 1,    // Mindestens 1 Worker
        ],
        'supervisor-cache' => [
            'maxProcesses' => 2,
        ],
        'supervisor-warmup' => [
            'maxProcesses' => 2,
        ],
        'supervisor-default' => [
            'maxProcesses' => 4,
        ],
    ],
],
```

### Horizon verwalten

```bash
# Status pruefen
sudo supervisorctl status horizon

# Neu starten (nach Code-Aenderungen)
sudo supervisorctl restart horizon

# Stoppen
sudo supervisorctl stop horizon

# Horizon-Dashboard im Browser
# https://example.com/horizon
# (Erfordert Login als authentifizierter Benutzer)

# Queue-Status per CLI
php artisan horizon:status

# Alle laufenden Jobs anzeigen
php artisan queue:monitor indexing,default,cache,warmup
```

### Fallback ohne Supervisor

Falls Supervisor nicht installiert ist (z.B. Entwicklungsumgebung), startet `update.sh` automatisch einen einfachen Queue Worker:

```bash
# Wird automatisch gestartet — oder manuell:
nohup php artisan queue:work --queue=indexing,default --sleep=3 --tries=3 \
    >> storage/logs/queue-worker.log 2>&1 &
```

> **Hinweis:** Der nohup-Worker ueberlebt keinen Server-Neustart. Fuer Production immer Supervisor verwenden.

---

## 4. Laravel Scheduler (Cron)

Der Scheduler fuehrt wiederkehrende Aufgaben aus (Cache-Bereinigung, geplante Exports etc.).

Wird automatisch von `setup.sh` eingerichtet:

```
* * * * * cd /var/www/publixx-pim && php artisan schedule:run >> /dev/null 2>&1
```

### Pruefen

```bash
# Cron-Jobs von www-data anzeigen
sudo crontab -u www-data -l

# Scheduler manuell ausfuehren (zum Testen)
cd /var/www/publixx-pim
php artisan schedule:run
```

---

## 5. Monitoring und Logs

### Log-Dateien

| Datei | Inhalt |
|---|---|
| `storage/logs/laravel.log` | Anwendungs-Logs (Fehler, Warnungen) |
| `storage/logs/horizon.log` | Horizon/Queue-Worker-Logs |
| `storage/logs/queue-worker.log` | Fallback-Worker-Logs (nur ohne Supervisor) |
| `/var/log/apache2/publixx-pim-error.log` | Apache-Fehler |
| `/var/log/apache2/publixx-pim-access.log` | Apache-Zugriffe |

### Healthcheck

```bash
# Per Script (prueft alle Dienste lokal)
bash healthcheck.sh

# Per URL
curl -s https://example.com/api/v1/health

# Dienste einzeln pruefen
sudo systemctl status apache2
sudo systemctl status mysql
sudo systemctl status redis-server
sudo systemctl status supervisor
sudo supervisorctl status horizon
```

### Horizon-Dashboard

Im Browser unter `https://example.com/horizon` erreichbar (Login erforderlich).

Zeigt an:
- Aktive/wartende/fehlgeschlagene Jobs
- Queue-Durchsatz und Wartezeiten
- Worker-Auslastung
- Job-Details und Fehlermeldungen

---

## 6. Subdirectory-Modus

PIM kann unter einem Unterverzeichnis laufen (z.B. `https://example.com/web`):

```bash
# setup.sh fragt: Web-Pfad (z.B. /web oder leer fuer Root)
# In .env:
APP_URL=https://example.com/web
```

### Automatische Anpassungen

- **Apache:** Alias-Konfiguration statt eigener VHost (`/etc/apache2/conf-available/publixx-pim.conf`)
- **Frontend-Build:** `VITE_BASE_PATH=/web/` und `VITE_API_BASE_URL=/web/api/v1` werden automatisch gesetzt
- **Session-Cookie:** `SESSION_COOKIE_PATH=/web`
- **update.sh:** Erkennt den Modus automatisch aus der `.env`

---

## 7. SSL / HTTPS

### Automatisch (setup.sh)

```bash
# setup.sh fragt:
# SSL mit Let's Encrypt einrichten? [j/N]
# E-Mail fuer Let's Encrypt: admin@example.com
```

### Manuell nachtraeglich

```bash
# Certbot installieren
sudo apt install certbot python3-certbot-apache

# Zertifikat einrichten
sudo certbot --apache -d pim.example.com

# Auto-Renewal pruefen
sudo certbot renew --dry-run
```

---

## 8. Fehlerbehebung

### Berechtigungsfehler

```bash
sudo chown -R www-data:www-data /var/www/publixx-pim
sudo chmod -R 775 /var/www/publixx-pim/storage
sudo chmod -R 775 /var/www/publixx-pim/bootstrap/cache
```

### Horizon laeuft nicht

```bash
# Status pruefen
sudo supervisorctl status horizon

# Falls FATAL:
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start horizon

# Logs pruefen
tail -50 /var/www/publixx-pim/storage/logs/horizon.log
```

### Search-Index leer (Produktnamen fehlen)

Der Search-Index wird asynchron ueber die `indexing`-Queue befuellt. Wenn Produktnamen im Katalog fehlen:

```bash
# 1. Pruefen ob Horizon/Queue Worker laeuft
sudo supervisorctl status horizon

# 2. Queue-Laenge pruefen
php artisan queue:monitor indexing

# 3. Search-Index manuell fuer alle Produkte neu aufbauen
php artisan tinker --execute="
    \App\Models\Product::where('status', 'active')
        ->pluck('id')
        ->each(fn(\$id) => \App\Jobs\UpdateSearchIndex::dispatch(\$id));
    echo 'Jobs dispatched.';
"
```

### Apache-Konfiguration fehlerhaft

```bash
sudo apache2ctl configtest
sudo systemctl status apache2
tail -20 /var/log/apache2/publixx-pim-error.log
```

### Wartungsmodus haengt

```bash
cd /var/www/publixx-pim
php artisan up

# Falls das nicht funktioniert:
rm storage/framework/down
```

### Redis-Verbindung fehlgeschlagen

```bash
# Status pruefen
sudo systemctl status redis-server

# Verbindung testen
redis-cli ping   # Erwartet: PONG

# Speicher pruefen
redis-cli INFO memory
```

---

## 9. Backup

### Datenbank

```bash
# Backup erstellen
mysqldump -u pim -p publixx_pim > backup_$(date +%Y%m%d_%H%M%S).sql

# Backup zurueckspielen
mysql -u pim -p publixx_pim < backup_20250305_120000.sql
```

### Medien-Dateien

```bash
# Hochgeladene Dateien sichern
tar czf media_backup_$(date +%Y%m%d).tar.gz storage/app/public/media/
```

### Vollstaendiges Backup

```bash
# Datenbank + Medien + .env
mysqldump -u pim -p publixx_pim > /tmp/db_backup.sql
tar czf /tmp/pim_backup_$(date +%Y%m%d).tar.gz \
    /tmp/db_backup.sql \
    /var/www/publixx-pim/.env \
    /var/www/publixx-pim/storage/app/public/media/
rm /tmp/db_backup.sql
```

---

## 10. Zusammenfassung: Typischer Betrieb

### Erstinstallation (einmalig)

```bash
git clone <repo> /tmp/publixx-pim
cd /tmp/publixx-pim
sudo bash setup.sh
# → Interaktive Abfragen beantworten
# → Fertig: PIM laeuft mit allen Diensten
```

### Regelmaessige Updates

```bash
cd /var/www/publixx-pim
sudo bash update.sh
# → Zeigt Aenderungen, fragt Bestaetigung
# → Wartungsmodus → Update → Neustart → Healthcheck
```

### Schnelles Backend-Update

```bash
sudo bash update.sh --skip-frontend
```

### Automatisiertes Update (CI/CD)

```bash
sudo bash update.sh --force
```

### Status pruefen

```bash
sudo supervisorctl status horizon    # Queue Worker
curl -s https://example.com/api/v1/health  # Healthcheck
tail -20 storage/logs/laravel.log    # Anwendungs-Logs
```

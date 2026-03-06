---
title: Schnellstart
---

# Schnellstart

anyPIM bietet zwei Wege zur Installation: das **automatische Setup-Script** (empfohlen) fuer Server-Installationen und die **manuelle Installation** fuer Entwicklungsumgebungen.

## Automatische Installation mit setup.sh (empfohlen)

Das mitgelieferte `setup.sh` installiert **alles automatisch** auf einem frischen Ubuntu 24.04 LTS Server: PHP 8.4, MySQL 8, Redis, Apache, Node.js, Composer, Frontend-Build, Supervisor und SSL.

### Voraussetzungen

- Frischer **Ubuntu 24.04 LTS** Server mit Root-Zugang
- Eine Domain oder IP-Adresse

### Installation

```bash
git clone https://github.com/incoxx/publixx-pim.git /var/www/publixx-pim
cd /var/www/publixx-pim
sudo bash setup.sh
```

Das Script fuehrt Sie interaktiv durch die Konfiguration:

| Abfrage | Beschreibung | Standard |
|---|---|---|
| **Domain/IP** | Server-Hostname oder IP-Adresse | -- (Pflicht) |
| **Apache-Port** | Webserver-Port | `80` |
| **SSL mit Let's Encrypt** | Automatisches HTTPS-Zertifikat | Nein |
| **Web-Pfad** | Subdirectory-Deployment (z.B. `/web`, `/pim`) | leer (Root) |
| **MySQL-Datenbank** | Datenbankname | `publixx_pim` |
| **MySQL-Benutzer** | Datenbankbenutzer | `pim` |
| **MySQL-Passwort** | Datenbankpasswort | -- (Pflicht) |
| **Installationspfad** | Verzeichnis der Anwendung | `/var/www/publixx-pim` |
| **Admin-Benutzer** | Optionaler zusaetzlicher Admin-Account | Optional |

### Was setup.sh installiert

Das Script durchlaeuft **10 Schritte** in ca. 5--10 Minuten:

1. **System-Update** -- `apt-get upgrade`
2. **PHP 8.4** -- mit allen Erweiterungen (mysql, redis, mbstring, xml, zip, gd, bcmath, curl, intl, opcache)
3. **Apache 2.4** -- mit mod_rewrite, mod_headers, mod_ssl, mod_php
4. **MySQL 8.0** -- Datenbank und Benutzer werden automatisch angelegt
5. **Redis** -- mit 512 MB Speicherlimit und allkeys-lru Policy
6. **Node.js 20 LTS** -- fuer den Frontend-Build
7. **Composer** -- PHP-Paketmanager
8. **Anwendung** -- .env, Composer-Install, Migrationen, Demo-Daten, Storage-Link
9. **Frontend-Build** -- npm ci, Vite-Build, Kopie nach public/
10. **Webserver & Services** -- Apache-VHost, Supervisor fuer Horizon, Cron, Firewall

### Nach der Installation

```bash
# Status aller Services pruefen
bash healthcheck.sh

# Nur HTTP-Endpoint testen
bash healthcheck.sh --url-only

# JSON-Ausgabe (fuer Monitoring)
bash healthcheck.sh --json
```

::: info Standard-Zugangsdaten
Nach der Installation stehen folgende Demo-Accounts zur Verfuegung:
- `admin@publixx.com` / `password`
- `admin@example.com` / `password`

Falls Sie im Setup einen eigenen Admin-Account angelegt haben, verwenden Sie dessen Zugangsdaten. Aendern Sie die Passwoerter nach dem ersten Login.
:::

### Deployment-Modi

Das Setup-Script unterstuetzt zwei Modi:

**Root-Modus** (Standard): Das PIM ist die einzige Anwendung auf der Domain. Apache VHost auf Port 80/443.

**Subdirectory-Modus**: Das PIM laeuft unter einem Pfad (z.B. `https://example.com/web`). Apache-Alias wird in den bestehenden VHost eingebunden. Ideal wenn bereits andere Anwendungen auf der Domain laufen.

---

## Updates mit update.sh

Fuer nachfolgende Updates steht das Script `update.sh` zur Verfuegung. Es aktualisiert die Anwendung von GitHub, fuehrt Migrationen durch, baut das Frontend neu und startet die Services.

```bash
sudo bash update.sh
```

### Optionen

| Option | Beschreibung |
|---|---|
| `--branch=NAME` | Anderen Branch verwenden (Standard: main) |
| `--skip-frontend` | Frontend-Build ueberspringen (schnelleres Update) |
| `--skip-composer` | Composer-Install ueberspringen |
| `--seed` | Datenbank-Seeder nach Migrationen ausfuehren |
| `--force` | Bestaetigung ueberspringen |

### Update-Ablauf

Das Script durchlaeuft folgende Schritte:

1. **Wartungsmodus aktivieren** -- Laravel `down` mit 60-Sekunden-Retry
2. **Git Pull** -- Neueste Aenderungen vom Branch holen
3. **Composer Install** -- PHP-Abhaengigkeiten aktualisieren
4. **Datenbank-Migrationen** -- Ausstehende Schema-Aenderungen ausfuehren
5. **Frontend-Build** -- npm ci + Vite-Build + Kopie nach public/
6. **Caches erneuern** -- Config, Route, View und Event Cache
7. **Services neustarten** -- Berechtigungen, Horizon-Neustart, Apache-Reload
8. **Healthcheck** -- API-Endpunkt testen + Wartungsmodus deaktivieren

::: tip Schnelles Update
Fuer reine Backend-Aenderungen (ohne Frontend-Aenderungen) nutzen Sie:
```bash
sudo bash update.sh --skip-frontend
```
Das reduziert die Update-Zeit erheblich.
:::

### Fehlerbehandlung

Falls waehrend des Updates ein Fehler auftritt, wird der Wartungsmodus automatisch deaktiviert, damit die Anwendung erreichbar bleibt.

---

## Healthcheck mit healthcheck.sh

Das Healthcheck-Script prueft alle kritischen Services und Abhaengigkeiten:

```bash
bash healthcheck.sh
```

### Pruefungen

| Pruefpunkt | Beschreibung |
|---|---|
| **Laravel** | Artisan-Datei vorhanden |
| **.env** | Konfigurationsdatei vorhanden |
| **PHP** | Version und Status |
| **Datenbank** | MySQL-Verbindung (SELECT 1) |
| **Redis** | Cache-Verbindung (PING) |
| **Apache** | Service-Status |
| **Supervisor/Horizon** | Queue-Worker laeuft |
| **Speicherplatz** | Warnung bei < 1 GB, kritisch bei < 200 MB |
| **Storage** | Schreibrechte auf storage/ |
| **HTTP-Endpoint** | API-Healthcheck (`/api/v1/health`) |

### Optionen

| Option | Beschreibung |
|---|---|
| `--url-only` | Nur HTTP-Endpoint pruefen |
| `--json` | JSON-Ausgabe (fuer Monitoring-Systeme) |
| `--quiet` | Nur Exit-Code (0 = gesund, 1 = Fehler) |

---

## Manuelle Installation (Entwicklung)

Fuer die lokale Entwicklungsumgebung koennen Sie die Installation auch manuell durchfuehren. Stellen Sie sicher, dass alle in den [Voraussetzungen](./voraussetzungen) beschriebenen Abhaengigkeiten installiert sind.

### 1. Repository klonen

```bash
git clone git@github.com:incoxx/publixx-pim.git
cd publixx-pim
```

### 2. PHP-Abhaengigkeiten installieren

```bash
composer install
```

### 3. Frontend-Abhaengigkeiten installieren

```bash
cd pim-frontend
npm install
cd ..
```

### 4. Umgebungskonfiguration

```bash
cp .env.example .env
php artisan key:generate
```

Oeffnen Sie die `.env`-Datei und konfigurieren Sie mindestens folgende Werte:

```dotenv
# Anwendung
APP_NAME="anyPIM"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:5173

# Datenbank
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=publixx_pim
DB_USERNAME=root
DB_PASSWORD=ihr_passwort

# Redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

# Sanctum
SANCTUM_STATEFUL_DOMAINS=localhost:5173,localhost:8000
```

Erstellen Sie die Datenbank:

```bash
mysql -u root -p -e "CREATE DATABASE publixx_pim CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### 5. Datenbank einrichten

```bash
php artisan migrate
php artisan db:seed
```

### 6. Storage-Link erstellen

```bash
php artisan storage:link
```

### 7. Entwicklungsserver starten

Starten Sie Backend und Frontend in zwei separaten Terminals:

**Terminal 1 -- Laravel Backend:**

```bash
php artisan serve
```

**Terminal 2 -- Vite Frontend (Entwicklungsmodus):**

```bash
cd pim-frontend
npm run dev
```

**Terminal 3 -- Queue Worker (optional):**

```bash
php artisan horizon
```

Das PIM ist nun unter `http://localhost:5173` erreichbar.

### 8. Installation ueberpruefen

| Pruefpunkt | Erwartetes Ergebnis |
|---|---|
| Login-Seite wird angezeigt | Frontend und API-Verbindung funktionieren |
| Login mit Admin-Zugangsdaten | Authentifizierung und Datenbank funktionieren |
| Dashboard wird geladen | SPA-Routing und API-Endpunkte funktionieren |
| Horizon-Dashboard erreichbar | Redis und Queue-System funktionieren |

## Haeufige Probleme

### CORS-Fehler im Browser

Stellen Sie sicher, dass `FRONTEND_URL` und `SANCTUM_STATEFUL_DOMAINS` in der `.env`-Datei korrekt auf die Frontend-URL gesetzt sind.

### Datenbankverbindung schlaegt fehl

```bash
php artisan db:monitor
```

### Redis-Verbindung schlaegt fehl

```bash
redis-cli ping
# Erwartete Antwort: PONG
```

### Berechtigungsprobleme bei Storage

```bash
chmod -R 775 storage bootstrap/cache
chown -R $USER:www-data storage bootstrap/cache
```

## Naechster Schritt

Fuer den produktiven Betrieb lesen Sie die Anleitung zum [Deployment](./deployment).

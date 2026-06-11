# anyPIM — Docker-Klon einer laufenden Instanz

## Konzept

`docker_clone.sh` klont eine **bereits laufende anyPIM-Installation** (Apache + PHP + MySQL + Redis + Typesense) vollständig in einen eigenständigen Docker-Compose-Stack — inklusive aller Daten und Testdaten.

**Ziel:** Eine laufende Produktiv- oder Staging-Instanz reproduzierbar einfrieren und auf einem anderen Rechner (z.B. Windows-Entwickler-Laptop) oder in CI weiterlaufen lassen.

---

## Voraussetzungen

| Software | Auf dem Quell-Server | Auf dem Ziel-Rechner |
|----------|---------------------|----------------------|
| Docker + Compose v2 | ✓ | ✓ |
| `mysqldump` | ✓ | — |
| `rsync` | ✓ | — |
| Docker Desktop | — | ✓ (Windows/Mac) |

---

## Verwendung

```bash
# Standard (Default-Pfad /var/www/anypim, Port 8080)
sudo bash docker_clone.sh

# Mit eigenen Pfaden
sudo bash docker_clone.sh \
    --app-dir /var/www/anypim \
    --output  /opt/anypim-docker \
    --port    8080

# + Transfer-Archiv für anderen Rechner erzeugen
sudo bash docker_clone.sh --pack
```

### Optionen

| Option | Default | Beschreibung |
|--------|---------|-------------|
| `--app-dir` | `/var/www/anypim` | Pfad zur laufenden anyPIM-Installation |
| `--output` | `/opt/anypim-docker` | Zielverzeichnis für den Klon |
| `--port` | `8080` | HTTP-Port des app-Containers auf dem Host |
| `--pack` | — | Erzeugt `<output>.tar.gz` zum Transfer |

---

## Was das Skript macht (10 Schritte)

| Schritt | Was passiert |
|---------|-------------|
| **1. Teardown** | Vorhandener Klon wird abgeräumt (`docker compose down -v`) — Skript ist idempotent |
| **2. DB-Dump** | `mysqldump --single-transaction --no-tablespaces` — kein Lock, kein PROCESS-Privileg nötig |
| **3. Storage** | `storage/app/` (Medien, generierte Dateien) wird nach `storage_backup/` kopiert |
| **4. App-Code** | `rsync` ohne `.git`, `node_modules`, Logs, Caches — Frontend ist bereits gebaut, kein npm nötig |
| **5. .env anpassen** | `DB_HOST=db`, `REDIS_HOST=redis`, `TYPESENSE_HOST=typesense` — Service-Namen statt localhost |
| **6. Configs generieren** | Apache-VHost, PHP-INI, Supervisor-Conf (Horizon + Scheduler) |
| **7. Dockerfile** | `php:8.4-apache-bookworm` + alle Extensions + Redis-Extension |
| **8. docker-compose.yml** | 4 Services: app, db (mysql:8.0), redis (redis:7), typesense (27.1) |
| **9. start.sh / start.ps1** | Ein-Klick-Starter für Linux/macOS und Windows |
| **10. --pack** | Optional: `tar czf` des Output-Ordners für Transfer |

---

## Generierter Stack

```
anypim-docker/
├── Dockerfile                    ← php:8.4-apache + Extensions
├── docker-compose.yml            ← 4 Services + Volumes + Healthchecks
├── .env                          ← Secrets für docker-compose (DB, Redis, Typesense)
├── app/                          ← Anwendungscode (inkl. vendor/ + public/)
│   └── .env                      ← Laravel-.env (Bind-Mount in Container)
├── storage_backup/               ← Medien + generierte Dateien
├── docker/
│   ├── init/01_dump.sql          ← DB-Dump (Auto-Import beim ersten Start)
│   ├── apache/000-default.conf
│   ├── php/anypim.ini
│   ├── supervisor/anypim.conf    ← Apache + Horizon + Scheduler
│   └── entrypoint.sh             ← config:clear vor supervisord
├── start.sh                      ← Linux / macOS / Git Bash / WSL
├── start.ps1                     ← Windows (Docker Desktop + PowerShell)
└── README.md
```

### Services

| Service | Image | Host-Port |
|---------|-------|-----------|
| app | `php:8.4-apache` (Build) | `8080 → 80` |
| db | `mysql:8.0` | `127.0.0.1:3307 → 3306` |
| redis | `redis:7-alpine` | intern |
| typesense | `typesense/typesense:27.1` | intern (kein Host-Port → kein Konflikt mit nativer Instanz) |

---

## Starten

### Linux / macOS / Git Bash / WSL

```bash
cd /opt/anypim-docker
bash start.sh
```

### Windows (Docker Desktop + PowerShell)

```powershell
Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass
cd C:\anypim-docker
.\start.ps1
```

### Was start.sh / start.ps1 tut

1. `docker compose build` — Image bauen
2. `docker compose up -d` — alle 4 Container starten (DB-Import läuft automatisch)
3. Warten bis app-Container `healthy` ist (max. 5 Min)
4. `storage_backup/` ins Volume kopieren + `storage:link`
5. `config:clear` + `optimize` + `typesense:setup` + `typesense:reindex`

---

## Transfer auf anderen Rechner

```bash
# Auf dem Quell-Server (alles in einem Schritt):
sudo bash docker_clone.sh --pack
# → /opt/anypim-docker.tar.gz

# Herunterladen z.B. per SCP:
scp root@server:/opt/anypim-docker.tar.gz .

# Entpacken und starten:
tar xzf anypim-docker.tar.gz
cd anypim-docker
bash start.sh          # Linux/macOS
.\start.ps1            # Windows
```

---

## Als Cron-Job (täglich aktueller Klon)

```bash
# /etc/cron.d/anypim-docker-clone
0 2 * * * root bash /var/www/anypim/docker_clone.sh --pack >> /var/log/anypim-clone.log 2>&1
```

Jeden Tag um 02:00 Uhr: frischer Klon inkl. aktueller DB + Medien, fertig gepackt als `.tar.gz`.

---

## Bekannte Stolpersteine & Lösungen

### `mysqldump: Access denied; PROCESS privilege`
**Ursache:** MySQL 8 versucht Tablespaces zu dumpen, App-User hat kein `PROCESS`-Recht.  
**Lösung:** `--no-tablespaces` — bereits im Skript eingebaut. Der Dump ist vollständig.

### `address already in use` (Typesense Port 8108)
**Ursache:** Native Typesense-Installation belegt Port 8108 auf dem Host.  
**Lösung:** Typesense-Container hat kein Host-Port-Mapping — App erreicht ihn intern über `typesense:8108`.

### App-Container wird nicht `healthy`
**Ursachen und Prüfung:**
```bash
docker compose logs --tail=50 app
docker compose exec app curl -v http://localhost/api/v1/health
```
Häufigste Ursache: Config-Cache mit alten Werten (z.B. `DB_HOST=127.0.0.1`).  
**Lösung:** `docker compose exec app php artisan config:clear`

### `.env`-Änderungen erreichen den Container nicht
**Ursache:** `app/.env` ist als Bind-Mount eingebunden (`:ro`) — nach Änderungen reicht `config:clear`, kein Rebuild nötig.
```bash
# Linux
docker compose exec app php artisan config:clear

# Windows (wenn .env auf Platte geändert)
Get-Content .\app\.env | docker compose exec -T app sh -c "cat > /var/www/html/.env"
docker compose exec app php artisan config:clear
```

### `REDIS_PASSWORD=null` → Redis Connection refused
**Ursache:** Original-`.env` hat `REDIS_PASSWORD=null` (Laravel-Konvention für "kein Passwort"), aber Redis-Container läuft mit echtem Passwort.  
**Lösung:** Im Skript wird `REDIS_PASSWORD` aus der laufenden Instanz in `app/.env` synchronisiert.

### PowerShell: `Zeichenfolge hat kein Abschlusszeichen`
**Ursache:** `start.ps1` mit UTF-8-Sonderzeichen (Umlaute, Em-Dash) — PowerShell liest ohne BOM als Windows-1252.  
**Lösung:** `start.ps1` ist reines ASCII — neu generieren mit aktuellem Skript.

### PowerShell: Eingabe-Umleitung mit `<`
**Ursache:** PowerShell unterstützt `< datei` nicht.  
**Lösung:**
```powershell
Get-Content .\app\.env | docker compose exec -T app sh -c "cat > /var/www/html/.env"
```

---

## Zurücksetzen / Neu generieren

```bash
# Auf dem Ziel-Rechner: alles löschen (Container + Volumes + Verzeichnis)
cd /opt/anypim-docker
docker compose down -v
cd /
rm -rf /opt/anypim-docker

# Neu generieren (Skript ist idempotent — macht das auch automatisch)
sudo bash /var/www/anypim/docker_clone.sh --pack
```

> **Wichtig:** `docker compose down -v` immer **aus dem Verzeichnis mit der `docker-compose.yml`** ausführen — *bevor* das Verzeichnis gelöscht wird. Sonst bleiben Volumes verwaist zurück.

# anyPIM — Update-Anleitung

## Schnell-Update

```bash
cd /var/www/publixx-pim
sudo bash update.sh
```

Das Script macht alles automatisch: Wartungsmodus, Git Pull, Composer, Migrationen, Frontend-Build, Caches, Service-Neustart, Healthcheck.

## Optionen

```bash
sudo bash update.sh [optionen]
```

| Option | Beschreibung |
|---|---|
| `--branch=NAME` | Anderen Branch als `main` verwenden |
| `--skip-frontend` | Frontend-Build ueberspringen (spart Zeit wenn nur Backend-Aenderungen) |
| `--skip-composer` | Composer Install ueberspringen |
| `--seed` | Nach Migrationen auch Seeders ausfuehren |
| `--force` | Keine Bestaetigung vor dem Update |

### Beispiele

```bash
# Standard-Update von main
sudo bash update.sh

# Schnelles Backend-Update (kein Frontend-Build)
sudo bash update.sh --skip-frontend

# Feature-Branch testen
sudo bash update.sh --branch=feature/new-import

# Vollautomatisch (z.B. in Cron/CI)
sudo bash update.sh --force

# Nach Schema-Aenderungen mit neuen Testdaten
sudo bash update.sh --seed
```

## Was update.sh macht (8 Schritte)

| Schritt | Beschreibung |
|---|---|
| 1/8 | Wartungsmodus aktivieren (`artisan down`) |
| 2/8 | Git Pull vom angegebenen Branch |
| 3/8 | Composer Install (--no-dev, optimized) |
| 4/8 | Datenbank-Migrationen |
| 5/8 | Frontend-Build (Subdirectory-aware, alte Assets entfernen) |
| 6/8 | Laravel-Caches neu erstellen (config, route, view, event) |
| 7/8 | Dateiberechtigungen + Horizon + Apache neu starten |
| 8/8 | Wartungsmodus deaktivieren + Healthcheck |

## Subdirectory-Modus

Das Update-Script erkennt automatisch den Subdirectory-Modus aus der `.env`:

```
APP_URL=https://smartentities.de/web
```

Beim Frontend-Build werden `VITE_BASE_PATH` und `VITE_API_BASE_URL` automatisch gesetzt.

## Fehler-Handling

- Bei jedem Fehler wird der Wartungsmodus automatisch deaktiviert
- Am Ende laeuft ein Healthcheck gegen `/api/v1/health`
- Die Aenderungen seit dem letzten Stand werden angezeigt

## Manuelles Update (ohne Script)

Falls das Script nicht verwendet werden soll:

```bash
cd /var/www/publixx-pim

# 1. Wartungsmodus
php artisan down --retry=60

# 2. Code holen
git pull origin main

# 3. PHP-Abhaengigkeiten
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader

# 4. Migrationen
php artisan migrate --force

# 5. Frontend bauen
cd pim-frontend
# Bei Subdirectory: export VITE_BASE_PATH="/web/"
npm ci --production=false
npm run build
cd ..
rm -rf public/pim-assets
cp pim-frontend/dist/index.html public/spa.html
cp -r pim-frontend/dist/pim-assets public/

# 6. Caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 7. Berechtigungen
chown -R www-data:www-data .
chmod -R 775 storage bootstrap/cache

# 8. Services
supervisorctl restart horizon
systemctl reload apache2
php artisan up
```

## Healthcheck nach Update

```bash
# Automatisch (im Script integriert)
# Oder manuell:
bash healthcheck.sh

# Nur URL-Check
curl -s https://example.com/api/v1/health | python3 -m json.tool
```

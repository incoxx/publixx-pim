# Publixx PIM — Installations-Anleitung

## Voraussetzungen

| Software | Version |
|---|---|
| Ubuntu / Debian | 22.04+ / 24.04 empfohlen |
| PHP | 8.2+ (mit Extensions: mysql, redis, mbstring, xml, zip, gd, bcmath, curl, intl) |
| MySQL | 8.0+ |
| Redis | 6+ |
| Node.js | 18+ (mit npm) |
| Composer | 2.x |
| Apache | 2.4 (mit mod_rewrite, mod_headers, mod_alias) |
| Supervisor | fuer Laravel Horizon (Queue Worker) |
| Git | fuer Deployment und Updates |

## Installation mit setup.sh

Das interaktive Setup-Script installiert alles automatisch:

```bash
# Repository klonen
git clone <repository-url> /var/www/publixx-pim
cd /var/www/publixx-pim

# Setup starten (als root)
sudo bash setup.sh
```

### Interaktive Abfragen

Das Script fragt folgende Informationen ab:

1. **Server-Domain** — z.B. `smartentities.de`
2. **Web-Pfad** — leer fuer Root (`/`) oder z.B. `/web` fuer Subdirectory
3. **HTTPS** — Ja/Nein (mit optionalem Let's Encrypt)
4. **Port** — Standard 80/443 oder benutzerdefiniert
5. **MySQL-Zugangsdaten** — Host, Port, Datenbankname, Benutzer, Passwort
6. **Redis-Host** — Standard 127.0.0.1
7. **Admin-Account** — E-Mail und Passwort fuer den ersten Benutzer

### Was setup.sh macht (10 Schritte)

| Schritt | Beschreibung |
|---|---|
| 1/10 | Systempakete installieren (PHP, MySQL-Client, Redis, Node.js, Composer) |
| 2/10 | PHP-Extensions pruefen und installieren |
| 3/10 | Composer Install (PHP-Abhaengigkeiten) |
| 4/10 | .env-Datei generieren + APP_KEY |
| 5/10 | Datenbank erstellen und Migrationen ausfuehren |
| 6/10 | Admin-Benutzer anlegen |
| 7/10 | Demo-Daten laden (optional) |
| 8/10 | Supervisor/Horizon konfigurieren |
| 9/10 | Frontend bauen (npm ci + npm run build) |
| 10/10 | Apache konfigurieren + Berechtigungen setzen |

## Deployment-Modi

### Root-Modus (PIM = einzige App)

- Eigener Apache VHost auf Port 80 (+ 443 mit SSL)
- Optionale Let's Encrypt Einrichtung
- `APP_URL=https://example.com`

### Subdirectory-Modus (PIM unter /pfad)

- Apache-Alias wird in den bestehenden VHost eingebunden
- SSL wird vom bestehenden VHost gehandhabt
- `APP_URL=https://example.com/web`
- Frontend wird automatisch mit korrektem Base-Path gebaut

```bash
# Beispiel: PIM unter /web
#   Domain: smartentities.de
#   Web-Pfad: /web
#   → APP_URL=https://smartentities.de/web
#   → Frontend Base-Path: /web/
#   → API: https://smartentities.de/web/api/v1/...
```

## SSL / Let's Encrypt

### Neues Zertifikat einrichten (Root-Modus)

Das Setup bietet automatisch Let's Encrypt an, wenn HTTPS gewaehlt wird.

### Bestehendes Zertifikat

Wenn bereits ein Let's Encrypt-Zertifikat fuer die Domain existiert, erkennt das Script dies und fragt, ob es beibehalten oder erneuert werden soll.

### Subdirectory-Modus

Kein SSL-Setup noetig — der bestehende VHost handhabt SSL.

## Nach der Installation

### Healthcheck

```bash
# Lokal (prueft alle Services)
bash healthcheck.sh

# Per URL
bash healthcheck.sh --url-only

# Oder direkt im Browser
curl https://example.com/api/v1/health
```

### Demo-Daten laden

```bash
cd /var/www/publixx-pim
php artisan db:seed --class=DemoAttributeSeeder
php artisan db:seed --class=DemoHierarchySeeder
php artisan db:seed --class=DemoProductSeeder
```

### Manuell einloggen

Oeffen die APP_URL im Browser. Login mit den bei der Installation angegebenen Admin-Zugangsdaten.

## Fehlerbehebung

### "Permission denied" Fehler

```bash
sudo chown -R www-data:www-data /var/www/publixx-pim
sudo chmod -R 775 /var/www/publixx-pim/storage
sudo chmod -R 775 /var/www/publixx-pim/bootstrap/cache
```

### Apache-Konfiguration pruefen

```bash
sudo apache2ctl configtest
sudo systemctl status apache2
```

### Laravel-Logs

```bash
# Per API
curl https://example.com/api/v1/debug/logs

# Oder direkt
tail -100 /var/www/publixx-pim/storage/logs/laravel.log
```

### Horizon / Queue

```bash
sudo supervisorctl status horizon
sudo supervisorctl restart horizon
```

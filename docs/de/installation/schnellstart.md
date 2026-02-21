---
title: Schnellstart
---

# Schnellstart

Diese Anleitung führt Sie in wenigen Schritten von einem leeren System zu einem lokal laufenden Publixx PIM. Sie richtet sich an Entwickler, die das System schnell einrichten und sofort produktiv arbeiten möchten.

::: tip Voraussetzung
Stellen Sie sicher, dass alle in den [Voraussetzungen](./voraussetzungen) beschriebenen Abhängigkeiten installiert sind, bevor Sie beginnen.
:::

## 1. Repository klonen

```bash
git clone git@github.com:publixx/publixx-pim.git
cd publixx-pim
```

## 2. PHP-Abhängigkeiten installieren

Installieren Sie alle Backend-Pakete mit Composer:

```bash
composer install
```

Dieser Befehl installiert Laravel 11 sowie alle benötigten PHP-Pakete. Bei der ersten Installation kann dies einige Minuten dauern.

## 3. Frontend-Abhängigkeiten installieren

Das Vue.js-Frontend befindet sich im Unterverzeichnis `pim-frontend`. Wechseln Sie dorthin und installieren Sie die npm-Pakete:

```bash
cd pim-frontend
npm install
cd ..
```

## 4. Umgebungskonfiguration

Kopieren Sie die Beispielkonfiguration und passen Sie die Werte an Ihre lokale Umgebung an:

```bash
cp .env.example .env
php artisan key:generate
```

Öffnen Sie die `.env`-Datei und konfigurieren Sie mindestens folgende Werte:

### Anwendung

```dotenv
APP_NAME="Publixx PIM"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:5173
```

### Datenbank

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=publixx_pim
DB_USERNAME=root
DB_PASSWORD=ihr_passwort
```

Erstellen Sie die Datenbank, falls noch nicht geschehen:

```bash
mysql -u root -p -e "CREATE DATABASE publixx_pim CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### Redis

```dotenv
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
```

### Sanctum (API-Authentifizierung)

```dotenv
SANCTUM_STATEFUL_DOMAINS=localhost:5173,localhost:8000
```

## 5. Datenbank einrichten

Führen Sie die Migrationen aus, um das Datenbankschema zu erstellen, und starten Sie anschliessend die Seeder, um Basisdaten wie Rollen, Berechtigungen und den Admin-Benutzer anzulegen:

```bash
php artisan migrate
php artisan db:seed
```

::: info Standard-Zugangsdaten
Nach dem Seeding steht ein Admin-Benutzer zur Verfügung. Die Zugangsdaten werden während des Seedings in der Konsole ausgegeben. Notieren Sie sich diese.
:::

## 6. Storage-Link erstellen

Laravel benötigt einen symbolischen Link, um öffentlich zugängliche Dateien (Medien, Uploads) aus dem `storage`-Verzeichnis bereitzustellen:

```bash
php artisan storage:link
```

## 7. Frontend bauen (optional für Produktion)

Wenn Sie das Frontend als statische Dateien bereitstellen möchten (z. B. für einen produktionsnahen Test), bauen Sie es und kopieren Sie die Ausgabe in das `public`-Verzeichnis:

```bash
cd pim-frontend
npm run build
cp -r dist/* ../public/
cd ..
```

::: warning Hinweis
Für die lokale Entwicklung nutzen Sie stattdessen den Vite-Entwicklungsserver (siehe Schritt 8). Das Bauen ist nur für produktive oder produktionsnahe Umgebungen nötig.
:::

## 8. Entwicklungsserver starten

Starten Sie das Backend und das Frontend in zwei separaten Terminals:

**Terminal 1 -- Laravel Backend:**

```bash
php artisan serve
```

Das Backend ist nun unter `http://localhost:8000` erreichbar.

**Terminal 2 -- Vite Frontend (Entwicklungsmodus):**

```bash
cd pim-frontend
npm run dev
```

Das Frontend ist nun unter `http://localhost:5173` erreichbar und unterstützt Hot Module Replacement (HMR) für schnelle Entwicklungszyklen.

**Terminal 3 -- Queue Worker (optional):**

Für die Verarbeitung von Hintergrundaufgaben (Import, Export) starten Sie den Queue-Worker:

```bash
php artisan horizon
```

Das Horizon-Dashboard ist unter `http://localhost:8000/horizon` erreichbar.

## 9. Installation überprüfen

Öffnen Sie `http://localhost:5173` in Ihrem Browser. Sie sollten die Login-Seite des Publixx PIM sehen. Melden Sie sich mit den beim Seeding erstellten Zugangsdaten an.

### Checkliste

| Prüfpunkt | Erwartetes Ergebnis |
|---|---|
| Login-Seite wird angezeigt | Frontend-Build und API-Verbindung funktionieren |
| Login mit Admin-Zugangsdaten | Authentifizierung und Datenbankverbindung funktionieren |
| Dashboard wird geladen | SPA-Routing und API-Endpunkte funktionieren |
| Horizon-Dashboard erreichbar | Redis-Verbindung und Queue-System funktionieren |

## Häufige Probleme

### CORS-Fehler im Browser

Stellen Sie sicher, dass `FRONTEND_URL` und `SANCTUM_STATEFUL_DOMAINS` in der `.env`-Datei korrekt auf die Frontend-URL gesetzt sind.

### Datenbankverbindung schlägt fehl

Prüfen Sie, ob der MySQL-Dienst läuft und die Zugangsdaten in `.env` korrekt sind:

```bash
php artisan db:monitor
```

### Redis-Verbindung schlägt fehl

Prüfen Sie, ob Redis läuft:

```bash
redis-cli ping
# Erwartete Antwort: PONG
```

### Berechtigungsprobleme bei Storage

```bash
chmod -R 775 storage bootstrap/cache
chown -R $USER:www-data storage bootstrap/cache
```

## Nächster Schritt

Für den produktiven Betrieb lesen Sie die Anleitung zum [Deployment](./deployment).

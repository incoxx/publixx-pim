# Umgebungsvariablen — Vollständige Referenz

Alle `.env`-Schlüssel von anyPIM an einem Ort. Die Datei `.env` wird **nicht** ins Repository eingecheckt; `.env.example` dient als Vorlage.

**Nach jeder Änderung an `.env` im Produktionsbetrieb:**
```bash
php artisan config:cache
php artisan queue:restart   # falls Queue-Worker laufen
```

**Dateien im Überblick:**

| Datei | Zweck |
|-------|-------|
| `.env` | Aktive Konfiguration (nicht eingecheckt) |
| `.env.example` | Vorlage für die Haupt-App |
| `.env.production.example` | Vorlage für Produktionsserver |
| `tms/.env.example` | Vorlage für den TMS-Microservice |
| `video-engine/.env.example` | Vorlage für die Video-Engine |

Legende: **✓** = Pflichtfeld · **–** = Optional · **Prod** = Nur in Produktion relevant · **⚠️** = Sensitiver Wert

---

## Inhaltsverzeichnis

1. [App](#1-app)
2. [Logging](#2-logging)
3. [Datenbank](#3-datenbank)
4. [Redis](#4-redis)
5. [Cache](#5-cache)
6. [Queue & Horizon](#6-queue--horizon)
7. [Session & Auth](#7-session--auth)
8. [Dateisystem](#8-dateisystem)
9. [Mail](#9-mail)
10. [Frontend / CORS](#10-frontend--cors)
11. [Fehlerklassifikation & Weiterleitung](#11-fehlerklassifikation--weiterleitung)
12. [Typesense (PDF-Suche)](#12-typesense-pdf-suche)
13. [TMS (Übersetzungsservice)](#13-tms-übersetzungsservice)
14. [SSO (Azure AD / Entra ID)](#14-sso-azure-ad--entra-id)
15. [Lizenz](#15-lizenz)
16. [Video Engine](#16-video-engine)
17. [Connectors](#17-connectors)
18. [Alternative E-Mail-Provider](#18-alternative-e-mail-provider)
19. [Slack Bot-Integration](#19-slack-bot-integration)
20. [Deployment](#20-deployment)

---

## 1. App

Grundlegende Laravel-Anwendungskonfiguration.

| Variable | Standard | Pflicht | Beschreibung |
|----------|----------|---------|--------------|
| `APP_NAME` | `"anyPIM"` | ✓ | Anwendungsname (erscheint in E-Mails, Titeln) |
| `APP_ENV` | `local` | ✓ | Umgebung: `local`, `staging`, `production` |
| `APP_KEY` | *(leer)* | ✓ ⚠️ | 32-Byte-Verschlüsselungsschlüssel. Generieren: `php artisan key:generate` |
| `APP_DEBUG` | `true` | ✓ | `false` in Produktion — verhindert Stacktrace-Ausgabe |
| `APP_URL` | `http://localhost:8000` | ✓ | Öffentliche Basis-URL der Anwendung (kein abschließendes `/`) |

---

## 2. Logging

| Variable | Standard | Pflicht | Beschreibung |
|----------|----------|---------|--------------|
| `LOG_CHANNEL` | `stack` | – | Log-Kanal: `stack`, `single`, `daily`, `stderr`, `syslog` |
| `LOG_LEVEL` | `debug` | – | Minimales Log-Level: `debug`, `info`, `warning`, `error`, `critical` |
| `LOG_DEPRECATIONS_CHANNEL` | `null` | – | Kanal für PHP-Deprecation-Warnungen (`null` = deaktiviert) |

---

## 3. Datenbank

anyPIM benötigt MySQL 8+.

| Variable | Standard | Pflicht | Beschreibung |
|----------|----------|---------|--------------|
| `DB_CONNECTION` | `mysql` | ✓ | Datenbanktreiber (nur `mysql` unterstützt) |
| `DB_HOST` | `127.0.0.1` | ✓ | Hostname des Datenbankservers |
| `DB_PORT` | `3306` | ✓ | Port des Datenbankservers |
| `DB_DATABASE` | `publixx_pim` | ✓ | Name der Datenbank |
| `DB_USERNAME` | `root` | ✓ ⚠️ | Datenbankbenutzer |
| `DB_PASSWORD` | *(leer)* | ✓ ⚠️ | Datenbankpasswort |

**Erweiterte Optionen (selten benötigt):**

| Variable | Standard | Beschreibung |
|----------|----------|--------------|
| `DB_URL` | *(leer)* | DSN-Connection-String (überschreibt die einzelnen DB_*-Vars) |
| `MYSQL_ATTR_SSL_CA` | *(leer)* | Pfad zum SSL-CA-Zertifikat für verschlüsselte DB-Verbindungen |

---

## 4. Redis

anyPIM nutzt Redis für Cache, Queue und Sessions — drei getrennte Datenbanken.

| Variable | Standard | Pflicht | Beschreibung |
|----------|----------|---------|--------------|
| `REDIS_CLIENT` | `phpredis` | ✓ | PHP-Client: `phpredis` (empfohlen) oder `predis` |
| `REDIS_HOST` | `127.0.0.1` | ✓ | Redis-Hostname |
| `REDIS_PORT` | `6379` | ✓ | Redis-Port |
| `REDIS_PASSWORD` | `null` | – ⚠️ | Redis-Passwort (`null` = kein Passwort) |
| `REDIS_CACHE_DB` | `1` | – | Redis-DB-Index für Cache |
| `REDIS_QUEUE_DB` | `2` | – | Redis-DB-Index für Queue |
| `REDIS_SESSION_DB` | `3` | – | Redis-DB-Index für Sessions |

---

## 5. Cache

| Variable | Standard | Pflicht | Beschreibung |
|----------|----------|---------|--------------|
| `CACHE_STORE` | `redis` | – | Cache-Backend: `redis`, `file`, `array` |
| `CACHE_PREFIX` | `pim` | – | Präfix für alle Cache-Keys |

**TTL-Overrides (Sekunden):** Standardwerte sind für die meisten Umgebungen sinnvoll.

| Variable | Standard | Beschreibung |
|----------|----------|--------------|
| `CACHE_TTL_PRODUCT_FULL` | `3600` | Vollständiges Produkt-Objekt (1 h) |
| `CACHE_TTL_PRODUCT_LANG` | `3600` | Produktdaten pro Sprache (1 h) |
| `CACHE_TTL_HIERARCHY_TREE` | `21600` | Hierarchie-Baum (6 h) |
| `CACHE_TTL_PQL_RESULT` | `900` | PQL-Abfrageergebnis (15 min) |
| `CACHE_TTL_PRODUCT_LIST` | `300` | Produktlisten (5 min) |
| `CACHE_TTL_ATTRIBUTES_ALL` | `3600` | Alle Attribute (1 h) |
| `CACHE_TTL_EXPORT_MAPPING` | `1800` | Export-Mapping-Regeln (30 min) |

---

## 6. Queue & Horizon

| Variable | Standard | Pflicht | Beschreibung |
|----------|----------|---------|--------------|
| `QUEUE_CONNECTION` | `redis` | ✓ | Queue-Backend: `redis` (Produktion), `sync` (nur lokal/Test) |
| `HORIZON_PREFIX` | `pim_horizon:` | – | Redis-Key-Präfix für Laravel Horizon |

---

## 7. Session & Auth

| Variable | Standard | Pflicht | Beschreibung |
|----------|----------|---------|--------------|
| `SESSION_DRIVER` | `redis` | – | Session-Backend: `redis`, `file`, `cookie`, `database` |
| `SESSION_LIFETIME` | `120` | – | Session-Lebensdauer in Minuten |
| `SESSION_SECURE_COOKIE` | *(leer)* | Prod | `true` in Produktion — Cookie nur über HTTPS |
| `SANCTUM_STATEFUL_DOMAINS` | `localhost,…` | ✓ | Kommagetrennte Domains für Sanctum-SPA-Authentifizierung |

---

## 8. Dateisystem

| Variable | Standard | Pflicht | Beschreibung |
|----------|----------|---------|--------------|
| `FILESYSTEM_DISK` | `local` | – | Standard-Disk: `local` oder `s3` |

---

## 9. Mail

Für Benachrichtigungen (kritische Fehler, Weiterleitungen). Im Entwicklungsbetrieb mit `MAIL_MAILER=log` werden E-Mails in `storage/logs/laravel.log` geschrieben.

| Variable | Standard | Pflicht | Beschreibung |
|----------|----------|---------|--------------|
| `MAIL_MAILER` | `log` | ✓ | Treiber: `log`, `smtp`, `ses`, `postmark`, `resend` |
| `MAIL_HOST` | `smtp.example.com` | – | SMTP-Hostname |
| `MAIL_PORT` | `587` | – | SMTP-Port (`587` für TLS, `465` für SSL, `25` ohne) |
| `MAIL_USERNAME` | *(leer)* | – ⚠️ | SMTP-Benutzername |
| `MAIL_PASSWORD` | *(leer)* | – ⚠️ | SMTP-Passwort |
| `MAIL_ENCRYPTION` | `tls` | – | Verschlüsselung: `tls`, `ssl`, `null` |
| `MAIL_FROM_ADDRESS` | `noreply@anypim.local` | ✓ | Absenderadresse |
| `MAIL_FROM_NAME` | `"${APP_NAME}"` | ✓ | Absendername |

> Für alternative Mail-Provider (Postmark, Resend, AWS SES) siehe [Abschnitt 18](#18-alternative-e-mail-provider).

---

## 10. Frontend / CORS

| Variable | Standard | Pflicht | Beschreibung |
|----------|----------|---------|--------------|
| `FRONTEND_URL` | `http://localhost:3000` | ✓ | Basis-URL des Vue-Frontends — wird für CORS-Whitelist verwendet |

---

## 11. Fehlerklassifikation & Weiterleitung

Beim Klick auf „An Entwicklung weiterleiten" können E-Mail, Slack und Jira ausgelöst werden. Alle Vars sind optional — nicht gesetzte Kanäle werden still übersprungen.

### E-Mail & Slack

| Variable | Standard | Pflicht | Beschreibung |
|----------|----------|---------|--------------|
| `ERROR_FORWARD_EMAIL` | *(leer)* | – | Empfängeradresse für weitergeleitete Fehler (z. B. `dev@example.com`) |
| `ERROR_FORWARD_SLACK_WEBHOOK` | *(leer)* | – ⚠️ | Slack Incoming Webhook URL für Fehler-Weiterleitungen |

### Jira (Cloud)

Konfiguriert in `config/connectors.php` unter dem Key `jira`.

| Variable | Standard | Pflicht | Beschreibung |
|----------|----------|---------|--------------|
| `JIRA_URL` | *(leer)* | – | Jira-Instanz-URL, z. B. `https://your-domain.atlassian.net` |
| `JIRA_EMAIL` | *(leer)* | – ⚠️ | E-Mail-Adresse des Jira-API-Benutzers |
| `JIRA_API_TOKEN` | *(leer)* | – ⚠️ | Atlassian API Token (nicht das Passwort — erstellen unter id.atlassian.com) |
| `JIRA_PROJECT_KEY` | *(leer)* | – | Projekt-Key in Jira, z. B. `DEV` |
| `JIRA_ISSUE_TYPE` | `Bug` | – | Issue-Typ für neue Tickets, z. B. `Bug`, `Task`, `Story` |

**Frontend:**

| Variable | Standard | Pflicht | Beschreibung |
|----------|----------|---------|--------------|
| `VITE_JIRA_URL` | *(leer)* | – | Jira-Basis-URL fürs Frontend (wird als klickbarer Link im Fehler-Detail angezeigt). In `pim-frontend/.env` setzen. |

---

## 12. Typesense (PDF-Suche)

Optionale Volltextsuche in PDF-Dokumenten. Siehe [`docs/guides/pdf-suche-typesense.md`](../guides/pdf-suche-typesense.md).

| Variable | Standard | Pflicht | Beschreibung |
|----------|----------|---------|--------------|
| `TYPESENSE_HOST` | `localhost` | – | Typesense-Serveradresse |
| `TYPESENSE_PORT` | `8108` | – | Typesense-Port |
| `TYPESENSE_PROTOCOL` | `http` | – | Protokoll: `http` oder `https` |
| `TYPESENSE_API_KEY` | *(leer)* | – ⚠️ | API-Key (liegt in `typesense-data/typesense-server.ini`) |

---

## 13. TMS (Übersetzungsservice)

Der TMS-Microservice läuft eigenständig mit einer eigenen `.env` (`tms/.env.example`). Die folgenden Vars steuern die **Verbindung vom PIM zum TMS**.

| Variable | Standard | Pflicht | Beschreibung |
|----------|----------|---------|--------------|
| `TMS_ENABLED` | `false` | – | TMS-Integration aktivieren (`true`/`false`) |
| `TMS_BASE_URL` | `http://localhost:8001/api` | – | Basis-URL der TMS-API |
| `TMS_API_KEY` | `change-me-to-a-secure-key` | – ⚠️ | Shared Secret zwischen PIM und TMS |
| `TMS_TIMEOUT` | `5` | – | HTTP-Timeout in Sekunden |
| `TMS_TARGET_LANGUAGES` | `en,fr,es,it,nl` | – | Kommagetrennte Zielsprachen für Übersetzungen |

**TMS-interne Vars** (in `tms/.env`, nicht in der Haupt-`.env`):

| Variable | Standard | Beschreibung |
|----------|----------|--------------|
| `TMS_PROVIDER_CHAIN` | `deepl,google` | Reihenfolge der Übersetzungs-Provider |
| `TMS_CACHE_TTL` | `86400` | Cache-Lebensdauer für Übersetzungen (Sekunden) |
| `DEEPL_API_KEY` | *(leer)* | ⚠️ DeepL API Key (Free oder Pro) |
| `DEEPL_API_URL` | `https://api-free.deepl.com/v2` | DeepL-Endpunkt (Pro: `https://api.deepl.com/v2`) |
| `GOOGLE_TRANSLATE_API_KEY` | *(leer)* | ⚠️ Google Cloud Translation API Key |
| `ANTHROPIC_API_KEY` | *(leer)* | ⚠️ Claude API Key für KI-gestützte Übersetzungsverbesserungen |
| `ANTHROPIC_MODEL` | `claude-sonnet-4-6` | Verwendetes Claude-Modell im TMS |

---

## 14. SSO (Azure AD / Entra ID)

Optionale Single-Sign-On-Integration. Konfiguriert in `config/services.php` unter `azure`.

| Variable | Standard | Pflicht | Beschreibung |
|----------|----------|---------|--------------|
| `SSO_ENABLED` | `false` | – | SSO-Login aktivieren |
| `SSO_AUTO_PROVISION` | `true` | – | Neuen PIM-User automatisch anlegen wenn Azure-Login erfolgreich |
| `SSO_DEFAULT_ROLE` | `Viewer` | – | Standardrolle für neu provisionierte SSO-Benutzer |
| `AZURE_AD_CLIENT_ID` | *(leer)* | – ⚠️ | Azure App Registration — Application (Client) ID |
| `AZURE_AD_CLIENT_SECRET` | *(leer)* | – ⚠️ | Azure App Registration — Client Secret |
| `AZURE_AD_TENANT_ID` | *(leer)* | – | Azure Tenant ID |
| `AZURE_AD_REDIRECT_URI` | `${APP_URL}/api/v1/auth/sso/callback` | – | OAuth Callback-URL (muss in Azure registriert sein) |

---

## 15. Lizenz

| Variable | Standard | Pflicht | Beschreibung |
|----------|----------|---------|--------------|
| `ANYPIM_LICENSE_PUBLIC_KEY` | *(leer)* | Prod ⚠️ | Ed25519-Public-Key zur Lizenz-Signaturprüfung (nur Produktion) |
| `ANYPIM_LICENSE_KEY` | *(leer)* | – ⚠️ | Fallback-Lizenzschlüssel (greift wenn Datenbank zurückgesetzt wird) |

---

## 16. Video Engine

Die Video-Engine läuft eigenständig mit eigener `.env` (`video-engine/.env.example`). Die folgenden Vars steuern sie aus der Haupt-App heraus. Siehe [`docs/features/video-engine.md`](../features/video-engine.md).

| Variable | Standard | Pflicht | Beschreibung |
|----------|----------|---------|--------------|
| `VIDEO_ENGINE_ENABLED` | `true` | – | Video-Engine-Feature aktivieren |
| `VIDEO_ENGINE_BASE_URL` | `http://localhost:8000` | – | URL der anyPIM-Instanz für Playwright-Aufnahmen |
| `VIDEO_ENGINE_OUTPUT_DIR` | `public/videos` | – | Ausgabeverzeichnis für fertige Videos (relativ zum Projekt-Root) |
| `VIDEO_ENGINE_DISPLAY` | `:99` | – | Xvfb Virtual-Display-Nummer |
| `VIDEO_ENGINE_FPS` | `30` | – | Bildrate der Aufnahmen |
| `VIDEO_ENGINE_QUALITY` | `high` | – | Qualitätsprofil: `low`, `medium`, `high` |
| `VIDEO_DEMO_USER_EMAIL` | `demo@anypim.local` | – | E-Mail des Demo-Benutzers für Video-Aufnahmen |
| `VIDEO_DEMO_USER_PASSWORD` | `demo1234` | – ⚠️ | Passwort des Demo-Benutzers |

**ElevenLabs Text-to-Speech:**

| Variable | Standard | Pflicht | Beschreibung |
|----------|----------|---------|--------------|
| `ELEVENLABS_API_KEY` | *(leer)* | – ⚠️ | ElevenLabs API Key für KI-Sprachsynthese |
| `ELEVENLABS_VOICE_DE_FEMALE` | *(leer)* | – | Voice-ID für deutsche weibliche Stimme |
| `ELEVENLABS_VOICE_DE_MALE` | *(leer)* | – | Voice-ID für deutsche männliche Stimme |
| `ELEVENLABS_FALLBACK` | `gtts` | – | Fallback-TTS wenn ElevenLabs nicht verfügbar: `gtts` oder `silent` |

**S3-Upload (Video-Engine-intern, in `video-engine/.env`):**

| Variable | Standard | Beschreibung |
|----------|----------|--------------|
| `VIDEO_S3_UPLOAD` | `false` | Fertige Videos nach S3 hochladen |
| `AWS_BUCKET` | *(leer)* | S3-Bucket-Name für Video-Uploads |

---

## 17. Connectors

Alle Connector-Credentials sind in `config/connectors.php` zentral gebündelt.

### Claude AI (Anthropic)

Wird für die KI-gestützte Fehlerklassifikation genutzt. Ohne Key wird automatisch auf regelbasierte Klassifikation umgeschaltet.

| Variable | Standard | Pflicht | Beschreibung |
|----------|----------|---------|--------------|
| `CLAUDE_AI_API_KEY` | *(leer)* | – ⚠️ | Anthropic API Key (console.anthropic.com) |
| `CLAUDE_AI_BASE_URL` | `https://api.anthropic.com/v1` | – | API-Endpunkt (nur ändern für Proxys) |
| `CLAUDE_AI_MODEL` | `claude-sonnet-4-5-20250929` | – | Modell-ID |
| `CLAUDE_AI_MAX_TOKENS` | `1024` | – | Maximale Tokens pro Antwort |

### OpenAI

| Variable | Standard | Pflicht | Beschreibung |
|----------|----------|---------|--------------|
| `OPENAI_API_KEY` | *(leer)* | – ⚠️ | OpenAI API Key (platform.openai.com) |
| `OPENAI_BASE_URL` | `https://api.openai.com/v1` | – | API-Endpunkt |
| `OPENAI_MODEL` | `gpt-4o` | – | Modell-ID |
| `OPENAI_MAX_TOKENS` | `4096` | – | Maximale Tokens pro Antwort |

### Canva

OAuth 2.0-Integration für Canva-Designs.

| Variable | Standard | Pflicht | Beschreibung |
|----------|----------|---------|--------------|
| `CANVA_CLIENT_ID` | *(leer)* | – ⚠️ | Canva App Client ID |
| `CANVA_CLIENT_SECRET` | *(leer)* | – ⚠️ | Canva App Client Secret |
| `CANVA_REDIRECT_URI` | *(leer)* | – | OAuth Redirect URI (muss in Canva registriert sein) |
| `CANVA_API_BASE_URL` | `https://api.canva.com/rest/v1` | – | Canva API-Endpunkt |

### DeepL

Direkte DeepL-Integration (außerhalb des TMS).

| Variable | Standard | Pflicht | Beschreibung |
|----------|----------|---------|--------------|
| `DEEPL_API_KEY` | *(leer)* | – ⚠️ | DeepL API Key |
| `DEEPL_BASE_URL` | `https://api-free.deepl.com/v2` | – | API-Endpunkt (Pro: `https://api.deepl.com/v2`) |

### Shopware

| Variable | Standard | Pflicht | Beschreibung |
|----------|----------|---------|--------------|
| `SHOPWARE_SHOP_URL` | *(leer)* | – | Shopware-Shop-URL, z. B. `https://shop.example.com` |
| `SHOPWARE_CLIENT_ID` | *(leer)* | – ⚠️ | OAuth2 Client ID (Integration in Shopware erstellen) |
| `SHOPWARE_CLIENT_SECRET` | *(leer)* | – ⚠️ | OAuth2 Client Secret |

### Shopify

| Variable | Standard | Pflicht | Beschreibung |
|----------|----------|---------|--------------|
| `SHOPIFY_SHOP_URL` | *(leer)* | – | Shop-URL, z. B. `https://mein-shop.myshopify.com` |
| `SHOPIFY_ACCESS_TOKEN` | *(leer)* | – ⚠️ | Statischer Admin API Access Token (Legacy) |
| `SHOPIFY_CLIENT_ID` | *(leer)* | – ⚠️ | OAuth2 Client ID (ab 2026) |
| `SHOPIFY_CLIENT_SECRET` | *(leer)* | – ⚠️ | OAuth2 Client Secret (ab 2026) |

### Cloudinary

| Variable | Standard | Pflicht | Beschreibung |
|----------|----------|---------|--------------|
| `CLOUDINARY_CLOUD_NAME` | *(leer)* | – | Cloudinary Cloud Name |
| `CLOUDINARY_API_KEY` | *(leer)* | – ⚠️ | Cloudinary API Key |
| `CLOUDINARY_API_SECRET` | *(leer)* | – ⚠️ | Cloudinary API Secret |

### Salesforce Commerce Cloud (SFCC)

| Variable | Standard | Pflicht | Beschreibung |
|----------|----------|---------|--------------|
| `SFCC_INSTANCE_URL` | *(leer)* | – | SFCC-Instanz-URL, z. B. `https://abcd-001.dx.commercecloud.salesforce.com` |
| `SFCC_CLIENT_ID` | *(leer)* | – ⚠️ | Account Manager Client ID |
| `SFCC_CLIENT_SECRET` | *(leer)* | – ⚠️ | Account Manager Client Secret |
| `SFCC_SITE_ID` | *(leer)* | – | Site ID, z. B. `RefArch` |
| `SFCC_CATALOG_ID` | `storefront-catalog` | – | Katalog-ID für Produktexporte |

---

## 18. Alternative E-Mail-Provider

Wenn `MAIL_MAILER` auf `ses`, `postmark` oder `resend` gesetzt ist, werden diese zusätzlichen Credentials benötigt.

### AWS SES

| Variable | Standard | Pflicht | Beschreibung |
|----------|----------|---------|--------------|
| `AWS_ACCESS_KEY_ID` | *(leer)* | – ⚠️ | AWS Access Key ID |
| `AWS_SECRET_ACCESS_KEY` | *(leer)* | – ⚠️ | AWS Secret Access Key |
| `AWS_DEFAULT_REGION` | `us-east-1` | – | AWS-Region für SES |

### Postmark

| Variable | Standard | Pflicht | Beschreibung |
|----------|----------|---------|--------------|
| `POSTMARK_TOKEN` | *(leer)* | – ⚠️ | Postmark Server API Token |

### Resend

| Variable | Standard | Pflicht | Beschreibung |
|----------|----------|---------|--------------|
| `RESEND_KEY` | *(leer)* | – ⚠️ | Resend API Key |

---

## 19. Slack Bot-Integration

Für die Bot-basierte Slack-Anbindung (nicht zu verwechseln mit dem Incoming Webhook aus Abschnitt 11). Konfiguriert in `config/services.php`.

| Variable | Standard | Pflicht | Beschreibung |
|----------|----------|---------|--------------|
| `SLACK_BOT_USER_OAUTH_TOKEN` | *(leer)* | – ⚠️ | OAuth Token des Slack Bot Users (`xoxb-…`) |
| `SLACK_BOT_USER_DEFAULT_CHANNEL` | *(leer)* | – | Standard-Kanal-ID oder Name (z. B. `#dev-alerts`) |

---

## 20. Deployment

| Variable | Standard | Pflicht | Beschreibung |
|----------|----------|---------|--------------|
| `DEPLOY_USER` | *(leer)* | – | System-User, dem die Projektdateien gehören (für `sudo` in Deploy-Kommandos). Leer lassen für automatische Erkennung. |

---

*Zuletzt aktualisiert: 2026-04-07*

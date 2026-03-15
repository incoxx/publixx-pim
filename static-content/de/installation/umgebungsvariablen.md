# Umgebungsvariablen

anyPIM wird über eine `.env`-Datei im Projekt-Stammverzeichnis konfiguriert. Diese Seite dokumentiert alle verfügbaren Variablen nach Subsystem gruppiert.

::: tip
Kopieren Sie `.env.example` nach `.env` und passen Sie die Werte an Ihre Umgebung an. Für Produktivumgebungen steht `.env.production.example` als Vorlage bereit.
:::

## Anwendung

| Variable | Standard | Beschreibung |
|----------|----------|--------------|
| `APP_NAME` | `anyPIM` | Anwendungsname für E-Mails und UI |
| `APP_ENV` | `local` | Umgebung: `local`, `staging` oder `production` |
| `APP_KEY` | — | Verschlüsselungsschlüssel. Generierung: `php artisan key:generate` |
| `APP_DEBUG` | `true` | Detaillierte Fehlerseiten. **Muss in Produktion `false` sein** |
| `APP_URL` | `http://localhost:8000` | Backend-Basis-URL für URL-Generierung |

## Logging

| Variable | Standard | Beschreibung |
|----------|----------|--------------|
| `LOG_CHANNEL` | `stack` | Log-Kanal (stack, single, daily, stderr, syslog) |
| `LOG_DEPRECATIONS_CHANNEL` | `null` | Kanal für PHP-Deprecation-Hinweise |
| `LOG_LEVEL` | `debug` | Mindest-Level: debug, info, notice, warning, error, critical. In Produktion: `warning` |

## Datenbank

| Variable | Standard | Beschreibung |
|----------|----------|--------------|
| `DB_CONNECTION` | `mysql` | Datenbanktreiber |
| `DB_HOST` | `127.0.0.1` | Datenbankserver |
| `DB_PORT` | `3306` | Datenbankport |
| `DB_DATABASE` | `publixx_pim` | Datenbankname |
| `DB_USERNAME` | `root` | Datenbankbenutzer |
| `DB_PASSWORD` | — | Datenbankpasswort |

## Redis

anyPIM verwendet separate Redis-Datenbanken für Cache, Queues und Sessions.

| Variable | Standard | Beschreibung |
|----------|----------|--------------|
| `REDIS_CLIENT` | `phpredis` | Redis-Client (`phpredis` oder `predis`) |
| `REDIS_HOST` | `127.0.0.1` | Redis-Server |
| `REDIS_PASSWORD` | `null` | Redis-Passwort (`null` = keine Auth) |
| `REDIS_PORT` | `6379` | Redis-Port |
| `REDIS_CACHE_DB` | `1` | Redis-DB-Index für Cache |
| `REDIS_QUEUE_DB` | `2` | Redis-DB-Index für Queues |
| `REDIS_SESSION_DB` | `3` | Redis-DB-Index für Sessions |

## Cache

| Variable | Standard | Beschreibung |
|----------|----------|--------------|
| `CACHE_STORE` | `redis` | Cache-Backend (`redis`, `file`, `array`) |
| `CACHE_PREFIX` | `pim` | Key-Präfix zur Vermeidung von Kollisionen |

### Cache-TTL-Overrides (Sekunden)

| Variable | Standard | Beschreibung |
|----------|----------|--------------|
| `CACHE_TTL_PRODUCT_FULL` | `3600` | Vollständiges Produkt mit Relationen |
| `CACHE_TTL_PRODUCT_LANG` | `3600` | Sprachspezifische Produktdaten |
| `CACHE_TTL_HIERARCHY_TREE` | `21600` | Hierarchie-Baumstruktur (6 Stunden) |
| `CACHE_TTL_PQL_RESULT` | `900` | PQL-Abfrageergebnisse (15 Minuten) |
| `CACHE_TTL_PRODUCT_LIST` | `300` | Produktlisten-Seiten (5 Minuten) |
| `CACHE_TTL_ATTRIBUTES_ALL` | `3600` | Vollständiger Attributkatalog |
| `CACHE_TTL_EXPORT_MAPPING` | `1800` | Export-Mapping-Konfiguration (30 Minuten) |

## Queue & Horizon

| Variable | Standard | Beschreibung |
|----------|----------|--------------|
| `QUEUE_CONNECTION` | `redis` | Queue-Treiber (`redis`, `database`, `sync`) |
| `HORIZON_PREFIX` | `pim_horizon:` | Redis-Key-Präfix für Laravel Horizon |

## Session

| Variable | Standard | Beschreibung |
|----------|----------|--------------|
| `SESSION_DRIVER` | `redis` | Session-Backend (`redis`, `file`, `database`) |
| `SESSION_LIFETIME` | `120` | Session-Timeout in Minuten |
| `SESSION_SECURE_COOKIE` | `false` | In Produktion `true` setzen (erfordert HTTPS) |

## Authentifizierung & CORS

| Variable | Standard | Beschreibung |
|----------|----------|--------------|
| `SANCTUM_STATEFUL_DOMAINS` | `localhost,...` | Komma-getrennte Domains für Cookie-basierte Auth |
| `FRONTEND_URL` | `http://localhost:3000` | Frontend-Origin für CORS-Header |

## Dateisystem

| Variable | Standard | Beschreibung |
|----------|----------|--------------|
| `FILESYSTEM_DISK` | `local` | Standard-Storage-Disk (`local`, `s3`) |

## E-Mail

| Variable | Standard | Beschreibung |
|----------|----------|--------------|
| `MAIL_MAILER` | `log` | Mail-Treiber. `log` für Entwicklung, `smtp` für Produktion |
| `MAIL_HOST` | — | SMTP-Server |
| `MAIL_PORT` | `587` | SMTP-Port (587 für TLS, 465 für SSL) |
| `MAIL_USERNAME` | — | SMTP-Benutzername |
| `MAIL_PASSWORD` | — | SMTP-Passwort |
| `MAIL_ENCRYPTION` | `tls` | Verschlüsselung (`tls` oder `ssl`) |
| `MAIL_FROM_ADDRESS` | — | Standard-Absender-E-Mail |
| `MAIL_FROM_NAME` | `${APP_NAME}` | Standard-Absendername |

## Translation Memory Service (TMS)

| Variable | Standard | Beschreibung |
|----------|----------|--------------|
| `TMS_ENABLED` | `false` | TMS-Integration aktivieren |
| `TMS_BASE_URL` | `http://localhost:8001/api` | TMS-API-Endpunkt |
| `TMS_API_KEY` | — | Gemeinsamer API-Schlüssel (muss mit TMS übereinstimmen) |
| `TMS_TIMEOUT` | `5` | HTTP-Timeout in Sekunden |
| `TMS_TARGET_LANGUAGES` | `en,fr,es,it,nl` | Komma-getrennte Zielsprachen |

### TMS-Service-Konfiguration (tms/.env)

| Variable | Standard | Beschreibung |
|----------|----------|--------------|
| `TMS_PROVIDER_CHAIN` | `deepl,google` | Übersetzungsanbieter-Kette (Fallback-Reihenfolge) |
| `TMS_CACHE_TTL` | `86400` | Cache-Dauer für Übersetzungen (24 Stunden) |
| `DEEPL_API_KEY` | — | DeepL-API-Schlüssel |
| `DEEPL_API_URL` | `https://api-free.deepl.com/v2` | DeepL-Endpunkt (`api.deepl.com` für Pro) |
| `GOOGLE_TRANSLATE_API_KEY` | — | Google-Translate-API-Schlüssel |
| `ANTHROPIC_API_KEY` | — | Anthropic-API-Schlüssel für Claude-Übersetzungen |
| `ANTHROPIC_MODEL` | `claude-sonnet-4-6` | Claude-Modell für KI-Übersetzungen |

## SSO (Azure AD / Entra ID)

| Variable | Standard | Beschreibung |
|----------|----------|--------------|
| `SSO_ENABLED` | `false` | Azure AD Single Sign-On aktivieren |
| `SSO_AUTO_PROVISION` | `true` | Benutzerkonten beim ersten SSO-Login automatisch anlegen |
| `SSO_DEFAULT_ROLE` | `Viewer` | Standardrolle für automatisch erstellte Benutzer |
| `AZURE_AD_CLIENT_ID` | — | Azure AD Application (Client) ID |
| `AZURE_AD_CLIENT_SECRET` | — | Azure AD Client Secret |
| `AZURE_AD_TENANT_ID` | — | Azure AD Tenant ID |
| `AZURE_AD_REDIRECT_URI` | `${APP_URL}/api/v1/auth/sso/callback` | OAuth-Callback-URL |

## Lizenz

| Variable | Standard | Beschreibung |
|----------|----------|--------------|
| `ANYPIM_LICENSE_PUBLIC_KEY` | — | Ed25519-Public-Key für Lizenz-Signaturprüfung |
| `ANYPIM_LICENSE_KEY` | — | Fallback-Lizenzschlüssel (DB-Schlüssel hat Vorrang) |

## Deployment

| Variable | Standard | Beschreibung |
|----------|----------|--------------|
| `DEPLOY_USER` | — | Systembenutzer der Projektdateien. Leer = automatische Erkennung |

## Produktions-Checkliste

1. `APP_ENV=production` und `APP_DEBUG=false`
2. `LOG_LEVEL=warning`
3. `SESSION_SECURE_COOKIE=true` (HTTPS erforderlich)
4. `MAIL_MAILER=smtp` mit gültigen SMTP-Daten
5. `SANCTUM_STATEFUL_DOMAINS` = Produktions-Domain
6. `FRONTEND_URL` = Produktions-Frontend-URL
7. `APP_KEY` gesetzt (`php artisan key:generate`)
8. `DB_PASSWORD` und `REDIS_PASSWORD` sind sichere Werte
9. Alle API-Keys sind Produktions-Schlüssel
10. `AZURE_AD_*` Variablen gesetzt falls SSO aktiv

# Sicherheits-Audit — Juli 2026

Architektur-Audit des anyPIM (Laravel 11). Ziel: die Daten im PIM absichern.
Untersucht wurden AuthN/AuthZ, Injection/RCE, öffentliche Endpoints/Datenexposition
sowie Secrets/Uploads/SSRF.

Legende Status: **behoben** = in diesem Branch gefixt · **offen** = erfordert
Ops-/Produktentscheidung (siehe Begründung).

## Kritisch

| ID | Fund | Ort | Status |
|----|------|-----|--------|
| K-1 | Privilege-Escalation: jeder Nutzer konnte sich per `PUT /users/{eigeneId}` mit `role_ids` selbst zum Admin machen (`UserPolicy::update()` erlaubt Self-Update bedingungslos). | `UserPolicy.php:44`, `UserController.php` | **behoben** |
| K-2 | `v1/debug/*` ohne jede Auth: App-Logs (Stacktraces/SQL/PII) anonym lesbar; Logs per **GET** löschbar (Anti-Forensik). | `routes/api.php`, `DebugController.php` | **behoben** |
| K-3 | Second-Order-SQL-Injection im PQL-Score-Ausdruck: Suchbegriff per String-Interpolation in `selectRaw()` ohne Bindings. | `PqlSqlGenerator.php:405,430` | **behoben** |
| K-4 | Default-Admin `admin@publixx.com` / `password` bei jeder Installation (auch Production) angelegt. | `AdminUserSeeder.php`, `setup.sh` | **behoben** |

### Fixes im Detail
- **K-1:** Neuer `UserController::guardRoleAssignment()` — Rollen-Sync erfordert
  `users.edit`, verbietet Änderung der EIGENEN Rollen (Self-Escalation) und die
  Vergabe der Admin-Rolle durch Nicht-Admins. In `store()` und `update()` angewandt.
- **K-2:** Debug-Routen unter `auth:sanctum` + `hasRole('Admin')`; die
  GET-Löschroute entfernt (Löschen nur noch per DELETE).
- **K-3:** `scoreExpressions` tragen jetzt `['sql' => '… AGAINST(? …) * ?', 'bindings' => [$term, $boost]]`;
  Ausführung via `selectRaw($sql, $bindings)`. `$ftColumn` bleibt (feste Whitelist).
- **K-4:** In Production werden keine trivialen Default-Konten mehr erzeugt; ein
  Admin entsteht dort nur aus `INITIAL_ADMIN_EMAIL` / `INITIAL_ADMIN_PASSWORD`.

## Hoch

| ID | Fund | Ort | Status |
|----|------|-----|--------|
| H-1 | Alle Medien liegen auf dem `public`-Disk (Symlink `public/storage`) und sind am Framework vorbei unter `/storage/media/<name>` abrufbar → umgeht Login-Gate & Restriktionen. | `config/filesystems.php` | **offen (Ops)** |
| H-2 | `catalog/media/{filename}` lag außerhalb `catalog.access` und lieferte jedes Medium ohne Restriktions-Check (Bypass zu `MediaController::serve`). | `CatalogController.php` | **behoben** |
| H-3 | MCP-Endpoint: ein globaler Static-Token = Voll-Schreibzugriff; Token im URL-Pfad (`/mcp/{urlToken}`). | `McpController.php` | **offen (Design)** |
| H-4 | OfflineCatalog akzeptierte `?token=` ohne Ablauf-/Scope-Prüfung (`findToken()` prüft beides nicht). | `OfflineCatalogController.php` | **behoben** |
| H-5 | SSRF-Guard per Redirect/DNS-Rebinding umgehbar (IP nur einmal vor dem Fetch geprüft). | `MediaController.php` | **behoben** |
| H-6 | SVG-Upload → Stored XSS (inline als `image/svg+xml` ausgeliefert). | `MediaController.php`, `CatalogController.php` | **behoben** |
| H-7 | Hierarchie-Restriktion nur schreibend erzwungen; Lesen (`view`/Suche) ignoriert sie → IDOR. | `ProductPolicy.php:35` | **offen (Produkt)** |
| H-8 | Document-Portal ohne `status='active'`-Filter → unveröffentlichte Entwürfe + Dokument-URLs öffentlich. | `DocumentPortalController.php` | **behoben** |

### Fixes im Detail
- **H-2:** `CatalogController::isRestrictionSensitiveMedia()` blockt
  restriktionsbehaftete Medien mit 403.
- **H-4:** `resolveUser()` prüft nun Ablauf (`expires_at` + `sanctum.expiration`)
  und verlangt die `*`-Ability (scoped Embed-Token abgelehnt).
- **H-5:** `fetchExternalUrl()` deaktiviert unkontrollierte Redirects und prüft
  jeden Redirect-Hop erneut gegen interne/private Netze.
- **H-6:** Script-fähige MIME-Typen (SVG/HTML/XML) werden mit
  `Content-Disposition: attachment` + `X-Content-Type-Options: nosniff`
  ausgeliefert. `<img>`-Einbettung bleibt funktionsfähig, Direktnavigation lädt herunter.
- **H-8:** `where('status','active')` in Suche und Dokumentenabruf.

### Offene HOCH-Punkte (bewusst nicht blind geändert)
- **H-1 (Public-Disk):** Vollständige Behebung erfordert einen privaten Disk +
  Auslieferung ausschließlich über die kontrollierten Controller-Routen inkl.
  Migration bestehender Dateien und nginx/Symlink-Anpassung — eine Ops-koordinierte
  Änderung, kein reiner Code-Fix. Bis dahin bleibt die Direkt-URL-Exposition bestehen;
  H-2/H-6 mindern den kontrollierten Pfad.
- **H-3 (MCP-Token):** Der Token im URL-Pfad ist für den claude.ai Custom Connector
  nötig (kein Header-Feld). Empfehlung: Pro-Client-Tokens mit Sanctum-Abilities,
  Schreib-Tools hinter Read-only-Default — verändert das Integrationsverhalten und
  braucht eine Design-Entscheidung.
- **H-7 (Read-Restriktion):** Es existiert kein `products.view:node-*`-Berechtigungs­modell;
  „alles lesbar" ist möglicherweise beabsichtigt. Eine Read-Trennung müsste als
  globaler Query-Scope über Liste/Suche/MCP eingeführt werden (Produktentscheidung).

## Mittel

| ID | Fund | Status |
|----|------|--------|
| M-1 | SSO-Account-Takeover durch ungeprüftes E-Mail-Matching. | **behoben** |
| M-2 | SSO liefert Sanctum-Token in der URL-Query (Leak über History/Referer/Logs). | **offen (Frontend)** |
| M-3 | `LicenseGeneratorController` & OfflineCatalog-Admin-Aktionen ohne Rollencheck. | **behoben** |
| M-4 | `DatabaseViewer`/`DatabaseConsistency` nur durch `viewAny(User)` gated. | **behoben** |
| M-5 | Login-Ratelimit nur pro IP, kein Account-Lockout. | **behoben** |
| M-6 | Health-Endpoint legt `app.debug`/Driver/Pfade offen. | **behoben** |

### Fixes im Detail
- **M-1:** `SsoController::isEmailTrusted()` — Verknüpfung/Provisionierung nur für
  Domains aus `SSO_ALLOWED_DOMAINS` und wenn `email_verified` nicht explizit false ist.
- **M-3:** Admin-Guard (`hasRole('Admin')`) in `LicenseGeneratorController` (alle
  Methoden) und `OfflineCatalogController` (generate/cancel/build/cleanup + token-basierte
  Download-/Preview-Routen).
- **M-4:** `DatabaseViewerController` Admin-only; Auth-/Token-/Session-Tabellen komplett
  gesperrt; sensible Spalten (`password`, `*_token`, `secret`) maskiert. `DatabaseConsistency::fix`
  (destruktiv) Admin-only.
- **M-5:** `AuthController::login` zählt Fehlversuche pro E-Mail (5 in 15 Min → 429),
  zusätzlich zum bestehenden IP-Throttle.
- **M-6:** Health-Detailfelder nur für authentifizierte Admins; anonym nur Status.

### Offene MITTEL-Punkte
- **M-2 (SSO-Token in URL):** Sichere Übergabe (einmaliger Exchange-Code oder httpOnly-Cookie
  statt Query-Parameter) erfordert eine koordinierte Frontend-Anpassung.
- Der Share-Unlock-Endpoint (`web.php`) trägt bereits `throttle:10,1`; der ursprünglich
  vermutete fehlende Throttle besteht nicht.

## Proaktive Angriffs-Erkennung (SecurityMonitor / „Guard")

Zusätzlich zu den behobenen Findings wurde ein Erkennungs-Guard eingebaut, der
verdächtige Muster erkennt, protokolliert und harte Signale blockiert.

- **Middleware** `DetectSuspiciousActivity` (global auf allen API-Requests).
- **Service** `App\Services\Security\SecurityMonitor` mit Heuristiken:
  - Login-Fehlversuche / Brute-Force (Hook in `AuthController`),
  - Bot-User-Agents auf sensiblen Pfaden — nur **unauthentifiziert** (legitime
    Token-Integrationen werden nie geblockt),
  - 4xx-Enumeration/Scanner-Bursts pro Client-IP (temporärer Block via Cooldown),
  - Geo-Anomalien über den Cloudflare-Header `CF-IPCountry` (Allow-/Blocklist).
- **Persistenz** in `security_events` (Model + Job `RecordSecurityEvent`),
  dedizierter Log-Channel `security`, E-Mail-Alarm an Admins (`SecurityAlertNotification`).
- **Konfiguration** über `config/security.php` (env) und die GUI unter
  **System → Guard** (`SecurityGuardController`, Setting-Group `security_guard`):
  Ein/Aus, Blockieren, Länder-Listen, Alarm-Schwelle, 4xx-Schwelle. Admin-only.
- Client-IP wird hinter Proxies aus `CF-Connecting-IP` ermittelt.

### Geo-Erkennung (Länder-Blocking)

Das Herkunftsland wird in dieser Reihenfolge bestimmt:
1. **CDN-/Proxy-Header** — Cloudflare (`CF-IPCountry`), CloudFront, Vercel, Fastly,
   Google App Engine u. a. Ohne CDN kommt kein solcher Header an.
2. **MaxMind GeoLite2** (`App\Services\Security\GeoIpResolver`) — lokale IP→Land-Auflösung
   ohne CDN. Benötigt die `.mmdb`-Datei:
   ```
   MAXMIND_LICENSE_KEY=…   # kostenloser MaxMind-Account
   php artisan pim:geoip-update
   ```
   Datei liegt unter `storage/app/geoip/GeoLite2-Country.mmdb` (gitignored).

Ist keine Quelle verfügbar, bleibt das Land „unbekannt" und die Geo-Regeln greifen
nicht — die Guard-UI zeigt das aktuell erkannte Land und warnt entsprechend.

**Whitelist vs. Blacklist:** Eine gesetzte **Whitelist ist exklusiv** — alles, was
nicht darauf steht, gilt als verdächtig (auch nicht explizit geblockte Länder).
Nur eine **Blacklist** (ohne Whitelist) lässt alle nicht gelisteten Länder durch.
Hinweis: Ländercodes sind ISO-3166 alpha-2 (z. B. `GB` für Großbritannien — `EN`
ist die Sprache Englisch und trifft nie zu).

## Als sicher bestätigt (Auszug)

ArtisanCockpit/Deployment/TestRunner (Whitelist + `escapeshellarg` + Admin),
BMEcat-XML (`LIBXML_NONET`, kein `NOENT` → kein XXE), Mass-Assignment
(`User::$fillable` ohne `role`, `password` gehasht), Embed-Asset-Pfade (Allowlist),
SFTP-/Connector-Credentials (`encrypted`-Cast), Upload-Sanitizing & MIME aus Inhalt,
AccessLink-Redeem (Single-Use, Admin-Sperre, Lock), CORS (kein Wildcard-mit-Credentials).

# CMS-Integration (Content → Integration → Typo 3)

Self-Service-Anleitung + Werkzeuge, mit denen eine Agentur den anyPIM-Produktkatalog
**ohne iFrame und ohne separates Fenster** in eine beliebige externe Website einbettet.
Der Menüpunkt heißt aktuell noch „Typo 3", das Feature selbst ist aber vollständig
CMS-agnostisch — TYPO3, WordPress, Drupal oder ein Eigenbau-CMS funktionieren identisch,
solange die Seite ein `<script>`-Tag und ein paar `<div>`s einbinden kann.

## Grundprinzip: Widget-API statt JSON-API

Das Herzstück ist das bestehende `catalog-embed`-JS-Bundle (`catalog-embed/src/`,
gebaut nach `public/catalog-embed-assets/`). Die Website bindet das Script ein,
platziert leere `<div data-catalog="…">`-Platzhalter — und jedes Widget (Suche,
Facetten, Produktraster, Detail-Overlay, Vergleich, Merkliste …) rendert sich
selbst und holt seine Daten **live** über die REST-API. Die Website bekommt nie
rohes JSON zu sehen, nur fertiges, interaktives UI:

```
Externe Website (TYPO3/WordPress/…)
  ├─ <script src=".../catalog-embed.umd.js">
  ├─ <div data-catalog="search|facets|product-grid|…">   ← Platzhalter
  └─ PublixxCatalog.init({ api, token?, locale, perPage })
             │
             ▼  live REST-Calls bei jedem Seitenaufruf
      /api/v1/catalog/{products,categories,facets,settings}
             │
             ▼
        anyPIM-Instanz (diese Codebase)
```

Wichtig für die Einordnung: In der gesamten Codebase gibt es **kein echtes SSR**.
`/catalog-embed/{slug}`, `/portal/{slug}` und `/site/{nav}` liefern alle nur eine
HTML-Hülle, die dasselbe Vue-Bundle client-seitig lädt — der Katalog ist immer
live (nicht vorgerendert), aber nie serverseitig gerendertes HTML zum bloßen
Reinziehen ohne JS-Ausführung.

## Die drei Betriebsmodi

| Modus | Wann | Wie |
|---|---|---|
| **CORS** | Getrennte Domains (Katalog-API auf `ihre-domain.de`, Website auf `www.kunde.de`) | Kunden-Domain wird als erlaubte CORS-Origin freigeschaltet (dynamisch, kein Deploy nötig) |
| **Reverse-Proxy** | Eine Domain, IT will die PIM-Domain nicht extern exponieren | Website-Webserver reicht einen Pfad (`/pim-api/…`) an anyPIM durch — kein CORS nötig |
| **API Designer (headless)** | Kein Widget, eigenes JSON/GraphQL-Schema für serverseitiges Rendering | Nutzt ein bestehendes API-Designer-Profil (`/api-streams/{slug}`), kein Facetten-/Suche-Support |

Modus 1+2 nutzen dasselbe `catalog-embed`-Widget, nur mit unterschiedlicher
API-Basis-URL (absolute Domain vs. relativer Proxy-Pfad). Modus 3 ist strukturell
etwas anderes — siehe `docs/features/` bzw. `publixx-katalog`-Skill für den
API-Designer selbst.

## Backend-Bausteine

| Datei | Zweck |
|---|---|
| `config/license.php` | Enterprise-Modul `typo3` (Name, Beschreibung — erscheint automatisch in Lizenz-Generator & Modul-Übersicht) |
| `app/Http/Controllers/Api/V1/SettingController.php` | `typo3Integration()` / `updateTypo3Integration()` (GET/PUT Betriebsmodus), `generateTypo3IntegrationEmbedToken()` / `revokeTypo3IntegrationEmbedToken()` (Service-Token), `typo3IntegrationStarterKit()` (ZIP-Download) |
| `app/Models/Setting.php` | Generischer `group`/`payload`-Store; Gruppe `typo3_integration` hält die gesamte Konfiguration (siehe unten) |
| `app/Providers/AppServiceProvider.php` | `applyTypo3IntegrationCors()` — schaltet im Modus „cors" die konfigurierte Origin dynamisch in `config('cors.allowed_origins')` frei, nur wenn Modul lizenziert |
| `app/Http/Middleware/RestrictScopedApiToken.php` | Globale Middleware: Sanctum-Tokens ohne `*`-Fähigkeit dürfen nur `api/v1/catalog/*` erreichen |
| `app/Http/Middleware/CacheCatalogResponse.php` | TTL-Caching der öffentlichen Katalog-Lese-Endpunkte |
| `routes/api.php` | Routen unter `module:typo3` (Settings/Starter-Kit/Embed-Token) sowie `catalog.cache`/`catalog.access`-Gruppierung in der `v1/catalog`-Sektion |
| `bootstrap/app.php` | Middleware-Aliase (`catalog.cache`, `catalog.access`, `module`) + globale Registrierung von `RestrictScopedApiToken` |

### Setting-Payload `typo3_integration`

```jsonc
{
  "mode": "cors" | "reverse_proxy" | "api_designer",
  "cors_origin": "https://www.kunde.de",        // nur Modus "cors"
  "reverse_proxy_path": "/pim-api",              // nur Modus "reverse_proxy", rein informativ
  "api_template_id": "uuid",                     // nur Modus "api_designer"
  "catalog_template_id": "uuid",                 // optional, alle Modi — siehe Katalog-Vorlage unten
  "embed_user_id": "uuid",                       // optional — Basis für den Service-Token
  "embed_token_id": "uuid",                      // Sanctum personal_access_tokens.id (zum Widerrufen)
  "embed_token": "212|abc...def"                 // Klartext-Token, bewusst persistiert (siehe Sicherheitsmodell)
}
```

## Frontend

| Datei | Zweck |
|---|---|
| `pim-frontend/src/views/content/Typo3IntegrationView.vue` | Die komplette Readme-/Konfigurationsseite (Betriebsmodus, Katalog-Vorlage, Starter-Kit, Service-Token, Voraussetzungen, Schritt-für-Schritt-Code, Widget-Tabelle) |
| `pim-frontend/src/components/layout/AppSidebar.vue` | Menügruppe „Integration" im Content-Bereich; Gruppe blendet sich automatisch aus, wenn kein Modul der Gruppe lizenziert ist (bestehender Cascading-Filter, kein neuer Code nötig) |
| `pim-frontend/src/router/index.js` | Route `/content/integration/typo3` |
| `catalog-embed/examples/basic.html` | Fallback-Vorlage für Starter-Kit & `/catalog-embed/{slug}`, wenn keine Katalog-Vorlage gewählt ist |

## Sicherheitsmodell: Embed-Service-Token

Problem: Ein Sanctum-Token für einen beliebigen Benutzer gewährt standardmäßig
`['*']` — alle Fähigkeiten dieses Benutzers, nicht nur Katalog-Lesezugriff. Ein
Token, der im öffentlichen Quelltext einer externen Website landet (unvermeidlich
bei client-seitigem `PublixxCatalog.init({ token })`), wäre damit ein Generalschlüssel.

Lösung: `generateTypo3IntegrationEmbedToken()` erzeugt den Token mit der
Fähigkeit `['catalog:read']` statt `['*']`. Die globale Middleware
`RestrictScopedApiToken` (registriert in `bootstrap/app.php`, läuft auf *jeder*
API-Route) prüft: hat der aktuelle Sanctum-Token **nicht** die `*`-Fähigkeit, ist
er auf `api/v1/catalog/*` beschränkt — unabhängig davon, welche PIM-Rolle der
zugrunde liegende Benutzer eigentlich hat. Normale Session-Logins
(`TransientToken`) sind davon nicht betroffen, nur echte Personal-Access-Tokens.

Der Token wird bewusst im Klartext im `Setting`-Payload gespeichert (nicht nur
gehasht wie sonst bei API-Keys üblich) — er muss jederzeit erneut in Code-Beispiele
und das Starter-Kit-ZIP eingebettet werden können, ohne bei jedem Download einen
neuen Token zu erzeugen (das würde bereits deployte Embeds brechen).

## Performance: TTL-Caching

Bis zu dieser Session gab es **kein Caching** für die öffentlichen Katalog-Lese-
Endpunkte, obwohl Redis als Cache-Store bereits konfiguriert war. `CacheCatalogResponse`
cached jetzt Body + relevante Header (`X-Total-Count`, `X-Category-Counts`, …) von
`products`, `categories`, `facets`, `attribute-groups`, `settings` — Cache-Key aus
vollständiger URL inkl. Query-String. Warenkorb, PDF-/Excel-Export und Vergleich
bleiben bewusst außen vor (session-/request-spezifisch).

TTL kommt aus dem bereits vorher in `config/cache.php` reservierten, aber nie
genutzten Wert `cache.ttl.product_list` (Default 300s, `CACHE_TTL_PRODUCT_LIST`
env-Override). Reine Zeit-Ablauf, keine Invalidierung bei Datenänderungen (erste
Ausbaustufe — Änderungen sind spätestens nach Ablauf der TTL sichtbar).

## Starter-Kit & Katalog-Vorlage

`GET /settings/typo3-integration/starter-kit` liefert ein ZIP mit
`catalog-embed.umd.js`, `catalog-embed.css`, einer lauffähigen `index.html`
(API-URL bereits auf die eigene Instanz gesetzt, Embed-Token eingefügt falls
konfiguriert) und einer `README.txt`. Ist im Setting eine `catalog_template_id`
hinterlegt (Auswahl aus den bestehenden Katalog-Vorlagen, `/catalog-templates`),
wird deren `html_template` als Basis genutzt statt der generischen `basic.html` —
Branding und Layout entsprechen dann der echten Vorlage. Dieselbe Auswahl speist
auch die `css_variables` im Theming-Code-Beispiel (Schritt 4) der Readme-Seite.

## Bekannte Stolperfalle: fehlendes Layout-CSS

`catalog-embed.css` stylt nur das **Innenleben** jedes `data-catalog`-Widgets
(Buttons, Karten, Facetten) — nicht die Anordnung der Widgets zueinander
(Sidebar/Hauptbereich/Facetten-Panel als Grid). Diese Anordnung steckt in einem
separaten `<style>`-Block in `basic.html`, der beim reinen Kopieren der
`data-catalog`-Divs (Schritt 2 der Readme) leicht übersehen wird — Ergebnis:
alle Widgets stapeln sich einfach als normale Blockelemente untereinander.

Zusätzliche Falle: `basic.html` verwendet generische Klassennamen (`layout`,
`sidebar`, `main`, `header`) sowie ein `<main class="main">` für den
Hauptbereich — kollidiert mit dem `<main>`-Landmark der Host-Seite (ungültiges
verschachteltes HTML) und mit deren eigenen, oft gleichnamigen CSS-Klassen.

**Praxisfall:** Bei der Einbindung auf `incoxx.com` wurde genau das sichtbar —
Suche/Kategorien/Produktraster liefen alle einspaltig untereinander statt im
Sidebar-Grid. Fix: Layout-CSS ergänzen und Wrapper-Klassen auf `pxc-catalog-*`
präfixen (Grid/Sidebar/Facetten-CSS + Markup siehe Chat-Verlauf vom 2026-07-23).

**Offen:** `basic.html` und der Starter-Kit-Fallback selbst sollten auf
präfixierte Klassennamen umgestellt werden, damit das nicht erneut passiert —
noch nicht umgesetzt.

## Offene Punkte

- Umbenennung „Typo 3" → „CMS-Integration" (Menü, Modulname, Routen, Dateiname
  `Typo3IntegrationView.vue`) — inhaltlich entschieden, technisch noch nicht
  durchgeführt.
- `basic.html` / Starter-Kit auf präfixierte CSS-Klassen umstellen (s. o.).
- Mehrere CORS-Origins gleichzeitig (aktuell nur eine Origin pro Instanz).
- Cache-Invalidierung bei Produkt-/Preisänderungen statt reiner TTL (bewusst
  zurückgestellt, siehe Abschnitt Performance).

# 23 · Content-Caching (Website-Vorschau & öffentliche Seiten)

Konzept für das Caching der gerenderten Content-Ausgaben (Sitemap, Content-Seite,
Produkt-Detailseite). Ziel: die teuren Public-Reads (Produktauflösung via
`MappingResolver` + Eager Loads pro Produkt) auf einen Redis-Treffer reduzieren –
**ohne** dass Redakteure veraltete Inhalte sehen.

---

## 1. Was wird gecacht?

Gecacht werden ausschließlich die **gerenderten JSON-Ausgaben** des
`WebsitePreviewService` (nicht einzelne Teilobjekte – die Komposition ist teuer):

| Methode | Cache-Einheit | Schlüssel-Dimensionen |
|---------|---------------|----------------------|
| `buildSitemap()` | Navigationsbaum + Theme | `navigation`, `lang` |
| `buildPage()` / `resolvePageBySlug()` | Seite inkl. aufgelöster Sektionen/Produkte | `navigation`, `slug`, `lang` |
| `buildProductPage()` | Produkt-Detailseite (Hero, Specs, Zubehör) | `product`, `lang` |

**Nicht** gecacht: Admin-CRUD, Authoring-Endpoints, alles hinter Auth.

---

## 2. Store & Strategie

- **Store:** Redis (`CACHE_STORE=redis`, bereits Default) → **Tag-Support**.
- **Pattern:** `Cache::tags([...])->remember($key, $ttl, fn () => …)` – exakt das
  Muster, das `AttributeValueResolver` & Co. bereits verwenden.
- **Fallback ohne Tags** (file/array, z. B. Tests): zentraler Versions-Key
  (`content:cache_version`), der bei jeder Änderung erhöht wird und in jeden
  Cache-Key einfließt. So funktioniert Invalidierung auch ohne Tag-Store
  (grobgranular). Eine kleine Abstraktion (`ContentCache`) kapselt beides.

```php
// app/Services/Content/ContentCache.php (neu)
public function remember(string $key, array $tags, ?int $ttl, Closure $cb): mixed
{
    if (! config('content.cache.enabled')) {
        return $cb();
    }
    $store = Cache::supportsTags() ? Cache::tags($tags) : Cache::store();
    $fullKey = Cache::supportsTags() ? $key : $key . ':v' . $this->version();
    return $store->remember($fullKey, $ttl ?? config('content.cache.ttl'), $cb);
}
```

---

## 3. Cache-Keys & Tags

### Keys (sprach- und mandantenbewusst)

```
content:sitemap:{navId}:{lang}
content:page:{navId}:{pageId}:{lang}
content:product:{productId}:{lang}
```

> Mehrmandanten: Redis-`prefix` (bestehende Konvention) trennt Mandanten bereits;
> ansonsten `{tenantId}` als zusätzliche Dimension voranstellen.

### Tags (mehrfach pro Eintrag – das ist der Kern der Invalidierung)

| Tag | Bedeutung | Flush durch |
|-----|-----------|-------------|
| `content` | globaler Content-Namespace | Notbremse / Deploy |
| `content:nav:{navId}` | Sitemap + alle Seiten dieser Navigation | Navigation/Knoten/Theme geändert |
| `content:page:{pageId}` | eine konkrete Seite | Seite/Sektion geändert |
| `product:{productId}` | **bereits vorhanden** | Produkt/Preis/Medien/Attribut geändert |

**Trick (automatische Produkt-Invalidierung):** Beim Cachen einer Seite/Produktseite
werden **alle eingebetteten Produkt-IDs** als zusätzliche Tags angehängt:

```php
$tags = ['content', "content:nav:{$navId}", "content:page:{$pageId}"];
foreach ($embeddedProductIds as $pid) {
    $tags[] = "product:{$pid}";
}
```

Damit flushen die **bestehenden** Observer (`ProductObserver`,
`AttributeValueObserver`, `HierarchyNodeObserver`), die heute schon
`Cache::tags(['product:'.$id])->flush()` aufrufen, automatisch auch die
Content-Seiten, die dieses Produkt einbetten – **ohne Zusatzlogik**.

---

## 4. Invalidierung (Event-getrieben)

Neue Observer (analog zu den bestehenden) flushen gezielt:

| Modell | Event | Flush |
|--------|-------|-------|
| `ContentPage` | saved/deleted | `content:page:{id}` + `content:nav:*` (Slug→Nav-Zuordnung) |
| `ContentSection` | saved/moved/deleted | `content:page:{content_page_id}` |
| `Navigation` | saved/deleted (inkl. `theme_json`) | `content:nav:{id}` |
| `NavigationNode` | saved/moved/deleted | `content:nav:{navigation_id}` |
| `ProductWidget` | saved/deleted | `content` (Widget kann in vielen Seiten stecken) |
| `Product`/`Price`/`AttributeValue`/`Media`/`Relation` | — | **bereits** via `product:{id}` (s. o.) |

> Für die `ContentPage` lässt sich die betroffene Navigation günstig über die
> verweisenden `navigation_nodes` ermitteln; im Zweifel `content:nav`-weit flushen
> (günstig, da Sitemaps klein sind).

### Gültigkeitsfenster (`valid_from` / `valid_to`)

Seiten haben Gültigkeitszeiträume (`isCurrentlyValid()`). Da kein Event feuert,
wenn eine Seite per Zeitgrenze gültig/ungültig wird, wird die **TTL gedeckelt**:

```php
$ttl = min(
    config('content.cache.ttl'),              // z. B. 3600 s
    $secondsUntilNextValidityBoundary ?? PHP_INT_MAX
);
```

So „kippt" eine geplante Seite spätestens zur nächsten `valid_*`-Grenze, ohne
auf einen Scheduler angewiesen zu sein. (Optional Phase 3: präziser Scheduler-Flush.)

---

## 5. Redakteur-Bypass (kein Stale im Backend)

Admin-Vorschau (`WebsitePreviewView`) und öffentliche Seite (`PublicSiteView`)
nutzen **dieselben** `/site/*`-Endpoints. Deshalb wird der Cache **nur für
anonyme** Anfragen genutzt:

```php
$bypass = Auth::check() || $request->boolean('nocache');
```

- **Eingeloggter Redakteur** → immer Live-Render (sofortige Kontrolle nach Edit).
- **Anonymer Besucher** → Cache-Treffer.
- `?nocache=1` erzwingt frisches Rendern (Debug/Preview-Button im Backend).

Antwort-Header zur Diagnose: `X-Cache: HIT | MISS | BYPASS`.

---

## 6. HTTP-/CDN-Ebene (Phase 2)

Zusätzlich zum Server-Cache erhalten die **öffentlichen** Antworten HTTP-Caching
(konsistent zu `CatalogController`/`MediaController`, die bereits
`Cache-Control: public, max-age=…` setzen):

```
Cache-Control: public, max-age=300, stale-while-revalidate=600
ETag: "<hash aus content:cache_version + key>"
```

- **ETag** aus Versions-Key/Inhalts-Hash → `304 Not Modified` bei unveränderten
  Seiten (spart Transfer).
- Ermöglicht vorgelagertes **CDN/Reverse-Proxy** (Cloudflare, Varnish) → Render
  trifft den Origin nur noch bei Invalidierung.
- Für eingeloggte/`nocache`-Anfragen: `Cache-Control: private, no-store`.

---

## 7. Cache-Warming (optional, Phase 3)

Nach „Veröffentlichen" einer Seite die wichtigsten Ausgaben vorrendern (analog
zum vorhandenen `WarmupCache`-Job):

- Job `WarmContentCache(navId, lang[])` rendert Sitemap + alle gültigen Seiten.
- Artisan:
  - `php artisan pim:content-cache-warm [--nav=] [--lang=de,en]`
  - `php artisan pim:content-cache-clear [--nav=] [--page=] [--product=]`

So ist die erste Besucheranfrage nach einer Veröffentlichung bereits ein HIT.

---

## 8. Konfiguration

```php
// config/content.php (neu) – oder Abschnitt in bestehender Config
'cache' => [
    'enabled' => env('CONTENT_CACHE_ENABLED', true),
    'ttl'     => env('CONTENT_CACHE_TTL', 3600),       // Server-Cache (s)
    'http_max_age' => env('CONTENT_CACHE_HTTP_MAX_AGE', 300),
    'warm_on_publish' => env('CONTENT_CACHE_WARM', false),
],
```

`enabled=false` → vollständiger Bypass (Verhalten wie heute).

---

## 9. Umsetzung in Phasen

| Phase | Inhalt | Risiko |
|-------|--------|--------|
| **1 ✅ umgesetzt** | `ContentCache`-Service (Tag- + Versions-Fallback, Reverse-Index), `remember()` in den drei Service-Methoden, Redakteur-Bypass (`Auth`/`?nocache`), Content-Observer (page/section/nav/node/widget) + Produkt-Observer (Product/Price/AttributeValue/MediaAssignment/Relation → PDP + einbettende Seiten), gültigkeits-bewusste TTL, `X-Cache`-Header (HIT/MISS/BYPASS), `config/content.php` | niedrig |
| **2 ✅ umgesetzt** | HTTP `ETag` + `Cache-Control: public, max-age, stale-while-revalidate` + `304 Not Modified` (If-None-Match) für anonyme Anfragen; `private, no-store` bei Auth/`nocache` (CDN-fähig) | mittel |
| **3 ✅ umgesetzt** | `ContentCacheWarmer` + `WarmContentCache`-Job + `pim:content-cache-warm`/`-clear` (gezielt: `--nav`/`--page`/`--product`, `--stats`), Hit-Rate-Metriken, optionales `warm_on_publish` | optional |

---

## 10. Testbarkeit & Betrieb

- **Tests:** `CACHE_STORE=array` (kein Tag-Support) → Versions-Key-Fallback greift,
  Funktionalität bleibt prüfbar; zusätzlich ein Redis-Tag-Test (Hit/Invalidate).
- **Korrektheit vor Geschwindigkeit:** Im Zweifel breiter flushen (`content:nav`/
  `content`) – Sitemaps/Seiten sind günstig zu rendern, ein Stale-Bug wäre teurer.
- **Beobachtbarkeit:** `X-Cache`-Header + optionaler Hit-Rate-Zähler
  (`Cache::increment('content:metrics:hit|miss')`).

---

## 11. Risiken & Entscheidungen

| Thema | Entscheidung |
|-------|-------------|
| Stale im Backend | Vermieden durch Auth-Bypass (Redakteur immer live) |
| Produktänderung → Seite stale | Vermieden durch eingebettete `product:{id}`-Tags (bestehende Observer flushen mit) |
| Geplante Seiten (`valid_*`) | TTL auf nächste Zeitgrenze gedeckelt |
| Store ohne Tags | Versions-Key-Fallback (grob, aber korrekt) |
| PQL-`product-list` (Live-Auflösung folgt) | Beim Aktivieren: entweder kurze TTL oder Tag `content` beim Render anhängen |

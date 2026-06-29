# 22 — Strukturierter Content (CMS-Modul)

> **Status:** Konzept / Spezifikation
> **Modul:** `content` (Enterprise, lizenzpflichtig)
> **Ziel:** anyPIM wird vom reinen Produktdaten-System zum vollständigen
> **Web- & Print-Publishing-System**. Neben Produkten verwaltet anyPIM
> strukturierte Inhalte (Teaserseiten, Kapitelseiten, Lösungsseiten,
> Impressum …), bildet daraus über einen **Navigationsbaum** eine **Sitemap**
> ab und kann eine komplette **Unternehmens-Webseite** als Preview rendern und
> strukturiert exportieren/importieren (Web **und** Print).

---

## 1. Leitidee

Strukturierter Content funktioniert **exakt wie Produkte** — nur dass nicht ein
physisches Produkt, sondern eine **Seite** das zentrale Objekt ist. Wir
übernehmen das bewährte anyPIM-Muster 1:1:

| Produkt-Welt | Content-Welt | Bedeutung |
|--------------|--------------|-----------|
| `ProductType` | **`ContentType`** | Typ-Definition: welche Bausteine sind erlaubt (Teaserseite, Kapitelseite …) |
| `Product` | **`ContentPage`** | konkrete Instanz (eine Seite) |
| Attribut-Werte (EAV) | **`ContentSection`** (Block-Instanzen) | strukturierte Bausteine je Seite |
| `Attribute` / `data_type` | **`SectionType`** + Feld-Schema | konfigurierbare Baustein-Typen (Headline, Teaser, Video …) |
| `Hierarchy` (`master`/`output`) | **`Navigation`** (`hierarchy_type = 'navigation'`) | Navigationsbaum = Sitemap |
| `OutputHierarchyProductAssignment` | **`NavigationNode → ContentPage / Produktkategorie`** | Verknüpfung Baum ↔ Inhalt |
| `MappingResolver` / `DatasetBuilder` | **`ContentMappingResolver`** | Export-Serialisierung (Web/Print) |
| `ProductPreviewService` | **`WebsitePreviewService`** | gerenderte Vorschau |

**Konsequenz:** Wer Produkte in anyPIM versteht, versteht Content sofort. Wir
erben Versionierung, Workflow, i18n, Media-Anbindung, Such-Indexierung,
Import/Export und Rechte-/Lizenzlogik aus dem bestehenden System.

---

## 2. Die drei Bausteine des Konzepts

```
┌──────────────────────────────────────────────────────────────────────┐
│  A) CONTENT (Seiten + Sektionen)                                       │
│     ContentType  →  ContentPage  →  [ContentSection, ContentSection …]  │
│     (Schema)         (Instanz)        (typisierte, sortierte Blöcke)    │
└───────────────────────────────┬──────────────────────────────────────┘
                                │  Verlinkung (bidirektional)
                                ▼
┌──────────────────────────────────────────────────────────────────────┐
│  B) VERKNÜPFUNG                                                         │
│     ContentPage ↔ Product            (Seite zeigt Produkte)            │
│     ContentPage ↔ HierarchyNode      (Kategorie hat redaktionellen     │
│                                        Intro-Content)                  │
│     ContentSection ↔ Product/Liste   (Block referenziert Produkte/PQL) │
└───────────────────────────────┬──────────────────────────────────────┘
                                │  Strukturierung
                                ▼
┌──────────────────────────────────────────────────────────────────────┐
│  C) NAVIGATIONSBAUM = SITEMAP                                          │
│     Navigation (Tree)                                                   │
│     └─ NavigationNode (folder | content_page | product_category |      │
│                         product | external_url)                        │
│        → ergibt URL-Struktur, Menü, Breadcrumbs, Sitemap.xml           │
└──────────────────────────────────────────────────────────────────────┘
                                │  Ausgabe
                                ▼
        WebsitePreviewService  →  Web-Vorschau (Intro, Produkte, Lösungen, Impressum)
        ContentFormatExporter  →  Web-JSON  /  Print-Export (InDesign, PDF, Katalog)
```

---

## 3. Datenmodell

Alle IDs sind UUIDs, `declare(strict_types=1)`, i18n analog zum bestehenden
Muster (`name_de`/`name_en` + `name_json`, bzw. EAV-`language`-Spalte).

### 3.1 `content_types` — Seitentyp (analog `product_types`)

Definiert, **welche Sektionstypen** auf einer Seite dieses Typs erlaubt und
welche standardmäßig vorbelegt sind.

```sql
CREATE TABLE content_types (
    id                     UUID PRIMARY KEY,
    technical_name         VARCHAR(100) UNIQUE,   -- 'teaser-page', 'chapter-page', 'imprint'
    name_de                VARCHAR(255),
    name_en                VARCHAR(255),
    name_json              JSON NULL,
    description            TEXT NULL,
    icon                   VARCHAR(50) NULL,       -- Lucide-Icon (wie ProductType)
    color                  VARCHAR(7) NULL,        -- Hex
    allowed_section_types  JSON NULL,              -- ['headline','teaser','text','media','video','link','product-list', ...]
    default_sections       JSON NULL,              -- Vorlage: [{section_type:'headline'}, {section_type:'teaser'} ...]
    layout_hint            VARCHAR(50) NULL,       -- 'landing' | 'article' | 'legal' | 'overview'
    workflow_id            UUID NULL REFERENCES workflows(id),
    is_active              BOOLEAN DEFAULT TRUE,
    sort_order             INT DEFAULT 0,
    created_at TIMESTAMP, updated_at TIMESTAMP
);
```

Beispiel-Typen: `teaser-page` (Teaserseite), `chapter-page` (Kapitelseite),
`solution-page` (Lösungsseite), `landing-page`, `imprint` (Impressum),
`privacy` (Datenschutz), `contact`, `blog-article`.

### 3.2 `section_types` — Baustein-Typ (analog `attributes` + `data_type`)

Der **konfigurierbare Kern**: Jeder Sektionstyp definiert über ein **Feld-Schema**,
welche Felder der Block hat. Damit ist „strukturiert je Typ konfigurierbar"
erfüllt — die Redaktion pflegt Felder, nicht Freitext-HTML.

```sql
CREATE TABLE section_types (
    id                UUID PRIMARY KEY,
    technical_name    VARCHAR(100) UNIQUE,   -- 'headline','subline','teaser','text','media','video','link','product-list'
    name_de           VARCHAR(255),
    name_en           VARCHAR(255),
    name_json         JSON NULL,
    icon              VARCHAR(50) NULL,
    category          VARCHAR(50) NULL,      -- 'text' | 'media' | 'commerce' | 'layout'
    schema            JSON,                  -- Feld-Definitionen (siehe unten)
    is_repeatable     BOOLEAN DEFAULT FALSE, -- Block mehrfach pro Seite?
    is_nestable       BOOLEAN DEFAULT FALSE, -- darf Kind-Sektionen enthalten (Spalten/Grid)?
    preview_component VARCHAR(100) NULL,     -- Vue-Komponentenname für Web-Preview
    is_active         BOOLEAN DEFAULT TRUE,
    sort_order        INT DEFAULT 0,
    created_at TIMESTAMP, updated_at TIMESTAMP
);
```

**Feld-Schema (`schema` JSON)** — wiederverwendet bewusst die `data_type`-Enum
der Attribute, damit Validierung, i18n und Renderer geteilt werden können:

```json
{
  "fields": [
    {"key": "headline",  "label": "Überschrift",  "type": "String",   "translatable": true, "required": true},
    {"key": "level",     "label": "Ebene",        "type": "Selection", "value_list": "heading-levels", "default": "h2"},
    {"key": "body",      "label": "Fließtext",    "type": "RichText",  "translatable": true},
    {"key": "image",     "label": "Bild",         "type": "Media",     "media_usage": "teaser"},
    {"key": "video_url", "label": "Video",        "type": "Link",      "link_kind": "VideoLink"},
    {"key": "cta",       "label": "Button",       "type": "Link",      "link_kind": "Hyperlink"}
  ]
}
```

> **Designentscheidung — JSON-Schema statt globaler Attribute:** Sektionsfelder
> sind block-lokal und page-builder-typisch (Reihenfolge, Wiederholung, Nesting).
> Sie als globale `attributes` zu führen würde die Attribut-Registry überladen.
> Wir verwenden daher ein **leichtgewichtiges Feld-Schema**, das die
> `data_type`-Enum, Value-Lists, Units und Link-Kinds der Attribut-Welt
> **wiederverwendet** (gleiche Renderer, gleiche i18n-Logik), ohne eine Zeile in
> `attributes` zu erzeugen. Selection-Felder dürfen auf bestehende
> `value_lists` verweisen.

**Vordefinierte Sektionstypen (Seed):**

| `technical_name` | Felder (Auszug) | Kategorie |
|------------------|-----------------|-----------|
| `headline` | headline, level | text |
| `subline` | text | text |
| `teaser` | headline, body, image, cta | text |
| `text` | body (RichText) | text |
| `quote` | text, author | text |
| `image` | image, alt, caption | media |
| `gallery` | images[] (media_array) | media |
| `video` | video_url, poster, caption | media |
| `download` | file (PdfLink), label | media |
| `link` | label, url, target | layout |
| `cta-banner` | headline, body, cta, background | layout |
| `columns` | (nestable) | layout |
| `accordion` | items[] (nestable) | layout |
| `product-list` | source (PQL/Liste), layout, limit | commerce |
| `product-teaser` | product_ref, variant | commerce |
| `category-teaser` | hierarchy_node_ref | commerce |
| `spacer` / `divider` | size | layout |

### 3.3 `content_pages` — Seite (analog `products`)

```sql
CREATE TABLE content_pages (
    id                         UUID PRIMARY KEY,
    content_type_id            UUID REFERENCES content_types(id),  -- required
    slug                       VARCHAR(255),         -- URL-Segment, eindeutig je Navigation/Sprache
    title                      VARCHAR(500),
    status                     ENUM('draft','active','inactive','archived') DEFAULT 'draft',
    -- Navigation / Sitemap
    navigation_node_id         UUID NULL REFERENCES navigation_nodes(id),  -- primäre Position im Baum
    -- SEO
    seo_title_json             JSON NULL,            -- {de,en}
    seo_description_json       JSON NULL,
    seo_image_id               UUID NULL REFERENCES media(id),
    -- Workflow / Versionierung (wie Product)
    workflow_id                UUID NULL REFERENCES workflows(id),
    current_workflow_status_id UUID NULL REFERENCES workflow_statuses(id),
    workflow_assignee_id       UUID NULL,
    created_by                 UUID NULL,
    updated_by                 UUID NULL,
    created_at TIMESTAMP, updated_at TIMESTAMP
);
```

`content_page_versions` wird **1:1 wie `product_versions`** angelegt (Snapshot-
JSON, `status` draft/scheduled/active/archived, `publish_at`/`published_at`) —
so erbt Content geplante Publikation und Historie.

### 3.4 `content_sections` — Block-Instanz (analog `product_attribute_values`)

```sql
CREATE TABLE content_sections (
    id                 UUID PRIMARY KEY,
    content_page_id    UUID REFERENCES content_pages(id) ON DELETE CASCADE,
    section_type_id    UUID REFERENCES section_types(id),
    parent_section_id  UUID NULL REFERENCES content_sections(id),  -- für nestable (Spalten/Accordion)
    sort_order         INT DEFAULT 0,
    is_visible         BOOLEAN DEFAULT TRUE,
    values_json        JSON,        -- Feldwerte je Sprache: {"de": {...}, "en": {...}}, sprachneutrale Felder unter "_"
    settings_json      JSON NULL,   -- Layout/Style: {background, padding, anchor, css_class}
    created_at TIMESTAMP, updated_at TIMESTAMP,
    INDEX (content_page_id, sort_order),
    INDEX (parent_section_id, sort_order)
);
```

> **i18n in `values_json`:** Übersetzbare Felder liegen unter dem Sprachschlüssel
> (`values_json.de.headline`), sprachneutrale unter `_` (`values_json._.image`).
> Das spiegelt die `language`-Spalten-Logik der EAV-Welt, hält aber einen Block
> als eine Zeile zusammen (block-lokale Atomarität, einfache Sortierung/Drag&Drop).
> **Suchindexierung** (Meilisearch, Spec 19): beim Speichern werden Textfelder
> pro Sprache flach extrahiert und mit indexiert.

### 3.5 Verknüpfungen Content ↔ Produkt ↔ Hierarchie

```sql
-- Seite ↔ Produkt (z. B. Lösungsseite zeigt zugehörige Produkte)
CREATE TABLE content_page_product_links (
    id               UUID PRIMARY KEY,
    content_page_id  UUID REFERENCES content_pages(id) ON DELETE CASCADE,
    product_id       UUID REFERENCES products(id) ON DELETE CASCADE,
    role             VARCHAR(50) DEFAULT 'related',  -- 'featured','related','accessory'
    sort_order       INT DEFAULT 0,
    UNIQUE (content_page_id, product_id, role)
);

-- Hierarchieknoten ↔ Seite (Kategorie bekommt redaktionellen Intro-Content)
CREATE TABLE hierarchy_node_content_links (
    id                 UUID PRIMARY KEY,
    hierarchy_node_id  UUID REFERENCES hierarchy_nodes(id) ON DELETE CASCADE,
    content_page_id    UUID REFERENCES content_pages(id) ON DELETE CASCADE,
    role               VARCHAR(50) DEFAULT 'intro',  -- 'intro','footer','sidebar'
    UNIQUE (hierarchy_node_id, content_page_id, role)
);
```

Eine `product-list`-Sektion referenziert Produkte **dynamisch** (PQL-Query, Spec 03)
oder **statisch** (Produktliste). Statische Listen nutzen
`content_page_product_links`; dynamische speichern die PQL im `values_json` der
Sektion und werden zur Render-/Exportzeit aufgelöst.

### 3.6 Navigationsbaum / Sitemap — `navigations` + `navigation_nodes`

**Das Herzstück.** Wir spiegeln das `hierarchies`/`hierarchy_nodes`-Muster
(Materialized Path, `sort_order`, Tree), erweitern es aber um einen **Ziel-Typ**,
sodass ein Knoten auf Content **oder** Produktkategorie **oder** Produkt **oder**
externen Link zeigen kann. Das ergibt die perfekte Verzahnung von Content und
Produkten in **einer** Sitemap.

```sql
CREATE TABLE navigations (
    id              UUID PRIMARY KEY,
    technical_name  VARCHAR(100) UNIQUE,  -- 'main', 'footer', 'mobile'
    name_de VARCHAR(255), name_en VARCHAR(255), name_json JSON NULL,
    locale_set      JSON NULL,            -- ['de','en']
    is_primary      BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP, updated_at TIMESTAMP
);

CREATE TABLE navigation_nodes (
    id               UUID PRIMARY KEY,
    navigation_id    UUID REFERENCES navigations(id) ON DELETE CASCADE,
    parent_node_id   UUID NULL REFERENCES navigation_nodes(id),
    path             VARCHAR(1000),   -- Materialized Path (wie hierarchy_nodes)
    depth            INT DEFAULT 0,
    sort_order       INT DEFAULT 0,
    -- Anzeige
    label_de VARCHAR(255), label_en VARCHAR(255), label_json JSON NULL,
    slug             VARCHAR(255),     -- URL-Segment dieses Knotens
    icon             VARCHAR(50) NULL,
    is_visible       BOOLEAN DEFAULT TRUE,  -- im Menü sichtbar (vs. nur in Sitemap)
    -- Ziel (Polymorphie)
    target_type      ENUM('folder','content_page','product_category','product','external_url') DEFAULT 'folder',
    content_page_id  UUID NULL REFERENCES content_pages(id),
    hierarchy_node_id UUID NULL REFERENCES hierarchy_nodes(id),  -- für 'product_category'
    product_id       UUID NULL REFERENCES products(id),
    external_url     VARCHAR(1000) NULL,
    created_at TIMESTAMP, updated_at TIMESTAMP,
    INDEX (navigation_id, parent_node_id),
    INDEX (path(255))
);
```

**Ziel-Typen erklärt:**

| `target_type` | bedeutet | Ausgabe-URL |
|---------------|----------|-------------|
| `folder` | reiner Menüpunkt/Gruppe, keine eigene Seite | `/loesungen/` |
| `content_page` | rendert eine Content-Seite | `/loesungen/smart-home` |
| `product_category` | rendert Produktliste eines Hierarchieknotens (+ optional verknüpfter Intro-Content via `hierarchy_node_content_links`) | `/produkte/werkzeuge` |
| `product` | Deeplink auf eine Produktdetailseite | `/produkte/werkzeuge/akkubohrer-x1` |
| `external_url` | externer Link (z. B. Shop, Social) | `https://…` |

So entsteht eine durchgängige Sitemap, in der **Intro-Page, Produkte, Lösungen
und Impressum** nebeneinander hängen — exakt das Zielbild.

```
Navigation "main"
├─ Home                     (content_page  → teaser-page)
├─ Produkte                 (product_category → Master-Wurzel)
│  ├─ Werkzeuge             (product_category → Knoten "Werkzeuge")
│  └─ Maschinen             (product_category → Knoten "Maschinen")
├─ Lösungen                 (folder)
│  ├─ Smart Home            (content_page → solution-page, verlinkt 12 Produkte)
│  └─ Industrie 4.0         (content_page → solution-page)
├─ Über uns                 (content_page → chapter-page)
└─ Rechtliches              (folder)
   ├─ Impressum             (content_page → imprint)
   └─ Datenschutz           (content_page → privacy)
```

---

## 4. Export & Import (Web + Print)

Content nutzt **dieselbe Export-Pipeline** wie Produkte und folgt der
verpflichtenden **5-Dateien-Konvention** (siehe
`.claude/rules/export-format-conventions.md`).

### 4.1 `ContentMappingResolver` / `ContentElementMap`

Analog zu `MappingResolver` lösen Mapping-Regeln Sektionsfelder auf flache
`target`-Felder auf. Neue Source-Namespaces:

| Prefix | Beispiel | Bedeutung |
|--------|----------|-----------|
| `section:` | `section:teaser.headline` | Feldwert einer Sektion nach Typ |
| `page:` | `page:title`, `page:slug` | Seiten-Stammdaten |
| `nav:` | `nav:path`, `nav:label` | Navigationskontext |
| `linked:` | `linked:products` | verknüpfte Produkte (→ via bestehender Produkt-MappingResolver) |

`ContentElementMap::TARGET_FIELDS` definiert das Zielschema je Ausgabeformat
(z. B. Web-JSON: `title`, `slug`, `sections`, `seo`, `breadcrumb`).

### 4.2 Ausgabeformate

| Format | Writer (vorhanden) | Zweck |
|--------|--------------------|-------|
| **Web-JSON** | `JsonWriter` (flat/nested/publixx) | Headless-Frontend / SPA-Vorschau |
| **Sitemap.xml** | `XmlWriter` | Suchmaschinen / Navigationsexport |
| **Print-Export** | `XmlWriter` / `JsonWriter` | InDesign-/Katalog-Strecken (Content + Produkte gemischt) |
| **PDF** | `PdfTemplateRenderer` (Spec 14) | Direkt-PDF einer Seite/eines Kapitels |
| **HTML-Bundle** | `OfflineCatalogExportService` (erweitert) | statische Website als ZIP |

**Print-Ziel:** Da der Navigationsbaum eine geordnete Sitemap liefert und Seiten
strukturierte Sektionen plus verknüpfte Produkte enthalten, kann der gesamte Baum
als **lineares Print-Dokument** (Kapitel → Seiten → Produktstrecken) exportiert
werden — die Brücke zwischen Web und Print.

### 4.3 Import

Erweiterung der bestehenden 3-Phasen-Pipeline (Spec 06, `ImportService`):
neue Sheets/Sektionen `Content-Typen`, `Sektionstypen`, `Seiten`, `Sektionen`,
`Navigation`. Ein `ContentJsonFormatImporter` (analog `JsonFormatImporter`)
erlaubt Roundtrip Export→Import. Validierung über `SheetValidator` +
`ReferenceResolver` (Auflösung von `content_type`, `section_type`, `slug`).

---

## 5. Vorschau / Webseiten-Rendering

`WebsitePreviewService` (analog `ProductPreviewService`) baut die
**vollständige Seitenstruktur** als JSON:

```php
buildPage(NavigationNode $node, string $lang): array
// → { breadcrumb, navigation, page: { title, sections: [...] }, products: [...], seo }

buildSitemap(Navigation $nav, string $lang): array
// → kompletter Baum für Menü + sitemap.xml
```

- **`content_page`-Knoten:** Sektionen werden über das Feld-Schema gerendert
  (RichText, Media-URLs, aufgelöste Links), verknüpfte Produkte über den
  bestehenden `ProductPreviewService` eingebettet.
- **`product_category`-Knoten:** Produktliste des Hierarchieknotens (+ optionaler
  Intro-Content via `hierarchy_node_content_links`).

**Öffentliche Routen** (analog Public-Catalog, ohne Auth, `public: true`):

```
GET /api/v1/site/{navigation}/sitemap        → kompletter Baum
GET /api/v1/site/{navigation}/page/{slug}     → gerenderte Seite (JSON)
GET /api/v1/site/{navigation}/sitemap.xml     → SEO-Sitemap
```

Im Frontend rendert eine `WebsitePreviewView` Menü (aus Navigation), Seite und
eingebettete Produktkacheln — die „Webseiten-Vorschau" mit Intro-Page,
Produkten, Lösungen und Impressum.

---

## 6. Menü- & Frontend-Integration

### 6.1 Sidebar (`AppSidebar.vue`)

Neue Einträge, gated über `module: 'content'` + Permissions
(`isModuleAccessible` / `authStore.hasPermission`):

```js
// In Sektion 'daily' (redaktionelle Arbeit), nach Hierarchien:
{ icon: FileStack,  label: () => t('nav.content'),    to: '/content',    module: 'content', permission: 'content.view',    testid: 'nav-content' },
{ icon: Network,    label: () => t('nav.navigation'), to: '/navigation', module: 'content', permission: 'navigation.view', testid: 'nav-navigation' },

// In Sektion 'publish':
{ icon: Globe, label: () => t('nav.websitePreview'), to: '/website-preview', module: 'content', permission: 'content.view' },

// In Sektion 'config' (Strukturpflege), als aufklappbare Gruppe:
{ key: 'grp-content-config', icon: Settings2, label: () => t('nav.contentConfig'), module: 'content', children: [
    { label: () => 'Content-Typen',   to: '/content-types',  permission: 'content-types.manage' },
    { label: () => 'Sektions-Typen',  to: '/section-types',  permission: 'section-types.manage' },
]},
```

### 6.2 Router (`router/index.js`)

```js
{ path: '/content',           name: 'content',          component: () => import('@/views/content/ContentListView.vue'),   meta: { title: 'Content' } },
{ path: '/content/:id',       name: 'content-detail',   component: () => import('@/views/content/ContentDetailView.vue'), meta: { title: 'Seite', tabable: true, tabTitle: 'Seite' } },
{ path: '/navigation',        name: 'navigation',       component: () => import('@/views/navigation/NavigationView.vue'), meta: { title: 'Navigation' } },
{ path: '/content-types',     name: 'content-types',    component: () => import('@/views/content/ContentTypeView.vue'),   meta: { title: 'Content-Typen' } },
{ path: '/section-types',     name: 'section-types',    component: () => import('@/views/content/SectionTypeView.vue'),   meta: { title: 'Sektions-Typen' } },
{ path: '/website-preview',   name: 'website-preview',  component: () => import('@/views/site/WebsitePreviewView.vue'),   meta: { title: 'Website-Vorschau' } },
// öffentlich:
{ path: '/site/:nav/:slug?',  name: 'public-site',      component: () => import('@/views/site/PublicSiteView.vue'),       meta: { title: 'Website', public: true } },
```

### 6.3 Komponenten-Wiederverwendung

| Bestehend | Wiederverwendung für Content |
|-----------|------------------------------|
| `PimTree.vue` (Drag&Drop, Materialized Path) | **`NavigationView`** — Sitemap-Editor: Knoten anlegen/verschieben, Ziel-Typ wählen |
| `ProductListView.vue` (PimTable, Filter, Spalten) | **`ContentListView`** — Seitenliste (Typ, Status, Navigation, Workflow) |
| `ProductDetailView.vue` (Tab-Layout) | **`ContentDetailView`** — Tabs: *Sektionen*, *Verknüpfte Produkte*, *SEO*, *Versionen*, *Workflow*, *Vorschau* |
| `HierarchyFormPanel.vue` (Slide-Panel) | Anlegen/Bearbeiten von Typen, Knoten, Sektionen |
| Stores-Muster (`products.js`) | `stores/content.js`, `stores/navigation.js` (items/current/meta/filters) |
| `api/hierarchies.js`-Muster | `api/content.js`, `api/navigation.js` |

**Sektions-Editor** (Tab „Sektionen" in `ContentDetailView`): Block-Liste mit
Drag&Drop-Sortierung (`sort_order`), „Block hinzufügen" beschränkt auf
`content_type.allowed_section_types`, je Block ein schema-getriebenes Formular
(dieselben Feld-Renderer wie die Attribut-Eingabe). Live-Vorschau-Spalte rendert
`section_type.preview_component`.

---

## 7. API-Endpunkte (`routes/api.php`)

```php
// Enterprise: Strukturierter Content
Route::middleware('module:content')->group(function () {
    // Struktur
    Route::apiResource('content-types', ContentTypeController::class);
    Route::apiResource('section-types', SectionTypeController::class);

    // Seiten
    Route::apiResource('content-pages', ContentPageController::class);
    Route::post('content-pages/{page}/sections',        [ContentSectionController::class, 'store']);
    Route::put('content-sections/{section}',            [ContentSectionController::class, 'update']);
    Route::put('content-sections/{section}/move',       [ContentSectionController::class, 'move']);
    Route::post('content-pages/{page}/links/products',  [ContentLinkController::class, 'attachProducts']);

    // Navigation / Sitemap
    Route::apiResource('navigations', NavigationController::class);
    Route::get('navigations/{nav}/tree',                [NavigationController::class, 'tree']);
    Route::post('navigations/{nav}/nodes',              [NavigationNodeController::class, 'store']);
    Route::put('navigation-nodes/{node}/move',          [NavigationNodeController::class, 'move']);

    // Export
    Route::post('content-export',                       [ContentExportController::class, 'export']);
});

// Öffentlich (Website-Vorschau, ohne Auth — analog Catalog)
Route::prefix('site')->group(function () {
    Route::get('{navigation}/sitemap',     [PublicSiteController::class, 'sitemap']);
    Route::get('{navigation}/sitemap.xml', [PublicSiteController::class, 'sitemapXml']);
    Route::get('{navigation}/page/{slug}', [PublicSiteController::class, 'page']);
});
```

CLI analog Konvention: `php artisan pim:content-export --navigation= --format=`.

---

## 8. Rechte & Lizenz

- **Modul:** `content` (über `CheckModuleLicense` / `licenseStore.isModuleActive`).
- **Permissions:** `content.view`, `content.edit`, `content-types.manage`,
  `section-types.manage`, `navigation.view`, `navigation.edit`.
- **Tab-Access** je Rolle wie bei Produkten (`getTabAccess`): z. B. Redakteur =
  `write` auf *Sektionen*, `read` auf *SEO*.
- **Workflow** (Spec 13) gilt für Seiten identisch (Entwurf → Review → Freigabe).

---

## 9. Umsetzung in Phasen

| Phase | Inhalt | Ergebnis |
|-------|--------|----------|
| **1 — Fundament** | Migrationen + Models (`content_types`, `section_types`, `content_pages`, `content_sections`), Seeds für Standard-Sektionstypen, CRUD-API, `stores/content.js` | Seiten mit Sektionen anlegen & bearbeiten |
| **2 — Sektions-Editor** | `ContentDetailView` Tab „Sektionen", schema-getriebene Feld-Renderer (Reuse Attribut-Inputs), Drag&Drop, i18n | Redaktionelle Pflege wie im CMS |
| **3 — Navigation/Sitemap** | `navigations`/`navigation_nodes`, `NavigationView` (Reuse `PimTree`), Ziel-Typen, Verknüpfung Content↔Produkt↔Hierarchie | Vollständige Sitemap |
| **4 — Vorschau** | `WebsitePreviewService`, öffentliche `site`-Routen, `WebsitePreviewView` | Klickbare Webseiten-Vorschau (Intro, Produkte, Lösungen, Impressum) |
| **5 — Export/Import** | `ContentMappingResolver`/`ContentElementMap`/`ContentFormatExporter`, Sitemap.xml, Print-Export, Import-Sheets | Web- & Print-Publishing, Roundtrip |
| **6 — Feinschliff** | Such-Index (Spec 19), Versionierung/Scheduling (Spec 13), KI-Textvorschläge (optional) | Produktionsreife |

---

## 10. Zusammenfassung

- Content = Produkte-Muster, übertragen auf **Seiten** → minimale Lernkurve,
  maximale Wiederverwendung (Versionierung, Workflow, i18n, Media, Such-Index,
  Import/Export, Rechte/Lizenz).
- **Konfigurierbare Sektionstypen** über Feld-Schema = strukturiertes CMS je Typ.
- **Navigationsbaum mit polymorphen Ziel-Typen** = eine durchgängige **Sitemap**,
  in der Content-Seiten, Produktkategorien, Produkte und externe Links nebeneinander
  hängen → perfektes Zusammenspiel Content ↔ Produkte.
- **WebsitePreviewService + öffentliche Routen** = echte Webseiten-Vorschau.
- **ContentFormatExporter** auf bestehender Pipeline = strukturierter Export/Import
  für **Web und Print**.

Damit kann anyPIM eine vollständige **Unternehmens-Webseite** abbilden — von der
Intro-Page über Produkt- und Lösungsseiten bis zum Impressum — und denselben
strukturierten Content zusätzlich für den Print-Kanal ausgeben.

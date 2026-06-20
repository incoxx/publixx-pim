# Konzept: Rollenbasiertes Cockpit ("Fokus-Modus")

> **Status:** Konzept / Entwurf
> **Datum:** 2026-06-20
> **Ziel:** Eine fokussierte Single-Page als Einstieg, die die wichtigsten Funktionen
> rollenabhängig bündelt – ohne den Nutzer durch das überladene Hauptmenü zu zwingen.

---

## 1. Ausgangslage

### 1.1 Das Problem
Das anyPIM ist mit Menüpunkten überladen: **7 Hauptsektionen mit ~40–50 Einträgen**
(`Daily Business`, `Publish`, `Plugins`, `Translations`, `Project`, `Configuration`,
`Administration`), teils 3–4 Ebenen tief verschachtelt
(`pim-frontend/src/components/layout/AppSidebar.vue`). Für die meisten Anwender ist
das zu viel: Ein Produktmanager oder eine Marketing-Person braucht im Alltag nur einen
Bruchteil dieser Funktionen.

### 1.2 Was bereits existiert (Wiederverwendung!)
Das Konzept baut **konsequent auf vorhandenen Bausteinen** auf — es wird kaum etwas
neu erfunden:

| Baustein | Datei | Nutzung im Cockpit |
|----------|-------|--------------------|
| Dashboard mit 6-Spalten-Grid | `views/dashboard/DashboardView.vue` | Layout-Basis |
| Widget-System (12+ Widgets) | `components/dashboard/*Widget.vue` | Cockpit-Blöcke |
| Widget-Wrapper | `components/dashboard/DashboardWidgetWrapper.vue` | Block-Rahmen |
| **Schnellzugriff** | `components/dashboard/QuickLinksWidget.vue` | Aktions-Kacheln |
| **Dashboard-Presets** (speichern/laden) | `stores/dashboard.js` → `dashboardPresetsApi` | **Rollen-Layouts** |
| Profil-Karten (KPI/Charts) | `components/dashboard/ProfileStatCard.vue` | Reports/KPIs |
| Globale (semantische) Suche | `components/layout/SemanticSearchBox.vue` | Hero-Suche |
| Schnellsuche (Tabs) | `views/search/QuickSearchView.vue` | Suchblock |
| User-Preferences-Persistenz | `userPreferencesApi.get/update(key)` | Personalisierung |
| **Rollen & Rechte** (Spatie) | `stores/auth.js`, `UserResource` | Rollensteuerung |
| Modul-Lizenzen | `licenseStore`, `module:`-Middleware | Block-Sichtbarkeit |

**Kernbefund:** Das Dashboard ist heute *nutzer*-individualisierbar (Presets pro User),
aber **nicht rollenspezifisch**. Genau diese Lücke schließt das Konzept.

### 1.3 Vorhandene Rollen
8 Rollen sind geseedet (`database/seeders/RoleAndPermissionSeeder.php`):
`Sysadmin`, `Admin`, `Data Steward`, **`Product Manager`**, `Viewer`,
`Export Manager`, `API Designer`, `Project Management`.

> ⚠️ **Eine „Marketing"-Rolle existiert noch nicht.** Sie muss als Teil dieses
> Konzepts neu angelegt werden (siehe §6.2). Die nötigen Features (Medien/DAM,
> Channels, Übersetzungen, Reports) sind aber alle bereits vorhanden.

### 1.4 Namens-Hinweis (wichtig)
Der Begriff **„Portal" ist im Code bereits belegt**: `PortalConfig` / das `portals`-Modul
liefern **öffentliche, kundenseitige** Landingpages und Catalog-Embeds
(`/portal/{slug}`). Das hier beschriebene, **interne** Single-Page-Konzept für
PIM-Anwender wird deshalb **„Cockpit"** genannt, um Verwechslungen zu vermeiden.

---

## 2. Zielbild

> **„Ein Bildschirm. Alles Wichtige. Rollengerecht."**

- Eine **fokussierte Einstiegsseite** (`/cockpit`) statt Menü-Wühlerei.
- **„Fokus-Modus"** ("Idiotenmodus"): reduzierte UI, große Kacheln, prominente Suche,
  Sidebar eingeklappt/ausgeblendet.
- **Rollenabhängige Ausprägung**: Produktmanager sieht andere Blöcke als Marketing.
- **Personalisierbar obendrauf**: Admin definiert das Rollen-Standard-Layout, der
  einzelne Nutzer darf es anpassen (vorhandener Preset-Mechanismus).
- **Kein Datensilo**: Das Cockpit verlinkt/öffnet die bestehenden Detailseiten — es
  ersetzt keine Funktionen, es bündelt Zugriffe.

---

## 3. Aufbau der Cockpit-Seite

Die Seite ist in **Zonen** gegliedert. Jede Zone ist optional und je Rolle befüllt.
Technisch ist jede Zone ein vorhandenes (oder leicht erweitertes) Widget.

```
┌───────────────────────────────────────────────────────────────────┐
│  [Logo]   anyPIM Cockpit · Marketing            [Fokus-Modus ▢] [⚙] │  ← Kopf
├───────────────────────────────────────────────────────────────────┤
│                                                                     │
│   🔍  Was suchst du?  ____________________________  [Suchen]        │  ← ZONE A: Hero-Suche
│       Tabs: Produkte · Medien · Hierarchien   (semantisch)          │
│                                                                     │
├───────────────────────────────────────────────────────────────────┤
│  ZONE B: Schnellaktionen (große Kacheln, rollenspezifisch)          │
│  ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐        │
│  │ Produkt │ │ Medien  │ │ Übersetz│ │ Channel │ │ Reports │        │
│  │ anlegen │ │ Library │ │ -ungen  │ │ -Sync   │ │         │        │
│  └─────────┘ └─────────┘ └─────────┘ └─────────┘ └─────────┘        │
├───────────────────────────────────────────────────────────────────┤
│  ZONE C: Arbeitsvorrat            │  ZONE D: KPIs / Reports         │
│  • Meine Aufgaben                 │  • Datenqualität (Gauge)        │
│  • Zuletzt bearbeitet             │  • Produkt-Füllstand            │
│  • Merkliste / Watchlist          │  • Export-/Channel-Status       │
├───────────────────────────────────┼─────────────────────────────────┤
│  ZONE E: Medien & Content (Marketing-Fokus)                         │
│  • Asset-Katalog-Vorschau · neue Assets · fehlende Bilder           │
├───────────────────────────────────────────────────────────────────┤
│  ZONE F: Aktivität / Datenflüsse (Import/Export/Channel-Feed)       │
└───────────────────────────────────────────────────────────────────┘
```

### Zonen-zu-Widget-Mapping

| Zone | Inhalt | Vorhandenes Widget/Komponente |
|------|--------|-------------------------------|
| **A — Hero-Suche** | Prominente globale Suche, semantisch, Tab-Umschaltung | `SemanticSearchBox` + Logik aus `QuickSearchView` |
| **B — Schnellaktionen** | Große, rollenspezifische Aktionskacheln | Erweiterung `QuickLinksWidget` (Kachel-Variante) |
| **C — Mein Arbeitsplatz** | **Notizen**, **gepinnte Produkte (Merkliste)**, zuletzt bearbeitet, Aufgaben | `NotesWidget`, `WatchlistWidget`, `RecentlyEditedWidget`, `MyTasksWidget` |
| **D — KPIs/Reports** | Füllstand, Datenqualität, Status | `CompletenessWidget`, `DataQualityWidget`, `ProfileStatCard` |
| **E — Medien/Content** | DAM-Vorschau, fehlende Assets | **Neu** `MediaSpotlightWidget` (nutzt `AssetCatalogController`) |
| **F — Aktivität** | Aktivitäts-Feed, Datenflüsse | `ActivityFeedWidget`, `DataFlowWidget` |

→ Lediglich **Zone E (`MediaSpotlightWidget`)** ist neu. Alles andere existiert.

> **Arbeitsplatz-Charakter (Anforderung):** Das Cockpit ist nicht nur ein
> Dashboard, sondern ein **persönlicher Arbeitsplatz**. Zone C bündelt deshalb die
> bereits vorhandenen, nutzerbezogenen Funktionen:
> - **Notizen** (`NotesWidget`) – freie Notizen direkt im Cockpit.
> - **Gepinnte Produkte** = **Merkliste** (`WatchlistWidget`) – die Produkte, an
>   denen man gerade arbeitet, bleiben griffbereit.
> - **Zuletzt bearbeitet** (`RecentlyEditedWidget`) – nahtloses Weiterarbeiten.
> - **Meine Aufgaben** (`MyTasksWidget`) – offene Workflow-Aufgaben.

---

## 4. Architektur: rollenabhängige Cockpits

### 4.1 Grundidee — „Cockpit-Profile"
Ein **Cockpit-Profil** ist eine Layout-Definition (welche Zonen/Widgets, welche
Reihenfolge, welche Schnellaktionen), die einer **Rolle** zugeordnet ist. Es ist
technisch fast identisch zu den vorhandenen **Dashboard-Presets** – mit dem
entscheidenden Zusatz eines **`role_id` / `scope`-Feldes**.

**Auflösungs-Reihenfolge zur Laufzeit** (analog zur bestehenden Rechte-Logik):

```
1. Persönliches Cockpit des Nutzers           (user_preferences: 'cockpit')   → höchste Priorität
2. Rollen-Standard-Cockpit der Primärrolle    (cockpit_profiles.role_id)
3. System-Fallback ("Allgemein")              (Default im Code)
```

Damit gilt dasselbe bewährte Muster wie bei `hasPermission()`/`getTabAccess()`:
**Rolle gibt den Standard vor, der Nutzer darf personalisieren.**

> **Zwei getrennte Fragen** – wichtig auseinanderzuhalten:
> - **„Welcher Modus?"** (Cockpit *oder* klassisches Menü-GUI) → siehe §7
> - **„Welches Layout im Cockpit?"** (welche Zonen/Widgets) → diese §4
>
> Beide folgen derselben Regel **Benutzer schlägt Rolle**, werden aber getrennt
> gespeichert.

### 4.2 Empfohlene Umsetzungsvariante

| Variante | Beschreibung | Bewertung |
|----------|--------------|-----------|
| **A — Config-getrieben (EMPFOHLEN)** | Cockpit-Layout als JSON pro Rolle in DB; Admin pflegt es in der GUI; Nutzer überschreibt optional via User-Preferences | ✅ Flexibel, kein Deploy nötig für Layout-Änderung, nutzt vorhandene Preset-Infra |
| B — Hardcodiert im Frontend | Pro Rolle eine feste Layout-Konstante in Vue | ❌ Unflexibel, jede Änderung = Deploy |

**Empfehlung: Variante A.** Sie erweitert lediglich die bereits existierende
`dashboardPresetsApi`/Preset-Tabelle um eine Rollen-Bindung.

### 4.3 Sichtbarkeit der Blöcke
Jeder Block bleibt zusätzlich an die bestehenden Gates gebunden:
- **`permission`** → `authStore.hasPermission(...)` (z. B. `media.view` für Zone E)
- **`module`** → `licenseStore.isModuleActive(...)` (z. B. `translations`, `portals`)

So kann ein Rollen-Profil zwar einen Block vorsehen, dieser wird aber nur gerendert,
wenn Recht **und** Lizenz vorhanden sind. Identisch zur heutigen Sidebar-Logik
(`AppSidebar.vue` Zeilen 241–260) → konsistent und wiederverwendbar.

---

## 5. Datenmodell-Erweiterung

Minimal-invasiv: Erweiterung der bestehenden Preset-Struktur statt Neubau.

```sql
-- Erweiterung der vorhandenen Dashboard-Preset-Tabelle (oder neue, falls getrennt gewünscht)
ALTER TABLE dashboard_presets
    ADD COLUMN scope        VARCHAR(20) DEFAULT 'user',  -- 'user' | 'role' | 'system'
    ADD COLUMN role_id      CHAR(36) NULL REFERENCES roles(id),
    ADD COLUMN view_type    VARCHAR(20) DEFAULT 'dashboard'; -- 'dashboard' | 'cockpit'

-- Layout-JSON (bereits vorhandenes Format der Presets):
-- {
--   "zones": [
--     { "key": "hero-search", "enabled": true },
--     { "key": "quick-actions", "items": ["product.create","media.library","translations","channel.sync","reports"] },
--     { "key": "work-queue", "widgets": ["tasks","recent","watchlist"] },
--     { "key": "kpis", "widgets": ["completeness","quality"] },
--     { "key": "media-spotlight", "enabled": true },
--     { "key": "activity", "widgets": ["activity","dataflows"] }
--   ]
-- }

-- (b) Standard-MODUS pro Rolle (Cockpit vs. klassisches Menü-GUI)
ALTER TABLE roles
    ADD COLUMN default_view_mode VARCHAR(10) DEFAULT 'gui';  -- 'cockpit' | 'gui'

-- (c) Modus-Wunsch pro Benutzer – schlägt die Rolle (NULL = "Rolle entscheidet")
ALTER TABLE users
    ADD COLUMN view_mode VARCHAR(10) NULL;  -- 'cockpit' | 'gui' | NULL
```

- **Cockpit-Layout** (a, oben) und **Modus** (b/c) sind bewusst getrennt: *Welche
  Zonen* im Cockpit erscheinen ≠ *ob* der Nutzer überhaupt im Cockpit landet.
- `users.view_mode = NULL` bedeutet **„Rolle entscheidet"** (Default). Setzt der
  Nutzer explizit `cockpit`/`gui`, **gewinnt diese Einstellung** über die Rolle.
- Persönliche Layout-Anpassungen liegen weiterhin in `user_preferences` (Key
  `'cockpit'`), exakt wie heute `'dashboard'` und `'quick_links'`.

Beide neuen Felder werden im `UserResource` mitgeliefert (`view_mode` direkt am
User, `default_view_mode` über `roles[]`), sodass das Frontend den Start-Modus ohne
Zusatz-Request kennt — analog zu `all_permissions`/`tab_permissions`.

---

## 6. Konkrete Cockpit-Profile

### 6.1 Produktmanager-Cockpit
**Fokus:** Daten pflegen, Vollständigkeit, Workflow.

| Zone | Befüllung |
|------|-----------|
| Hero-Suche | Produkte (Standard-Tab), Hierarchien |
| Schnellaktionen | Produkt anlegen · Suche/Profisuche · Import · Hierarchien · Workflow |
| Arbeitsvorrat | Meine Aufgaben · Zuletzt bearbeitet · Merkliste |
| KPIs/Reports | Produkt-Füllstand · Datenqualität |
| Medien/Content | *(reduziert: nur „fehlende Bilder")* |
| Aktivität | Datenflüsse (Import/Export) · Aktivitäts-Feed |

**Rechte:** vorhanden (Rolle `Product Manager`: `products.*`, `media.view`,
`prices.*`, `imports.*`, `workflow.*`).

### 6.2 Marketing-Cockpit  ⭐ (Schwerpunkt der Anforderung)
**Fokus:** Content, Medien, Sprachen, Ausspielkanäle.

| Zone | Befüllung |
|------|-----------|
| Hero-Suche | Produkte + **Medien**-Tab prominent |
| Schnellaktionen | **Medien-Library** · **Asset-Katalog** · **Übersetzungs-Jobs** · **Channel-/Publixx-Sync** · **Portale/Catalog-Templates** · Reports |
| Arbeitsvorrat | Merkliste (Kampagnen-Auswahl) · Zuletzt bearbeitet · Aufgaben |
| KPIs/Reports | Füllstand **marketingrelevanter Felder** (Beschreibung, Bilder, SEO) · Datenqualität |
| Medien/Content | **Asset-Spotlight** (neue Assets, fehlende Bilder, Usage-Heatmap) |
| Aktivität | Übersetzungs-Status · Channel-/Export-Feed |

**Neue Rolle „Marketing" — ENTSCHEIDUNG (Punkt 4, 2026-06-20):**

Der Zuschnitt ist festgelegt. Marketing arbeitet content- und medienlastig, pflegt
aber keine Stammdaten/Preise und administriert nichts.

```
default_view_mode: cockpit            # Marketing startet im Cockpit

Permissions (Vollzugriff Content/Medien, sonst lesend):
  media.view, media.edit, media.create, media.delete
  products.view                       # Produkte sichten, nicht strukturell ändern
  attributes.view, hierarchies.view   # Kontext lesen
  reports.view
  watchlist.view, watchlist.edit      # Kampagnen-/Arbeitsauswahl
  export.view                         # Exporte sehen/anstoßen, nicht konfigurieren

Module (Lizenz vorausgesetzt):
  connectors  (DeepL, Canva, Cloudinary)
  translations
  portals
  catalog_templates
  reports
  publixx      # optional, falls lizenziert

Tab-Rechte (RoleTabPermission):
  media = write
  base-data = read
  attributes = read
  prices = hidden
  variants / variant-attributes = read
```

**Begründung:** Marketing braucht volle Hoheit über Assets, Texte/Übersetzungen und
Ausspielung (Portale/Kataloge/Channels), aber keine Schreibrechte auf
Produktstruktur, Preise oder Administration. Damit ist die Rolle klar von
`Product Manager` (Datenpflege) abgegrenzt und überschneidungsfrei.

Diese Rolle wird im `RoleAndPermissionSeeder` ergänzt (gleiche Mechanik wie die
8 bestehenden Rollen) — Teil von **Phase 2/3** (siehe Roadmap).

### 6.3 Weitere Profile (kostenlos „mitgenommen")
Da das System rollengetrieben ist, lassen sich später trivial weitere Cockpits
definieren: `Export Manager` (Export-Profile, Jobs, Channels), `Project Management`
(Projekt-Dashboard, Team-Workload), `Viewer` (nur Suche + lesende KPIs).

---

## 7. Modus-Umschalter Cockpit ⇄ GUI (der „Idiotenmodus")

Das PIM kennt **zwei Betriebsmodi**:

| Modus | Beschreibung |
|-------|--------------|
| **`cockpit`** ("Fokus") | Single-Page `/cockpit`, große Kacheln, reduzierte Dichte, Sidebar ausgeblendet/eingeklappt. Einstieg landet direkt im Cockpit. |
| **`gui`** ("Vollmodus") | Klassische PIM-Oberfläche mit voller Sidebar – heutiges Verhalten. |

### 7.1 Der Umschalter (gut sichtbar)
Ein **prominenter, immer sichtbarer Umschalter im App-Header** (`AppHeader.vue`),
direkt neben der globalen Suche — in **beiden** Modi vorhanden, damit man jederzeit
zurück- bzw. hinwechseln kann:

```
┌──────────────────────────────────────────────────────────────────┐
│ [Logo]   🔍 Suche …            [ ▢ Cockpit | ☰ Menü ]      [⚙][👤] │
└──────────────────────────────────────────────────────────────────┘
                                  └── Segmented-Control-Toggle ──┘
```

- Umsetzung als **Segmented Control** (zwei Buttons, aktiver Zustand hervorgehoben),
  nicht als versteckter Menüpunkt → „gut sichtbar" wie gefordert.
- Icons: `LayoutDashboard` (Cockpit) / `Menu` (GUI), mit Text-Label.
- Klick schaltet sofort um **und persistiert** die Wahl (siehe §7.3).
- Optional Tastenkürzel (z. B. `g` + `c` / `g` + `m`).

### 7.2 Wer bekommt welchen Start-Modus? (Auflösung)
Beim Login/Seitenaufruf wird der wirksame Modus so ermittelt:

```
1. users.view_mode          (Benutzereinstellung)   → wenn gesetzt: GEWINNT
2. roles[].default_view_mode (zugeordnete Rolle)     → sonst maßgeblich
3. 'gui'                     (System-Fallback)
```

> **Regel (wie gefordert): Benutzereinstellung schlägt Rolle.**
> Beispiel: Rolle *Marketing* hat `default_view_mode = 'cockpit'`. Ein Marketing-
> Nutzer startet also im Cockpit – es sei denn, er hat persönlich `gui` gewählt,
> dann startet er im Vollmodus. Bei mehreren Rollen: Primärrolle (`roles[0]`); ist
> dort `cockpit` hinterlegt, genügt das (Cockpit „gewinnt" über GUI bei Gleichstand,
> da bewusst aktiviert).

### 7.3 Persistenz des Umschaltens
- Klickt der Nutzer den Header-Umschalter, wird **`users.view_mode` gesetzt**
  (PATCH auf `/api/v1/auth/me` bzw. einen `user-settings`-Endpoint) → seine Wahl
  überlebt Sessions und schlägt fortan die Rolle.
- Ein „**Zurücksetzen auf Rollen-Standard**" (setzt `view_mode = NULL`) gehört in
  die persönlichen Einstellungen.

### 7.4 Pflege der Defaults (Admin)
- **Pro Rolle:** Feld „Standard-Ansicht (Cockpit/Menü)" in der Rollen-Verwaltung
  (`Users & Roles → Roles`). Schreibt `roles.default_view_mode`.
- **Pro Benutzer:** Feld „Ansicht" in der Benutzer-Verwaltung **und** in den eigenen
  Profileinstellungen. Schreibt `users.view_mode` (mit Option „Rolle entscheidet").

### 7.5 Technische Hinweise
- Modus-State zentral im `authStore` (abgeleitet aus `user.view_mode` /
  `userRole.default_view_mode`), Helper `effectiveViewMode`.
- `AppLayout.vue` rendert die `AppSidebar` nur, wenn `effectiveViewMode === 'gui'`;
  im Cockpit-Modus wird sie ausgeblendet (bzw. auf Icon-Leiste reduziert).
- Router-Guard: bei `cockpit` und Aufruf von `/` → Redirect auf `/cockpit`;
  bei `gui` → bestehendes `/dashboard`.

---

## 8. Technische Umsetzung (Frontend/Backend)

### Frontend
| Datei | Änderung |
|-------|----------|
| `router/index.js` | Neue Route `/cockpit` (name: `cockpit`), Permission `dashboard.view` |
| `views/cockpit/CockpitView.vue` | **Neu** – Zonen-Layout, lädt Cockpit-Profil, rendert Widgets |
| `components/cockpit/HeroSearch.vue` | **Neu** – prominente Suche (wiederverwendet `SemanticSearchBox`/QuickSearch-Logik) |
| `components/cockpit/ActionTiles.vue` | **Neu** – Kachel-Variante des `QuickLinksWidget` |
| `components/dashboard/MediaSpotlightWidget.vue` | **Neu** – Zone E (nutzt `AssetCatalogController`) |
| `stores/cockpit.js` | **Neu** – lädt Profil (Auflösung User→Rolle→System), Persistenz |
| `components/layout/AppHeader.vue` | **Modus-Umschalter Cockpit ⇄ GUI** (Segmented Control, §7.1) |
| `components/layout/AppLayout.vue` | Sidebar nur bei `effectiveViewMode === 'gui'` rendern |
| `stores/auth.js` | Erweiterung: `effectiveViewMode`, Setter für `view_mode` (§7.2/7.3) |
| `views/admin/RoleEdit.vue` / `UserEdit.vue` | Feld „Standard-Ansicht (Cockpit/Menü)" bzw. „Ansicht" |

### Backend
| Datei | Änderung |
|-------|----------|
| `database/migrations/..._cockpit_profiles.php` | Erweiterung Preset-Tabelle + `roles.default_view_mode` + `users.view_mode` (§5) |
| `app/Models/DashboardPreset.php` (o. ä.) | `scope`, `role_id`, `view_type` |
| `app/Models/Role.php` / `User.php` | `default_view_mode` / `view_mode` (fillable) |
| `app/Http/Resources/Api/V1/UserResource.php` | `view_mode` + `default_view_mode` (über `roles[]`) ausliefern |
| `app/Http/Controllers/Api/V1/...PresetController` | Endpoint: Rollen-Default-Cockpit lesen/schreiben (Admin) |
| `app/Http/Controllers/Api/V1/AuthController.php` (o. UserSettings) | PATCH `view_mode` des eigenen Users (§7.3) |
| `database/seeders/RoleAndPermissionSeeder.php` | Neue Rolle **„Marketing"** (+ `default_view_mode='cockpit'`), Default-Cockpit-Profile |

→ **Aufwand-Schwerpunkt liegt im Frontend**; Backend ist im Wesentlichen eine
Erweiterung der vorhandenen Preset-Infrastruktur.

---

## 9. Phasenplan (Roadmap)

| Phase | Umfang | Ergebnis |
|-------|--------|----------|
| **P1 — MVP** ✅ | `/cockpit`-Seite mit Zonen A–D inkl. Arbeitsplatz (Notizen + gepinnte Produkte), System-Default-Layout, Wiederverwendung vorhandener Widgets, sichtbarer Cockpit/GUI-Umschalter mit Persistenz | **Umgesetzt** (siehe §9.1) |
| **P2 — Rollen-Profile** | DB-Erweiterung, Auflösung User→Rolle→System, Profile für `Product Manager` + neue Rolle `Marketing` | Rollenabhängige Cockpits |
| **P3 — Marketing-Tiefe** | `MediaSpotlightWidget` (Zone E), marketingspezifische Füllstand-KPIs, Channel-/Übersetzungs-Status | Marketing-Fokus voll ausgebaut |
| **P4 — Admin-GUI** | Layout-Editor: Admin pflegt Rollen-Cockpits per Drag-&-Drop (nutzt vorhandenen Preset-Editor) | Self-Service ohne Deploy |

### 9.1 MVP — umgesetzte Dateien (Phase 1)
**Neu:**
- `pim-frontend/src/views/cockpit/CockpitView.vue` — Single-Page: Hero-Suche,
  Schnellaktions-Kacheln, **Arbeitsplatz** (Notizen, Merkliste/gepinnte Produkte,
  zuletzt bearbeitet, Aufgaben), KPIs (Füllstand, Datenqualität).

**Geändert:**
- `router/index.js` — Route `/cockpit`; `/` leitet modusabhängig (Cockpit/GUI) um.
- `stores/auth.js` — `viewModePref`, `roleDefaultViewMode`, `effectiveViewMode`,
  `isCockpitMode`, `setViewMode/toggleViewMode/loadViewMode` (Regel: Benutzer schlägt Rolle).
- `components/layout/AppHeader.vue` — **sichtbarer Segmented-Control-Umschalter**
  Cockpit ⇄ Menü.
- `components/layout/AppLayout.vue` — Sidebar im Cockpit-Modus ausgeblendet.
- `app/Http/Controllers/Api/V1/UserPreferenceController.php` — Persistenz der
  Modus-Wahl (`view_mode`-Gruppe).

**Bewusst noch offen (Phase 2+):** `roles.default_view_mode`-Spalte (aktuell liefert
das Frontend `'gui'` als Rollen-Fallback; die persönliche Wahl funktioniert bereits
voll), rollenspezifische Cockpit-Layouts, `MediaSpotlightWidget`, „Marketing"-Rolle.

---

## 10. Offene Entscheidungen (für Abstimmung)

> **Entscheidung (2026-06-20):** Umsetzung als **eigenständige Cockpit-View**
> (eigene Route `/cockpit`, eigenes Zonen-Layout) — nicht als Dashboard-Preset.
> → Punkt 2 ist damit geklärt. Status weiterhin Konzept; keine Implementierung
> beauftragt ("Konzept reicht erstmal").

1. ~~**Cockpit als neuer Standard-Login-Ziel?**~~ **Geklärt (2026-06-20):** Der
   Start-Modus wird über `users.view_mode` / `roles.default_view_mode` gesteuert
   (§7.2). Wer `cockpit` hat, landet auf `/cockpit`; wer `gui` hat, auf
   `/dashboard`. Ein gut sichtbarer Header-Umschalter erlaubt jederzeit den Wechsel
   (§7.1).
2. ~~**Cockpit vs. Dashboard**~~ **Geklärt:** eigenständige Cockpit-View (s. o.).
3. **Rollen-Mehrfachzuordnung:** Bei Nutzern mit mehreren Rollen – welches
   Cockpit-Layout/welcher Modus gewinnt? Vorschlag: Primärrolle (`roles[0]`);
   `users.view_mode` schlägt ohnehin alles.
4. ~~**Neue Rolle „Marketing"**~~ **Entschieden (2026-06-20):** Rechte-/Modul-/
   Tab-Zuschnitt festgelegt in §6.2 (inkl. `default_view_mode = 'cockpit'`).
   Seeding erfolgt in Phase 2/3.
5. ~~**Namensgebung**~~ **Geklärt (2026-06-20):** Der Name **„Cockpit"** ist
   bestätigt (klar abgegrenzt vom öffentlichen „Portal").

---

## Anhang: Wiederverwendungs-Quote

Von ~12 benötigten Bausteinen sind **~10 bereits vorhanden** (Suche, 9 Widgets,
Preset-Mechanik, Rollen-/Rechte-Store, Modul-Gates, User-Preferences).
**Neu zu bauen:** Cockpit-View + Zonen-Layout, `MediaSpotlightWidget`,
Action-Kachel-Variante, DB-Erweiterung + „Marketing"-Rolle. Das hält den Aufwand
gering und die Konsistenz hoch.

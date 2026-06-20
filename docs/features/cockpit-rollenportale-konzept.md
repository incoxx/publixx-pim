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
| **C — Arbeitsvorrat** | Aufgaben, zuletzt bearbeitet, Merkliste | `MyTasksWidget`, `RecentlyEditedWidget`, `WatchlistWidget` |
| **D — KPIs/Reports** | Füllstand, Datenqualität, Status | `CompletenessWidget`, `DataQualityWidget`, `ProfileStatCard` |
| **E — Medien/Content** | DAM-Vorschau, fehlende Assets | **Neu** `MediaSpotlightWidget` (nutzt `AssetCatalogController`) |
| **F — Aktivität** | Aktivitäts-Feed, Datenflüsse | `ActivityFeedWidget`, `DataFlowWidget` |

→ Lediglich **Zone E (`MediaSpotlightWidget`)** ist neu. Alles andere existiert.

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
```

Persönliche Anpassungen liegen weiterhin in `user_preferences` (Key `'cockpit'`),
exakt wie heute der Key `'dashboard'` und `'quick_links'`.

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

**Neue Rolle „Marketing" — Vorschlag Rechte/Module:**

```
Permissions:  products.view, media.view, media.edit, media.create,
              reports.view, watchlist.*, export.view
Module:       connectors (DeepL/Canva/Cloudinary), translations,
              portals, catalog_templates, reports, publixx (optional)
Tab-Rechte:   media = write, base-data = read, prices = hidden,
              attributes = read   (via RoleTabPermission)
```

Diese Rolle wird im `RoleAndPermissionSeeder` ergänzt (gleiche Mechanik wie die
8 bestehenden Rollen).

### 6.3 Weitere Profile (kostenlos „mitgenommen")
Da das System rollengetrieben ist, lassen sich später trivial weitere Cockpits
definieren: `Export Manager` (Export-Profile, Jobs, Channels), `Project Management`
(Projekt-Dashboard, Team-Workload), `Viewer` (nur Suche + lesende KPIs).

---

## 7. „Fokus-Modus" (der „Idiotenmodus")

Ein Umschalter im Kopf der Seite (und/oder als globaler Toggle, persistiert in
User-Preferences):

- **An:** Sidebar eingeklappt/ausgeblendet, große Kacheln, reduzierte Dichte,
  nur Cockpit sichtbar. Einstieg landet direkt auf `/cockpit`.
- **Aus:** volle PIM-Oberfläche mit Sidebar (heutiges Verhalten).

Technisch: Flag in `appearanceStore` / `userPreferences` + bedingtes Rendern der
`AppSidebar` in `AppLayout.vue`. Optional: pro Rolle ein **Default-Modus**
(Marketing startet z. B. standardmäßig im Fokus-Modus, Admin im Vollmodus).

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
| `components/layout/AppLayout.vue` | Fokus-Modus-Toggle (Sidebar aus) |
| `stores/auth.js` | bereits vorhanden (`userRole`, `permissions`) – keine Änderung |

### Backend
| Datei | Änderung |
|-------|----------|
| `database/migrations/..._cockpit_profiles.php` | Erweiterung Preset-Tabelle (§5) |
| `app/Models/DashboardPreset.php` (o. ä.) | `scope`, `role_id`, `view_type` |
| `app/Http/Controllers/Api/V1/...PresetController` | Endpoint: Rollen-Default-Cockpit lesen/schreiben (Admin) |
| `database/seeders/RoleAndPermissionSeeder.php` | Neue Rolle **„Marketing"** + Default-Cockpit-Profile |

→ **Aufwand-Schwerpunkt liegt im Frontend**; Backend ist im Wesentlichen eine
Erweiterung der vorhandenen Preset-Infrastruktur.

---

## 9. Phasenplan (Roadmap)

| Phase | Umfang | Ergebnis |
|-------|--------|----------|
| **P1 — MVP** | `/cockpit`-Seite mit Zonen A–D, ein **System-Default-Profil**, Wiederverwendung vorhandener Widgets, Fokus-Modus-Toggle | Funktionierende Single-Page, noch nicht rollengetrieben |
| **P2 — Rollen-Profile** | DB-Erweiterung, Auflösung User→Rolle→System, Profile für `Product Manager` + neue Rolle `Marketing` | Rollenabhängige Cockpits |
| **P3 — Marketing-Tiefe** | `MediaSpotlightWidget` (Zone E), marketingspezifische Füllstand-KPIs, Channel-/Übersetzungs-Status | Marketing-Fokus voll ausgebaut |
| **P4 — Admin-GUI** | Layout-Editor: Admin pflegt Rollen-Cockpits per Drag-&-Drop (nutzt vorhandenen Preset-Editor) | Self-Service ohne Deploy |

---

## 10. Offene Entscheidungen (für Abstimmung)

1. **Cockpit als neuer Standard-Login-Ziel?** Soll `/cockpit` die neue Startseite
   sein (für bestimmte Rollen), oder bleibt `/dashboard` Standard und Cockpit ist
   ein zusätzlicher Einstieg?
2. **Cockpit vs. Dashboard** – getrennte Views oder soll das Cockpit nur ein
   weiteres **Dashboard-Preset im „Fokus-Layout"** sein? (Weniger Code, aber
   weniger gestalterische Freiheit.)
3. **Rollen-Mehrfachzuordnung:** Bei Nutzern mit mehreren Rollen – welches Cockpit
   gewinnt? Vorschlag: Primärrolle (`roles[0]`) oder explizite Auswahl im Header.
4. **Neue Rolle „Marketing"** – Rechte-/Modul-Zuschnitt aus §6.2 so freigeben?
5. **Namensgebung:** „Cockpit" (Vorschlag) vs. „Arbeitsplatz" / „Workspace" /
   „Mein PIM" – wegen Kollision mit dem bestehenden öffentlichen „Portal".

---

## Anhang: Wiederverwendungs-Quote

Von ~12 benötigten Bausteinen sind **~10 bereits vorhanden** (Suche, 9 Widgets,
Preset-Mechanik, Rollen-/Rechte-Store, Modul-Gates, User-Preferences).
**Neu zu bauen:** Cockpit-View + Zonen-Layout, `MediaSpotlightWidget`,
Action-Kachel-Variante, DB-Erweiterung + „Marketing"-Rolle. Das hält den Aufwand
gering und die Konsistenz hoch.

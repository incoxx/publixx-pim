---
title: Katalog-Embed (Template-System)
---

# Katalog-Embed (Template-System)

Das Katalog-Embed ist ein Drop-in Widget-System, mit dem Sie einen vollwertigen Produktkatalog in jede beliebige Website einbetten können. Kunden können das Design an ihr Corporate Design anpassen, ohne Programmierkenntnisse zu benötigen.

## Funktionsweise

Das System besteht aus einer einzigen JavaScript-Datei (`catalog-embed.umd.js`) und einer CSS-Datei (`catalog-embed.css`), die in eine HTML-Seite eingebunden werden. Über das HTML-Attribut `data-catalog="..."` werden Widgets an beliebigen Stellen im HTML platziert.

```html
<!-- Widget einfügen -->
<div data-catalog="product-grid"></div>
<div data-catalog="search"></div>
<div data-catalog="pagination"></div>

<!-- Bundle laden und initialisieren -->
<script src="/catalog-embed-assets/catalog-embed.umd.js"></script>
<script>
  PublixxCatalog.init({
    api: 'https://ihre-domain.de/api/v1',
    locale: 'de',
    perPage: 24,
  })
</script>
```

## Verfügbare Widgets

Jedes Widget wird über das `data-catalog`-Attribut aktiviert:

| Widget | Attribut | Beschreibung |
|---|---|---|
| **Produktraster** | `product-grid` | Zeigt Produkte als Karten-Grid oder Liste an |
| **Suche** | `search` | Suchfeld mit Echtzeit-Suche |
| **Pagination** | `pagination` | Seitennavigation für die Produktliste |
| **Kategorien** | `categories` | Kategorie-Navigation als Baumstruktur |
| **Facetten** | `facets` | Filterpanel mit Attribut-Facetten |
| **Toolbar** | `toolbar` | Sortierung, Ansichtsmodus und Produktanzahl |
| **Aktive Filter** | `active-filters` | Zeigt aktive Filterbadges mit Entfernen-Funktion |
| **Merkliste** | `wishlist` | Merklisten-Drawer mit Export-Funktionen |
| **Merkliste-Button** | `wishlist-button` | Standalone-Button zum Öffnen der Merkliste |
| **Produktdetail** | `product-detail` | Produkt-Detailansicht als Modal |
| **Vergleich** | `compare` | Produktvergleich als Tabelle |
| **Sprache** | `locale` | Sprachwähler (DE/EN) |

::: tip Tipp
Die Widgets `product-detail` und `compare` werden als Modals angezeigt und können an beliebiger Stelle im HTML platziert werden (z.B. am Ende des `<body>`).
:::

## Templates (Vorlagen)

Templates sind fertige HTML-Dateien, die als Ausgangspunkt für eigene Designs dienen. Sie liegen im Verzeichnis `catalog-embed/examples/`.

### Mitgelieferte Templates

| Template | Beschreibung |
|---|---|
| **minimal** | Minimales Beispiel mit nur wenigen Widgets |
| **basic** | Vollständiges Layout mit allen Widgets |
| **custom-design** | Beispiel mit umfangreichem Custom-CSS |
| **austrian-sunrise** | Professionelles Template mit 3-Row-Header, Sidebar und Blau/Gelb-Farbschema |

### Templates aufrufen

Templates können direkt über die URL aufgerufen werden:

```
https://ihre-domain.de/catalog-embed/           → Template-Übersicht
https://ihre-domain.de/catalog-embed/basic       → Basic-Template
https://ihre-domain.de/catalog-embed/austrian-sunrise → Austrian Sunrise
```

Dies funktioniert auch bei Installationen in Unterverzeichnissen:

```
https://ihre-domain.de/pim/catalog-embed/basic
```

## Design anpassen (CSS Custom Properties)

Das gesamte Erscheinungsbild wird über CSS Custom Properties (Variablen) gesteuert. Überschreiben Sie diese in einem `<style>`-Block:

```css
:root {
  /* Farben */
  --pxc-primary: #004588;        /* Hauptfarbe */
  --pxc-primary-text: #ffffff;   /* Text auf Hauptfarbe */
  --pxc-accent: #fd0;            /* Akzentfarbe */
  --pxc-bg: #ffffff;             /* Hintergrund */
  --pxc-surface: #f7f7f7;        /* Flächen/Karten */
  --pxc-border: #e5e7eb;         /* Rahmenfarbe */
  --pxc-text: #111827;           /* Textfarbe */
  --pxc-text-muted: #6b7280;     /* Gedämpfter Text */

  /* Typografie & Form */
  --pxc-font: 'Open Sans', sans-serif;
  --pxc-radius: 8px;             /* Eckenradius */
  --pxc-shadow: 0 1px 3px rgba(0,0,0,0.1);
  --pxc-shadow-lg: 0 4px 12px rgba(0,0,0,0.15);
}
```

Alle Widget-Klassen verwenden das Prefix `pxc-` und folgen der BEM-Namenskonvention:

```css
.pxc-product-card { }           /* Block */
.pxc-product-card__image { }    /* Element */
.pxc-product-card--featured { } /* Modifier */
```

## Konfiguration (`PublixxCatalog.init`)

| Option | Typ | Standard | Beschreibung |
|---|---|---|---|
| `api` | `string` | `/api/v1` | Basis-URL der Katalog-API |
| `locale` | `string` | `'de'` | Sprache (`de` oder `en`) |
| `perPage` | `number` | `24` | Produkte pro Seite |
| `token` | `string` | — | Optionaler API-Token für geschützte Kataloge |
| `cache` | `boolean` | `true` | Aktiviert In-Memory-Caching (60s TTL) |

```html
<script>
  PublixxCatalog.init({
    api: 'https://ihre-domain.de/api/v1',
    locale: 'de',
    perPage: 12,
    token: 'ihr-katalog-token',
  })
</script>
```

## Eigenes Template erstellen

1. Kopieren Sie ein bestehendes Template (z.B. `basic.html`) als Ausgangspunkt
2. Passen Sie die CSS Custom Properties in `:root` an Ihr Corporate Design an
3. Ordnen Sie die Widget-Container (`data-catalog="..."`) nach Ihrem Layout an
4. Fügen Sie eigene HTML-Elemente (Header, Footer, Banner) hinzu
5. Speichern Sie die Datei unter `catalog-embed/examples/ihr-name.html`
6. Rufen Sie das Template auf unter `/catalog-embed/ihr-name`

::: warning Hinweis
Template-Dateinamen dürfen nur Buchstaben, Zahlen, Bindestriche und Unterstriche enthalten. Keine Leerzeichen oder Sonderzeichen.
:::

## Deployment

Das Katalog-Embed wird automatisch beim Deployment gebaut. Der Deploy-Prozess:

1. Führt `npm ci` im `catalog-embed/`-Verzeichnis aus
2. Baut das Bundle mit `npm run build`
3. Kopiert die Dateien nach `public/catalog-embed-assets/`

Die erzeugten Dateien:
- `catalog-embed.umd.js` — JavaScript-Bundle (~120 KB)
- `catalog-embed.css` — Stylesheet (~22 KB)

## Technische Details

| Aspekt | Detail |
|---|---|
| **Framework** | Vue 3 (Composition API) |
| **Bundler** | Vite (UMD-Format) |
| **Abhängigkeiten** | Keine externen Laufzeit-Abhängigkeiten |
| **API-Client** | Native `fetch()` mit 60s In-Memory-Cache |
| **State-Management** | Vue 3 Reactivity (Singleton Store) |
| **CSS** | Custom Properties + BEM mit `pxc-`-Prefix |
| **Bundle-Größe** | ~120 KB JS + ~22 KB CSS (minified) |

## Nächste Schritte

- [Katalog-API Endpunkte](/de/api/produkte) — API-Referenz für die Katalog-Endpunkte
- [Zugangslinks](/de/administration/zugangslinks) — Katalog-Zugang für externe Nutzer einrichten

import { defineConfig } from 'vitepress'

// Der Deploy-Pfad haengt vom individuellen Installationspfad ab (Root-Modus -> "/web/help/",
// Unterverzeichnis-Modus z.B. "/pim/help/" -- siehe setup.sh/update.sh, Apache-Alias
// "${WEB_PATH}/help"). update.sh/setup.sh setzen DOCS_BASE_PATH vor dem Build passend zum
// jeweiligen WEB_PATH; ohne die Variable (z.B. lokaler `vitepress dev`) gilt die Root-Konvention.
const DOCS_BASE_PATH = process.env.DOCS_BASE_PATH || '/web/help/'

// Wo die App selbst liegt (Root-Modus -> "/", Unterverzeichnis-Modus -> "${WEB_PATH}/").
// WICHTIG: laesst sich NICHT aus DOCS_BASE_PATH ableiten -- im Root-Modus liegt die Doku
// per fixer Konvention unter "/web/help/", obwohl die App unter "/" (nicht "/web/") laeuft.
// Beide Faelle ergeben ohne diesen zweiten, von setup.sh/update.sh separat gesetzten Wert
// identisch aussehende Doku-URLs, sind aber nicht auseinander ableitbar -- deshalb hier
// explizit und nicht laufzeitseitig aus der aktuellen URL geraten (das hatte zuvor einen
// kaputten "PIM öffnen"-Link im Root-Modus verursacht).
const APP_ROOT_PATH = process.env.APP_ROOT_PATH || '/'

// Oeffentliche Basis-URL der Doku fuer die sitemap.xml, z.B.
// "https://ihre-domain.de/web/help/". Wird von setup.sh/update.sh aus APP_URL +
// DOCS_BASE_PATH zusammengesetzt; ohne die Variable (lokaler Build) entsteht schlicht
// keine sitemap.xml -- so bleibt die Domain nirgends hardcodiert.
//
// WICHTIG: Der Wert muss den Doku-Pfad enthalten UND auf "/" enden. VitePress erzeugt
// die Eintraege aus den Quellpfaden ("de/bedienung/dashboard") OHNE `base` und loest sie
// per new URL(eintrag, hostname) auf -- fehlt der Doku-Pfad oder der abschliessende
// Slash, zeigen alle Sitemap-URLs ins Leere.
const SITEMAP_HOSTNAME = process.env.DOCS_SITEMAP_HOSTNAME || ''

export default defineConfig({
  title: 'anyPIM',
  description: 'Dokumentation für das anyPIM Product Information Management System',
  base: DOCS_BASE_PATH,
  cleanUrls: true,
  lastUpdated: true,
  // Standard ist Light Mode (unabhaengig von der OS-/Browser-Praeferenz). Der
  // Dark-Mode-Umschalter bleibt verfuegbar; die Wahl wird wie ueblich in
  // localStorage gemerkt.
  appearance: {
    initialValue: 'light',
  },

  // Erzeugt beim Build dist/sitemap.xml mit allen Seiten beider Sprachen
  // (inkl. hreflang-Verknuepfung DE<->EN, die VitePress automatisch setzt).
  ...(SITEMAP_HOSTNAME ? { sitemap: { hostname: SITEMAP_HOSTNAME } } : {}),

  vite: {
    define: {
      // Fuer Seiten, die zur Laufzeit auf die App zurueckverlinken muessen
      // (z.B. der Login-Link im Bedienungshandbuch), als Build-Zeit-Konstante
      // statt als laufzeitseitige URL-Vermutung.
      __APP_ROOT_PATH__: JSON.stringify(APP_ROOT_PATH),
    },
    build: {
      chunkSizeWarningLimit: 1000,
    },
  },

  head: [
    ['link', { rel: 'icon', type: 'image/svg+xml', href: `${DOCS_BASE_PATH}logo.svg` }],
  ],

  locales: {
    de: {
      label: 'Deutsch',
      lang: 'de',
      link: '/de/',
      themeConfig: {
        nav: [
          // Marketing-Seiten in einem Aufklappmenue gebuendelt -- sie belegten vorher
          // 4 von 7 Nav-Plaetzen und dominierten damit vor allem das mobile Menue.
          {
            text: 'Produkt',
            items: [
              { text: 'Übersicht', link: '/de/marketing/' },
              { text: 'Vertriebsmappe', link: '/de/marketing/vertriebsmappe' },
              { text: 'Integrationen', link: '/de/marketing/integrationen' },
              { text: 'KI & API', link: '/de/marketing/ki-uebersetzung' },
            ],
          },
          { text: 'Dokumentation', link: '/de/' },
          { text: 'API-Referenz', link: '/de/api/' },
          // Build-Zeit-Konstante (siehe APP_ROOT_PATH oben) statt hardcodierter Domain
          // oder laufzeitseitig geratenem Pfad -- funktioniert fuer Root- ("/") und
          // Unterverzeichnis-Installationen (z.B. "/pim/") gleichermassen korrekt.
          { text: 'PIM öffnen', link: APP_ROOT_PATH },
        ],
        sidebar: {
          // Marketing-Landingpages ohne Doku-Baum. VitePress sortiert die Sidebar-Keys
          // nach Pfadtiefe absteigend, '/de/marketing/' gewinnt also gegen '/de/'; ein
          // leeres Array setzt hasSidebar=false, die Seite rendert dann volle Breite.
          '/de/marketing/': [],
          // Gliederung spiegelt bewusst die Sidebar der Anwendung (AppSidebar.vue), damit
          // ein Nutzer die Doku dort findet, wo er die Funktion in der App kennt.
          // Reihenfolge folgt dem Nutzer-Pfad: kennenlernen -> installieren -> bedienen ->
          // konfigurieren -> Daten austauschen -> ausspielen -> integrieren -> verwalten.
          '/de/': [
            {
              text: 'Erste Schritte',
              collapsed: false,
              items: [
                { text: 'Einführung', link: '/de/' },
                { text: 'Alleinstellungsmerkmale', link: '/de/intro/alleinstellungsmerkmale' },
                // Bewusst doppelt (auch unter "Installation & Betrieb") -- Wegweiser für Neulinge.
                { text: 'Schnellstart', link: '/de/installation/schnellstart' },
              ],
            },
            {
              text: 'Installation & Betrieb',
              collapsed: true,
              items: [
                { text: 'Übersicht', link: '/de/installation/' },
                { text: 'Voraussetzungen', link: '/de/installation/voraussetzungen' },
                { text: 'Schnellstart', link: '/de/installation/schnellstart' },
                { text: 'Umgebungsvariablen', link: '/de/installation/umgebungsvariablen' },
                { text: 'Cronjobs & Planung', link: '/de/installation/cronjobs' },
                { text: 'Deployment', link: '/de/installation/deployment' },
                { text: 'Typesense', link: '/de/installation/typesense' },
              ],
            },
            {
              // "Daily Business" ist das Label, das die App-Oberflaeche auch auf Deutsch
              // woertlich anzeigt (locales/chromeRawText.js: 'Daily Business': 'Daily Business')
              // -- bewusst uebernommen, damit die Ueberschrift exakt der App-Sidebar entspricht.
              text: 'Daily Business',
              collapsed: false,
              items: [
                { text: 'Übersicht', link: '/de/bedienung/' },
                { text: 'Dashboard', link: '/de/bedienung/dashboard' },
                { text: 'Produkte', link: '/de/bedienung/produkte' },
                { text: 'Varianten & Versionen', link: '/de/bedienung/varianten-versionen' },
                { text: 'Hierarchien', link: '/de/bedienung/hierarchien' },
                { text: 'Merkliste', link: '/de/bedienung/merkliste' },
                { text: 'Massenbearbeitung', link: '/de/bedienung/massenbearbeitung' },
                { text: 'Workflow', link: '/de/bedienung/workflow' },
                { text: 'Planungskalender', link: '/de/erweitert/planungskalender' },
                { text: 'Geplante Aktionen', link: '/de/erweitert/geplante-aktionen' },
                { text: 'Medien', link: '/de/bedienung/medien' },
              ],
            },
            {
              text: 'Konfiguration',
              collapsed: true,
              items: [
                { text: 'Attribute', link: '/de/bedienung/attribute' },
                { text: 'Wörterbuch', link: '/de/bedienung/woerterbuch' },
                { text: 'Einheiten', link: '/de/bedienung/einheiten' },
                { text: 'Preise', link: '/de/bedienung/preise' },
                { text: 'Preisregionen', link: '/de/erweitert/preisregionen' },
                { text: 'Hersteller', link: '/de/bedienung/hersteller' },
                { text: 'Beziehungstypen', link: '/de/bedienung/beziehungstypen' },
              ],
            },
            {
              text: 'Import & Export',
              collapsed: true,
              items: [
                { text: 'Import — Übersicht', link: '/de/import/' },
                { text: 'Excel-Format', link: '/de/import/excel-format' },
                { text: 'Validierung', link: '/de/import/validierung' },
                { text: 'Export — Übersicht', link: '/de/export/' },
                { text: 'JSON-Export', link: '/de/export/json-export' },
                { text: 'Sheet-Designer (Excel)', link: '/de/export/sheet-designer' },
                { text: 'Publixx-Export', link: '/de/export/publixx-export' },
                { text: 'BMEcat', link: '/de/administration/bmecat' },
                { text: 'Export-Jobs', link: '/de/erweitert/exportjobs' },
                { text: 'Attribut-Mapping', link: '/de/erweitert/attribut-mapping' },
              ],
            },
            {
              text: 'Publish & Ausgabe',
              collapsed: true,
              items: [
                { text: 'Berichte', link: '/de/erweitert/berichte' },
                { text: 'PDF-Vorlagen', link: '/de/erweitert/pdf-vorlagen' },
                { text: 'Katalog-Embed', link: '/de/erweitert/catalog-embed' },
                { text: 'Katalog-Embed Architektur', link: '/de/erweitert/catalog-embed-architektur' },
                { text: 'Portale', link: '/de/erweitert/portale' },
                { text: 'Reel-Generator (Social-Video)', link: '/de/ki/reel-generator' },
              ],
            },
            {
              text: 'KI & Automatisierung',
              collapsed: true,
              items: [
                { text: 'Übersicht', link: '/de/ki/' },
                { text: 'Copilot (KI-Assistent)', link: '/de/ki/copilot' },
                { text: 'Semantische Suche', link: '/de/ki/semantische-suche' },
              ],
            },
            {
              text: 'Übersetzungen',
              collapsed: true,
              items: [
                { text: 'Übersetzungen (TMS)', link: '/de/bedienung/uebersetzungen' },
                { text: 'Übersetzungsjobs (XLIFF)', link: '/de/bedienung/uebersetzungsjobs' },
              ],
            },
            {
              // Eigene Top-Level-Gruppe, weil die App "Projektmanagement" als eigenen
              // Bereich fuehrt (Geschwister von Administration), nicht als Unterpunkt.
              text: 'Projektmanagement',
              collapsed: true,
              items: [
                { text: 'Projekte & Teams', link: '/de/bedienung/projekte-teams' },
              ],
            },
            {
              text: 'Integrationen & API',
              collapsed: true,
              items: [
                { text: 'Übersicht', link: '/de/api/' },
                { text: 'Authentifizierung', link: '/de/api/authentifizierung' },
                { text: 'API-Keys', link: '/de/api/api-keys' },
                { text: 'Produkte', link: '/de/api/produkte' },
                { text: 'Attribute', link: '/de/api/attribute' },
                { text: 'Hierarchien', link: '/de/api/hierarchien' },
                { text: 'PQL-Abfragesprache', link: '/de/api/pql' },
                { text: 'PimSync API', link: '/de/api/pim-sync' },
                { text: 'API-Designer', link: '/de/erweitert/api-designer' },
                { text: 'Konnektoren & Integrationen', link: '/de/erweitert/konnektoren' },
              ],
            },
            {
              text: 'Administration',
              collapsed: true,
              items: [
                { text: 'Rollen & Berechtigungen', link: '/de/administration/rollen' },
                { text: 'Benutzerverwaltung', link: '/de/bedienung/benutzer' },
                { text: 'Benutzer-Audit', link: '/de/administration/benutzer-audit' },
                { text: 'Zugangslinks', link: '/de/administration/zugangslinks' },
                { text: 'Journal', link: '/de/administration/journal' },
                { text: 'Datenbank', link: '/de/administration/datenbank' },
                { text: 'API-Tester', link: '/de/administration/api-tester' },
              ],
            },
            {
              text: 'Architektur',
              collapsed: true,
              items: [
                { text: 'Übersicht', link: '/de/architektur/' },
                { text: 'Datenmodell', link: '/de/architektur/datenmodell' },
                { text: 'Services & Events', link: '/de/architektur/services' },
                { text: 'Vererbung', link: '/de/architektur/vererbung' },
              ],
            },
            {
              text: 'FAQ',
              collapsed: true,
              items: [
                { text: 'Häufige Fragen', link: '/de/faq/' },
              ],
            },
          ],
        },
        outline: {
          label: 'Auf dieser Seite',
        },
        docFooter: {
          prev: 'Vorherige Seite',
          next: 'Nächste Seite',
        },
        lastUpdated: {
          text: 'Zuletzt aktualisiert',
        },
        search: {
          provider: 'local',
          options: {
            translations: {
              button: {
                buttonText: 'Suchen',
                buttonAriaLabel: 'Suchen',
              },
              modal: {
                displayDetails: 'Details anzeigen',
                resetButtonTitle: 'Suche zurücksetzen',
                backButtonTitle: 'Suche schließen',
                noResultsText: 'Keine Ergebnisse für',
                footer: {
                  selectText: 'Auswählen',
                  navigateText: 'Navigieren',
                  closeText: 'Schließen',
                },
              },
            },
          },
        },
      },
    },
    en: {
      label: 'English',
      lang: 'en',
      link: '/en/',
      themeConfig: {
        nav: [
          // Marketing pages bundled into one dropdown -- they previously took
          // 4 of 7 nav slots and dominated the mobile menu in particular.
          {
            text: 'Product',
            items: [
              { text: 'Overview', link: '/en/marketing/' },
              { text: 'Sales Folder', link: '/en/marketing/sales-folder' },
              { text: 'Integrations', link: '/en/marketing/integrations' },
              { text: 'AI & API', link: '/en/marketing/ai-translation' },
            ],
          },
          { text: 'Documentation', link: '/en/' },
          { text: 'API Reference', link: '/en/api/' },
          // Build-time constant (see APP_ROOT_PATH above) instead of a hardcoded
          // domain or a runtime-guessed path -- correct for root ("/") and
          // subdirectory installs (e.g. "/pim/") alike.
          { text: 'Open PIM', link: APP_ROOT_PATH },
        ],
        sidebar: {
          // Marketing landing pages without the docs tree. VitePress sorts sidebar keys
          // by path depth descending, so '/en/marketing/' beats '/en/'; an empty array
          // sets hasSidebar=false and the page renders full width.
          '/en/marketing/': [],
          // Structure deliberately mirrors the application's own sidebar (AppSidebar.vue)
          // so users find a doc where they know the feature in the app. Order follows the
          // user journey: learn -> install -> operate -> configure -> exchange data ->
          // publish -> integrate -> administer.
          '/en/': [
            {
              text: 'Getting Started',
              collapsed: false,
              items: [
                { text: 'Introduction', link: '/en/' },
                { text: 'Unique Features', link: '/en/intro/unique-features' },
                // Deliberately duplicated (also under "Installation & Operations") as a signpost.
                { text: 'Quick Start', link: '/en/installation/quickstart' },
              ],
            },
            {
              text: 'Installation & Operations',
              collapsed: true,
              items: [
                { text: 'Overview', link: '/en/installation/' },
                { text: 'Requirements', link: '/en/installation/requirements' },
                { text: 'Quick Start', link: '/en/installation/quickstart' },
                { text: 'Environment', link: '/en/installation/environment' },
                { text: 'Cron Jobs & Scheduling', link: '/en/installation/cron-jobs' },
                { text: 'Deployment', link: '/en/installation/deployment' },
                { text: 'Typesense', link: '/en/installation/typesense' },
              ],
            },
            {
              // "Daily Business" is the literal label the app chrome shows in BOTH languages
              // (locales/chromeRawText.js: 'Daily Business': 'Daily Business') -- kept verbatim
              // so the heading matches the app sidebar word for word.
              text: 'Daily Business',
              collapsed: false,
              items: [
                { text: 'Overview', link: '/en/usage/' },
                { text: 'Dashboard', link: '/en/usage/dashboard' },
                { text: 'Products', link: '/en/usage/products' },
                { text: 'Variants & Versions', link: '/en/usage/variants-versions' },
                { text: 'Hierarchies', link: '/en/usage/hierarchies' },
                { text: 'Watchlist', link: '/en/usage/watchlist' },
                { text: 'Bulk Editing', link: '/en/usage/bulk-editing' },
                { text: 'Workflow', link: '/en/usage/workflow' },
                { text: 'Planning Calendar', link: '/en/advanced/calendar' },
                { text: 'Scheduled Actions', link: '/en/advanced/scheduled-actions' },
                { text: 'Media', link: '/en/usage/media' },
              ],
            },
            {
              text: 'Configuration',
              collapsed: true,
              items: [
                { text: 'Attributes', link: '/en/usage/attributes' },
                { text: 'Dictionary', link: '/en/usage/dictionary' },
                { text: 'Units', link: '/en/usage/units' },
                { text: 'Pricing', link: '/en/usage/pricing' },
                { text: 'Price Regions', link: '/en/advanced/price-regions' },
                { text: 'Manufacturers', link: '/en/usage/manufacturers' },
                { text: 'Relation Types', link: '/en/usage/relation-types' },
              ],
            },
            {
              text: 'Import & Export',
              collapsed: true,
              items: [
                { text: 'Import — Overview', link: '/en/import/' },
                { text: 'Excel Format', link: '/en/import/excel-format' },
                { text: 'Validation', link: '/en/import/validation' },
                { text: 'Export — Overview', link: '/en/export/' },
                { text: 'JSON Export', link: '/en/export/json-export' },
                { text: 'Sheet Designer (Excel)', link: '/en/export/sheet-designer' },
                { text: 'Publixx Export', link: '/en/export/publixx-export' },
                { text: 'BMEcat', link: '/en/administration/bmecat' },
                { text: 'Export Jobs', link: '/en/advanced/export-jobs' },
                { text: 'Attribute Mapping', link: '/en/advanced/attribute-mapping' },
              ],
            },
            {
              text: 'Publishing & Output',
              collapsed: true,
              items: [
                { text: 'Reports', link: '/en/advanced/reports' },
                { text: 'PDF Templates', link: '/en/advanced/pdf-templates' },
                { text: 'Catalog Embed', link: '/en/advanced/catalog-embed' },
                { text: 'Catalog Embed Architecture', link: '/en/advanced/catalog-embed-architecture' },
                { text: 'Portals', link: '/en/advanced/portals' },
                { text: 'Reel Generator (Social Video)', link: '/en/ai/reel-generator' },
              ],
            },
            {
              text: 'AI & Automation',
              collapsed: true,
              items: [
                { text: 'Overview', link: '/en/ai/' },
                { text: 'Copilot (AI Assistant)', link: '/en/ai/copilot' },
                { text: 'Semantic Search', link: '/en/ai/semantic-search' },
              ],
            },
            {
              text: 'Translation',
              collapsed: true,
              items: [
                { text: 'Translations (TMS)', link: '/en/usage/translations' },
                { text: 'Translation Jobs (XLIFF)', link: '/en/usage/translation-jobs' },
              ],
            },
            {
              // Own top-level group because the app has "Projektmanagement" as a section of
              // its own (sibling of Administration), not as a child of it.
              text: 'Project Management',
              collapsed: true,
              items: [
                { text: 'Projects & Teams', link: '/en/usage/projects-teams' },
              ],
            },
            {
              // Note: API Keys and PimSync exist in German only -- no EN pages for them,
              // so this group has 8 entries where the German one has 10.
              text: 'Integrations & API',
              collapsed: true,
              items: [
                { text: 'Overview', link: '/en/api/' },
                { text: 'Authentication', link: '/en/api/authentication' },
                { text: 'Products', link: '/en/api/products' },
                { text: 'Attributes', link: '/en/api/attributes' },
                { text: 'Hierarchies', link: '/en/api/hierarchies' },
                { text: 'PQL Query Language', link: '/en/api/pql' },
                { text: 'API Designer', link: '/en/advanced/api-designer' },
                { text: 'Connectors & Integrations', link: '/en/advanced/connectors' },
              ],
            },
            {
              text: 'Administration',
              collapsed: true,
              items: [
                { text: 'Roles & Permissions', link: '/en/administration/roles' },
                { text: 'User Management', link: '/en/usage/users' },
                { text: 'User Audit', link: '/en/administration/user-audit' },
                { text: 'Access Links', link: '/en/administration/access-links' },
                { text: 'Journal', link: '/en/administration/journal' },
                { text: 'Database', link: '/en/administration/database' },
                { text: 'API Tester', link: '/en/administration/api-tester' },
              ],
            },
            {
              text: 'Architecture',
              collapsed: true,
              items: [
                { text: 'Overview', link: '/en/architecture/' },
                { text: 'Data Model', link: '/en/architecture/data-model' },
                { text: 'Services & Events', link: '/en/architecture/services' },
                { text: 'Inheritance', link: '/en/architecture/inheritance' },
              ],
            },
            {
              text: 'FAQ',
              collapsed: true,
              items: [
                { text: 'Common Questions', link: '/en/faq/' },
              ],
            },
          ],
        },
        outline: {
          label: 'On this page',
        },
      },
    },
  },

  themeConfig: {
    logo: '/logo.svg',
    siteTitle: 'anyPIM',

    socialLinks: [
      { icon: 'github', link: 'https://github.com/incoxx/publixx-pim' },
    ],

    search: {
      provider: 'local',
    },

    footer: {
      message: 'anyPIM Dokumentation',
      copyright: '© 2025 anyPIM / incoxx GmbH',
    },
  },
})

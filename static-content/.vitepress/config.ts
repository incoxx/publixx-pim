import { defineConfig } from 'vitepress'

export default defineConfig({
  title: 'anyPIM',
  description: 'Dokumentation für das anyPIM Product Information Management System',
  base: '/web/help/',
  cleanUrls: true,
  lastUpdated: true,

  vite: {
    build: {
      chunkSizeWarningLimit: 1000,
    },
  },

  head: [
    ['link', { rel: 'icon', type: 'image/svg+xml', href: '/web/help/logo.svg' }],
  ],

  locales: {
    de: {
      label: 'Deutsch',
      lang: 'de',
      link: '/de/',
      themeConfig: {
        nav: [
          { text: 'Produkt', link: '/de/marketing/' },
          { text: 'Vertriebsmappe', link: '/de/marketing/vertriebsmappe' },
          { text: 'Integrationen', link: '/de/marketing/integrationen' },
          { text: 'KI & API', link: '/de/marketing/ki-uebersetzung' },
          { text: 'Dokumentation', link: '/de/' },
          { text: 'API-Referenz', link: '/de/api/' },
          { text: 'PIM öffnen', link: 'https://smartentities.de/web/' },
        ],
        sidebar: {
          '/de/': [
            {
              text: 'Erste Schritte',
              collapsed: false,
              items: [
                { text: 'Einführung', link: '/de/' },
                { text: 'Alleinstellungsmerkmale', link: '/de/intro/alleinstellungsmerkmale' },
              ],
            },
            {
              text: 'Architektur',
              collapsed: false,
              items: [
                { text: 'Übersicht', link: '/de/architektur/' },
                { text: 'Datenmodell', link: '/de/architektur/datenmodell' },
                { text: 'Services & Events', link: '/de/architektur/services' },
                { text: 'Vererbung', link: '/de/architektur/vererbung' },
              ],
            },
            {
              text: 'Bedienung',
              collapsed: false,
              items: [
                { text: 'Übersicht', link: '/de/bedienung/' },
                { text: 'Dashboard', link: '/de/bedienung/dashboard' },
                { text: 'Produkte', link: '/de/bedienung/produkte' },
                { text: 'Merkliste', link: '/de/bedienung/merkliste' },
                { text: 'Workflow', link: '/de/bedienung/workflow' },
                { text: 'Attribute', link: '/de/bedienung/attribute' },
                { text: 'Hierarchien', link: '/de/bedienung/hierarchien' },
                { text: 'Medien', link: '/de/bedienung/medien' },
                { text: 'Preise', link: '/de/bedienung/preise' },
                { text: 'Hersteller', link: '/de/bedienung/hersteller' },
                { text: 'Beziehungstypen', link: '/de/bedienung/beziehungstypen' },
                { text: 'Einheiten', link: '/de/bedienung/einheiten' },
                { text: 'Wörterbuch', link: '/de/bedienung/woerterbuch' },
                { text: 'Benutzerverwaltung', link: '/de/bedienung/benutzer' },
                { text: 'Massenbearbeitung', link: '/de/bedienung/massenbearbeitung' },
                { text: 'Varianten & Versionen', link: '/de/bedienung/varianten-versionen' },
                { text: 'Projekte & Teams', link: '/de/bedienung/projekte-teams' },
                { text: 'Übersetzungsjobs (XLIFF)', link: '/de/bedienung/uebersetzungsjobs' },
              ],
            },
            {
              text: 'KI & Automatisierung',
              collapsed: false,
              items: [
                { text: 'Übersicht', link: '/de/ki/' },
                { text: 'Copilot (KI-Assistent)', link: '/de/ki/copilot' },
                { text: 'Semantische Suche', link: '/de/ki/semantische-suche' },
                { text: 'Reel-Generator (Social-Video)', link: '/de/ki/reel-generator' },
              ],
            },
            {
              text: 'Erweitert',
              collapsed: true,
              items: [
                { text: 'Berichte', link: '/de/erweitert/berichte' },
                { text: 'PDF-Vorlagen', link: '/de/erweitert/pdf-vorlagen' },
                { text: 'Katalog-Embed', link: '/de/erweitert/catalog-embed' },
                { text: 'Katalog-Embed Architektur', link: '/de/erweitert/catalog-embed-architektur' },
                { text: 'Planungskalender', link: '/de/erweitert/planungskalender' },
                { text: 'Geplante Aktionen', link: '/de/erweitert/geplante-aktionen' },
                { text: 'Preisregionen', link: '/de/erweitert/preisregionen' },
                { text: 'Export-Jobs', link: '/de/erweitert/exportjobs' },
                { text: 'Konnektoren & Integrationen', link: '/de/erweitert/konnektoren' },
                { text: 'API-Designer', link: '/de/erweitert/api-designer' },
                { text: 'Attribut-Mapping', link: '/de/erweitert/attribut-mapping' },
                { text: 'Portale', link: '/de/erweitert/portale' },
              ],
            },
            {
              text: 'Administration',
              collapsed: true,
              items: [
                { text: 'Rollen & Berechtigungen', link: '/de/administration/rollen' },
                { text: 'Benutzer-Audit', link: '/de/administration/benutzer-audit' },
                { text: 'Zugangslinks', link: '/de/administration/zugangslinks' },
                { text: 'API-Tester', link: '/de/administration/api-tester' },
                { text: 'Datenbank', link: '/de/administration/datenbank' },
                { text: 'Journal', link: '/de/administration/journal' },
                { text: 'BMEcat', link: '/de/administration/bmecat' },
              ],
            },
            {
              text: 'Installation',
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
              text: 'FAQ',
              collapsed: true,
              items: [
                { text: 'Häufige Fragen', link: '/de/faq/' },
              ],
            },
            {
              text: 'Import',
              collapsed: true,
              items: [
                { text: 'Übersicht', link: '/de/import/' },
                { text: 'Excel-Format', link: '/de/import/excel-format' },
                { text: 'Validierung', link: '/de/import/validierung' },
              ],
            },
            {
              text: 'Export',
              collapsed: true,
              items: [
                { text: 'Übersicht', link: '/de/export/' },
                { text: 'JSON-Export', link: '/de/export/json-export' },
                { text: 'Sheet-Designer (Excel)', link: '/de/export/sheet-designer' },
                { text: 'Publixx-Export', link: '/de/export/publixx-export' },
              ],
            },
            {
              text: 'JSON API',
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
          { text: 'Product', link: '/en/marketing/' },
          { text: 'Sales Folder', link: '/en/marketing/sales-folder' },
          { text: 'Integrations', link: '/en/marketing/integrations' },
          { text: 'AI & API', link: '/en/marketing/ai-translation' },
          { text: 'Documentation', link: '/en/' },
          { text: 'API Reference', link: '/en/api/' },
          { text: 'Open PIM', link: 'https://smartentities.de/web/' },
        ],
        sidebar: {
          '/en/': [
            {
              text: 'Getting Started',
              collapsed: false,
              items: [
                { text: 'Introduction', link: '/en/' },
                { text: 'Unique Features', link: '/en/intro/unique-features' },
              ],
            },
            {
              text: 'Architecture',
              collapsed: false,
              items: [
                { text: 'Overview', link: '/en/architecture/' },
                { text: 'Data Model', link: '/en/architecture/data-model' },
                { text: 'Services & Events', link: '/en/architecture/services' },
                { text: 'Inheritance', link: '/en/architecture/inheritance' },
              ],
            },
            {
              text: 'Usage',
              collapsed: false,
              items: [
                { text: 'Overview', link: '/en/usage/' },
                { text: 'Dashboard', link: '/en/usage/dashboard' },
                { text: 'Products', link: '/en/usage/products' },
                { text: 'Watchlist', link: '/en/usage/watchlist' },
                { text: 'Workflow', link: '/en/usage/workflow' },
                { text: 'Attributes', link: '/en/usage/attributes' },
                { text: 'Hierarchies', link: '/en/usage/hierarchies' },
                { text: 'Media', link: '/en/usage/media' },
                { text: 'Pricing', link: '/en/usage/pricing' },
                { text: 'Manufacturers', link: '/en/usage/manufacturers' },
                { text: 'Relation Types', link: '/en/usage/relation-types' },
                { text: 'Units', link: '/en/usage/units' },
                { text: 'Dictionary', link: '/en/usage/dictionary' },
                { text: 'User Management', link: '/en/usage/users' },
                { text: 'Bulk Editing', link: '/en/usage/bulk-editing' },
                { text: 'Variants & Versions', link: '/en/usage/variants-versions' },
                { text: 'Projects & Teams', link: '/en/usage/projects-teams' },
                { text: 'Translation Jobs (XLIFF)', link: '/en/usage/translation-jobs' },
              ],
            },
            {
              text: 'AI & Automation',
              collapsed: false,
              items: [
                { text: 'Overview', link: '/en/ai/' },
                { text: 'Copilot (AI Assistant)', link: '/en/ai/copilot' },
                { text: 'Semantic Search', link: '/en/ai/semantic-search' },
                { text: 'Reel Generator (Social Video)', link: '/en/ai/reel-generator' },
              ],
            },
            {
              text: 'Advanced',
              collapsed: true,
              items: [
                { text: 'Reports', link: '/en/advanced/reports' },
                { text: 'PDF Templates', link: '/en/advanced/pdf-templates' },
                { text: 'Catalog Embed', link: '/en/advanced/catalog-embed' },
                { text: 'Catalog Embed Architecture', link: '/en/advanced/catalog-embed-architecture' },
                { text: 'Planning Calendar', link: '/en/advanced/calendar' },
                { text: 'Scheduled Actions', link: '/en/advanced/scheduled-actions' },
                { text: 'Price Regions', link: '/en/advanced/price-regions' },
                { text: 'Export Jobs', link: '/en/advanced/export-jobs' },
                { text: 'Connectors & Integrations', link: '/en/advanced/connectors' },
                { text: 'API Designer', link: '/en/advanced/api-designer' },
                { text: 'Attribute Mapping', link: '/en/advanced/attribute-mapping' },
                { text: 'Portals', link: '/en/advanced/portals' },
              ],
            },
            {
              text: 'Administration',
              collapsed: true,
              items: [
                { text: 'Roles & Permissions', link: '/en/administration/roles' },
                { text: 'User Audit', link: '/en/administration/user-audit' },
                { text: 'Access Links', link: '/en/administration/access-links' },
                { text: 'API Tester', link: '/en/administration/api-tester' },
                { text: 'Database', link: '/en/administration/database' },
                { text: 'Journal', link: '/en/administration/journal' },
                { text: 'BMEcat', link: '/en/administration/bmecat' },
              ],
            },
            {
              text: 'Installation',
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
              text: 'FAQ',
              collapsed: true,
              items: [
                { text: 'Common Questions', link: '/en/faq/' },
              ],
            },
            {
              text: 'Import',
              collapsed: true,
              items: [
                { text: 'Overview', link: '/en/import/' },
                { text: 'Excel Format', link: '/en/import/excel-format' },
                { text: 'Validation', link: '/en/import/validation' },
              ],
            },
            {
              text: 'Export',
              collapsed: true,
              items: [
                { text: 'Overview', link: '/en/export/' },
                { text: 'JSON Export', link: '/en/export/json-export' },
                { text: 'Sheet Designer (Excel)', link: '/en/export/sheet-designer' },
                { text: 'Publixx Export', link: '/en/export/publixx-export' },
              ],
            },
            {
              text: 'JSON API',
              collapsed: true,
              items: [
                { text: 'Overview', link: '/en/api/' },
                { text: 'Authentication', link: '/en/api/authentication' },
                { text: 'Products', link: '/en/api/products' },
                { text: 'Attributes', link: '/en/api/attributes' },
                { text: 'Hierarchies', link: '/en/api/hierarchies' },
                { text: 'PQL Query Language', link: '/en/api/pql' },
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

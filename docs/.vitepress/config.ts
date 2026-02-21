import { defineConfig } from 'vitepress'

export default defineConfig({
  title: 'Publixx PIM',
  description: 'Dokumentation für das Publixx Product Information Management System',
  base: '/docs/',
  cleanUrls: true,
  lastUpdated: true,

  head: [
    ['link', { rel: 'icon', type: 'image/svg+xml', href: '/docs/logo.svg' }],
  ],

  locales: {
    de: {
      label: 'Deutsch',
      lang: 'de',
      link: '/de/',
      themeConfig: {
        nav: [
          { text: 'Dokumentation', link: '/de/' },
          { text: 'API-Referenz', link: '/de/api/' },
          { text: 'PIM öffnen', link: '/' },
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
                { text: 'Produkte', link: '/de/bedienung/produkte' },
                { text: 'Attribute', link: '/de/bedienung/attribute' },
                { text: 'Hierarchien', link: '/de/bedienung/hierarchien' },
                { text: 'Medien', link: '/de/bedienung/medien' },
                { text: 'Preise', link: '/de/bedienung/preise' },
                { text: 'Benutzerverwaltung', link: '/de/bedienung/benutzer' },
              ],
            },
            {
              text: 'Installation',
              collapsed: true,
              items: [
                { text: 'Übersicht', link: '/de/installation/' },
                { text: 'Voraussetzungen', link: '/de/installation/voraussetzungen' },
                { text: 'Schnellstart', link: '/de/installation/schnellstart' },
                { text: 'Deployment', link: '/de/installation/deployment' },
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
                { text: 'Publixx-Export', link: '/de/export/publixx-export' },
              ],
            },
            {
              text: 'JSON API',
              collapsed: true,
              items: [
                { text: 'Übersicht', link: '/de/api/' },
                { text: 'Authentifizierung', link: '/de/api/authentifizierung' },
                { text: 'Produkte', link: '/de/api/produkte' },
                { text: 'Attribute', link: '/de/api/attribute' },
                { text: 'Hierarchien', link: '/de/api/hierarchien' },
                { text: 'PQL-Abfragesprache', link: '/de/api/pql' },
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
          { text: 'Documentation', link: '/en/' },
          { text: 'API Reference', link: '/en/api/' },
          { text: 'Open PIM', link: '/' },
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
                { text: 'Products', link: '/en/usage/products' },
                { text: 'Attributes', link: '/en/usage/attributes' },
                { text: 'Hierarchies', link: '/en/usage/hierarchies' },
                { text: 'Media', link: '/en/usage/media' },
                { text: 'Pricing', link: '/en/usage/pricing' },
                { text: 'User Management', link: '/en/usage/users' },
              ],
            },
            {
              text: 'Installation',
              collapsed: true,
              items: [
                { text: 'Overview', link: '/en/installation/' },
                { text: 'Requirements', link: '/en/installation/requirements' },
                { text: 'Quick Start', link: '/en/installation/quickstart' },
                { text: 'Deployment', link: '/en/installation/deployment' },
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
    siteTitle: 'Publixx PIM',

    socialLinks: [
      { icon: 'github', link: 'https://github.com/incoxx/publixx-pim' },
    ],

    search: {
      provider: 'local',
    },

    footer: {
      message: 'Publixx PIM Dokumentation',
      copyright: '© 2025 Publixx / incoxx GmbH',
    },
  },
})

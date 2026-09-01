/**
 * Uebersetzungen fuer Menue (AppSidebar), Toolbar (AppHeader, AppTabBar) und
 * Footer (AppFooter) -- gleicher Rohtext-als-Key-Ansatz wie
 * productEditorRawText.js. Key = deutscher Originaltext, Value = englische
 * Uebersetzung. Wird in main.js in die messages.de/messages.en Bloecke
 * gemischt und ueber denselben rawTextMessageResolver aufgeloest.
 */

const en = {
  // --- AppSidebar.vue: Bereichs-Ueberschriften ---
  'Daily Business': 'Daily Business',
  'Content': 'Content',
  'E-Commerce': 'E-Commerce',
  'Publish': 'Publish',
  'Plugins': 'Plugins',
  'Übersetzungen': 'Translations',
  'Projektmanagement': 'Project management',
  'Konfiguration': 'Configuration',
  'Administration': 'Administration',

  // --- AppSidebar.vue: Menuepunkte/Gruppen ---
  'Dashboard': 'Dashboard',
  'Merkliste': 'Watchlist',
  'Collections': 'Collections',
  'Workflow': 'Workflow',
  'Planungskalender': 'Planning calendar',
  'Motive': 'Motifs',
  'Sitemap': 'Sitemap',
  'Website-Vorschau': 'Website preview',
  'Seitentypen': 'Page types',
  'Sektionstypen': 'Section types',
  'Produkt-Widgets': 'Product widgets',
  'Export / Import': 'Export / Import',
  'Cache': 'Cache',
  'Integration': 'Integration',
  'Typo 3': 'Typo 3',
  'Warenkörbe': 'Shopping carts',
  'Adressarten': 'Address types',
  'Zahlungsarten': 'Payment types',
  'Bestellungen': 'Orders',
  'Berichte': 'Reports',
  'PDF-Vorlagen': 'PDF templates',
  'Katalog-Vorlagen': 'Catalog templates',
  'Katalog-Demo': 'Catalog demo',
  'Social-Video': 'Social video',
  'Plugin-Einstellungen': 'Plugin settings',
  'Connectoren': 'Connectors',
  'Shopware': 'Shopware',
  'Shopify': 'Shopify',
  'Claude AI': 'Claude AI',
  'anyPIM Sync': 'anyPIM Sync',
  'API-Keys': 'API keys',
  'Übersetzungsjobs': 'Translation jobs',
  'Translation Memory': 'Translation Memory',
  'DeepL Einstellungen': 'DeepL settings',
  'Projekt-Dashboard': 'Project dashboard',
  'Workflows': 'Workflows',
  'Workflow-Status': 'Workflow status',
  'Organisation': 'Organization',
  'Teams': 'Teams',
  'Projekte': 'Projects',
  'Produktstruktur': 'Product structure',
  'Referenz-Profile': 'Reference profiles',
  'Konformitäts-Report': 'Conformance report',
  'Attribute': 'Attributes',
  'Preise & Einheiten': 'Prices & units',
  'Einheiten': 'Units',
  'Vergleichsoperatoren': 'Comparison operators',
  'Medien': 'Media',
  'Medien-Sprachen': 'Media languages',
  'Medien-Länder': 'Media countries',
  'Collection-Typen': 'Collection types',
  'Datenaustausch': 'Data exchange',
  'JSON Export/Import': 'JSON Export/Import',
  'Sheet Designer': 'Sheet Designer',
  'BMEcat Import/Export': 'BMEcat Import/Export',
  'Export-Jobs': 'Export jobs',
  'Attribut-Mapping': 'Attribute mapping',
  'Benutzer & Rollen': 'Users & roles',
  'Benutzer-Audit': 'User audit',
  'Rollen': 'Roles',
  'Cockpit-Layouts': 'Cockpit layouts',
  'Zugangslinks': 'Access links',
  'System': 'System',
  'Test anyPIM': 'Test anyPIM',
  'API-Designer': 'API designer',
  'MCP Playground': 'MCP Playground',
  'API Tester': 'API tester',
  'Datenbank': 'Database',
  'Datenkonsistenz': 'Data consistency',
  'Guard': 'Guard',
  'Log Viewer': 'Log Viewer',
  'Artisan-Cockpit': 'Artisan cockpit',
  // Bewusst "Fehlerliste" statt "Fehler" als Key: das Wort "Fehler" wird auch als
  // Status-Badge (Singular, "Error") in ProductConformanceTab/ProductNotesTab
  // verwendet -- mit identischem Key waere die englische Uebersetzung je nach
  // Merge-Reihenfolge fuer eine der beiden Stellen falsch (Singular/Plural).
  'Fehlerliste': 'Errors',
  'Journal': 'Journal',

  'Alle Menüs schließen': 'Close all menus',

  // --- PimCommandPalette.vue ("Wo ist was?", Toolbar) ---
  'Katalog-Vorschau': 'Catalog Preview',
  'navigieren': 'navigate',
  'öffnen': 'open',
  'schließen': 'close',

  // --- AppHeader.vue (Toolbar) ---
  'Cockpit / Fokus-Modus': 'Cockpit / focus mode',
  'Cockpit': 'Cockpit',
  'Klassisches Menü': 'Classic menu',
  'Menü': 'Menu',
  'Katalog-Vorschau öffnen': 'Open catalog preview',
  'Preview': 'Preview',
  'anyPIM Copilot': 'anyPIM Copilot',
  'Copilot': 'Copilot',
  'Wo ist was? — Menüpunkte und Aktionen finden (⌘K)': 'Where is what? — Find menu items and actions (⌘K)',
  'Nachrichten': 'Messages',
  'Abmelden': 'Log out',

  // --- AppTabBar.vue (Toolbar: offene Tabs) ---
  'Alle Tabs schließen': 'Close all tabs',
  'Andere schließen': 'Close others',
  'Alle schließen': 'Close all',

  // --- AppFooter.vue ---
  'Alle Dienste aktiv': 'All services active',
  'Dienste eingeschränkt': 'Services degraded',
  '{name} gestört': '{name} down',
  '{count} Dienste gestört': '{count} services down',
  'Einstellungen > System': 'Settings > System',
  '(Klick: öffnen · Edit-Icon: ändern)': '(Click: open · edit icon: change)',
  'Leer': 'Empty',
  'Edit-Icon: aktuelle Seite zuweisen': 'Edit icon: assign current page',
  'Slot bearbeiten': 'Edit slot',
  'Aktuelle Seite zuweisen': 'Assign current page',
  'Slot P': 'Slot P',
  'Slot leeren': 'Clear slot',
  'Einstellungen > System öffnen': 'Open Settings > System',
  // --- PimCommandPalette.vue: eindeutige Labels für Einträge, deren Menü-Label
  // ohne Gruppenkontext mehrdeutig wäre ('Cache', 'Export / Import') ---
  'Content-Cache': 'Content cache',
  'Content Export / Import': 'Content export / import',
};

// de = Identitaet, gleicher Grund wie in productEditorRawText.js: verhindert,
// dass fallbackLocale:'en' bei fehlendem de-Key versehentlich Englisch zeigt.
const de = Object.fromEntries(Object.keys(en).map((key) => [key, key]));

export const chromeRawText = { de, en };

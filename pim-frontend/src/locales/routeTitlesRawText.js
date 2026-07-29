/**
 * Uebersetzungen fuer Routen-Seitentitel (router/index.js meta.title),
 * angezeigt als <h1> in AppHeader.vue (Toolbar) sowie in AppFooter.vue's
 * Preset-Slots. router/index.js selbst bleibt unveraendert (meta.title
 * traegt weiterhin den deutschen Rohtext) -- die Uebersetzung passiert erst
 * an der Anzeigestelle per t(route.meta.title), damit kein zirkulaerer
 * Import zwischen main.js und router/index.js noetig ist.
 *
 * Gleicher Rohtext-als-Key-Ansatz wie chromeRawText.js/productEditorRawText.js.
 * Viele Menuepunkt-Namen sind ohnehin identisch mit ihren Seitentiteln --
 * diese Datei enthaelt nur die zusaetzlichen, noch nicht vorhandenen Titel.
 */

const en = {
  'Profisuche': 'Advanced Search',
  'Schnellsuche': 'Quick Search',
  'Produkte': 'Products',
  'Bulk-Editor': 'Bulk Editor',
  'Massendatenpflege': 'Bulk Data Maintenance',
  'Produktdetail': 'Product Detail',
  'Content-Seite': 'Content Page',
  'Navigation': 'Navigation',
  'Content-Konfiguration': 'Content Configuration',
  'Content-Cache': 'Content Cache',
  'TYPO3-Integration': 'TYPO3 Integration',
  'Warenkorbarten': 'Cart Types',
  'Hierarchien': 'Hierarchies',
  'Produkttypen': 'Product Types',
  'Attributgruppen': 'Attribute Groups',
  'Attribut-Sichten': 'Attribute Views',
  'Wertelisten': 'Value Lists',
  'Formatierungsregeln': 'Formatting Rules',
  'Wörterbuch': 'Dictionary',
  'Collection importieren': 'Import Collection',
  'Import': 'Import',
  'Bericht-Designer': 'Report Designer',
  'OAuth Callback': 'OAuth Callback',
  'Canva Export': 'Canva Export',
  'DeepL': 'DeepL',
  'Salesforce Commerce Cloud': 'Salesforce Commerce Cloud',
  'Connector-Verbindung': 'Connector Connection',
  'PDF-Vorlage Designer': 'PDF Template Designer',
  'Katalog-Vorlage Designer': 'Catalog Template Designer',
  'Portal Editor': 'Portal Editor',
  'Bildtypen': 'Image Types',
  'Preisregionen': 'Price Regions',
  'Übersetzungsjob erstellen': 'Create Translation Job',
  'Übersetzungsjob': 'Translation Job',
  'Benutzer-Audit-Trail': 'User Audit Trail',
  'Benutzer': 'Users',
  'Zugang': 'Access',
  'Einstellungen': 'Settings',
  'Fehlerklassifikation': 'Error Classification',
  'License Generator': 'License Generator',
  'Änderungsprotokoll': 'Change Log',
  'Workflow-Aufgaben': 'Workflow Tasks',
  'Hilfe': 'Help',
  'Website': 'Website',
  'Produktkatalog': 'Product Catalog',
  'Impressum': 'Imprint',
  'Kontakt': 'Contact',
  'Asset-Katalog': 'Asset Catalog',
  'Dokumentenportal': 'Document Portal',
};

const de = Object.fromEntries(Object.keys(en).map((key) => [key, key]));

export const routeTitlesRawText = { de, en };

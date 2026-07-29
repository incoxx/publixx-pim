/**
 * Uebersetzungen fuer die Produktliste (views/products/ProductListView.vue,
 * Kachel- und Listenansicht) sowie das mitverwendete gemeinsame
 * ProfileSelector.vue (Spaltenprofil-Dropdown). Gleicher Rohtext-als-Key-
 * Ansatz wie die anderen locales/*RawText.js-Dateien.
 *
 * Parametrisierte Keys ({label}) nutzen vue-i18n's Named-Interpolation --
 * noetig, weil sich die Wortstellung zwischen Deutsch ("Spaltenprofil
 * wählen") und Englisch ("Select Column Profile") unterscheidet; eine reine
 * Wort-fuer-Wort-Ersetzung haette in einer der beiden Sprachen falsch
 * geklungen.
 */

const en = {
  // --- ProductListView.vue ---
  'Geändert': 'Changed',
  'Weitere Attribute': 'Other Attributes',
  'Erstellt': 'Created',
  'Zur Prüfung': 'In Review',
  'Freigegeben': 'Approved',
  'Spaltenprofil': 'Column Profile',
  'Quick Export': 'Quick Export',
  'Export...': 'Export...',
  'Excel-Export': 'Excel Export',
  'XLIFF Export / Import': 'XLIFF Export / Import',
  'XLIFF': 'XLIFF',
  'Export…': 'Exporting…',
  'Import…': 'Importing…',
  'XLIFF Import': 'XLIFF Import',
  'Produkte durchsuchen…': 'Search products…',
  'ausgewählt': 'selected',
  'Lade...': 'Loading...',
  'Zur Merkliste': 'Add to Watchlist',
  'Produkte vergleichen': 'Compare Products',
  'Vergleichen': 'Compare',
  'Bulk bearbeiten': 'Bulk Edit',
  'Projekt zuordnen': 'Assign Project',
  'Hierarchie zuordnen': 'Assign Hierarchy',
  'Klassifikation sync': 'Classification Sync',
  'Lösche...': 'Deleting...',
  'Auswahl aufheben': 'Clear Selection',
  'Produkte unwiderruflich löschen?': 'Delete products permanently?',
  'Ja, löschen': 'Yes, delete',
  'Keine Produkte gefunden': 'No products found',
  'Weiter': 'Next',
  'Zurücksetzen': 'Reset',
  'Produkt löschen?': 'Delete product?',
  'Produktvergleich': 'Product Comparison',
  'Unterschiede von': 'Differences from',
  'Feldern': 'fields',
  'Nur Unterschiede': 'Differences only',
  'Keine Unterschiede gefunden — Produkte sind identisch': 'No differences found — products are identical',
  'Keine Daten zum Vergleichen': 'No data to compare',

  // --- ProfileSelector.vue (gemeinsame Komponente, hier neu uebersetzt) ---
  'Profil': 'Profile',
  '— {label} wählen —': '— Select {label} —',
  '{label} aktualisieren': 'Update {label}',
  '{label} speichern': 'Save {label}',
  'Als neues {label} speichern': 'Save as New {label}',
  '{label} löschen': 'Delete {label}',
  '(geteilt)': '(shared)',
  'Profilname ändern...': 'Change profile name...',
  'Profilname eingeben...': 'Enter profile name...',
  'Für alle Benutzer freigeben': 'Share with all users',
};

const de = Object.fromEntries(Object.keys(en).map((key) => [key, key]));

export const productListRawText = { de, en };

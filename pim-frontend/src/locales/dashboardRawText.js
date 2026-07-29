/**
 * Uebersetzungen fuer das Dashboard (views/dashboard/DashboardView.vue +
 * components/dashboard/*.vue, 24 Dateien). Gleicher Rohtext-als-Key-Ansatz
 * wie die anderen locales/*RawText.js-Dateien.
 *
 * Enthaelt sowohl automatisch vom Compiler-Transform erfasste Template-Texte
 * als auch manuell mit t() versehene JS-Literale (Widget-Registry-Labels,
 * Status-Maps, relative Zeitangaben mit vue-i18n Named-Interpolation, da
 * sich die Wortstellung zwischen "vor {count} Min." und "{count} min ago"
 * unterscheidet).
 */

const en = {
  // --- DashboardView.vue: Widget-Registry ---
  'Begrüßung': 'Welcome',
  'Übersicht': 'Overview',
  'Datenqualität': 'Data Quality',
  'Aktivitäten': 'Activity',
  'Meine Aufgaben': 'My Tasks',
  'Datenflüsse': 'Data Flows',
  'Schnellzugriff': 'Quick Access',
  'Zuletzt bearbeitet': 'Recently Edited',
  'Produkt-Füllstand': 'Product Completeness',
  'Füllstand (Merkliste)': 'Completeness (Watchlist)',
  'Datenqualität (Merkliste)': 'Data Quality (Watchlist)',
  'Medien (Merkliste)': 'Media (Watchlist)',
  'Medien-Spotlight (alle)': 'Media Spotlight (All)',

  // --- DashboardView.vue: Layout-/Preset-Panel ---
  'Auto-Refresh 60s': 'Auto-refresh 60s',
  'Layouts': 'Layouts',
  'Dashboard-Layouts': 'Dashboard Layouts',
  'Standard': 'Default',
  'Werkseinstellung': 'Factory default',
  'Änderungen in dieses Layout speichern': 'Save changes to this layout',
  '" löschen?': '" delete?',
  'Ja': 'Yes',
  'Nein': 'No',
  '" gespeichert': '" saved',
  'Layout speichern': 'Save layout',
  'Name des Layouts...': 'Layout name...',
  'Aktuelles Layout speichern': 'Save current layout',
  'Widgets': 'Widgets',
  'Kacheln ein-/ausblenden & sortieren': 'Show/hide & reorder tiles',
  'Profilkarten': 'Profile Cards',
  'Profilkarte hinzufügen': 'Add profile card',

  // --- ActiveProjectsWidget.vue ---
  'Aktive Projekte': 'Active Projects',
  'Keine aktiven Projekte': 'No active projects',
  '{count} Teams': '{count} teams',
  '{count} Produkte': '{count} products',
  '{count} Tage': '{count} days',
  'Überfällig': 'Overdue',

  // --- ActivityFeedWidget.vue / DataFlowWidget.vue (relative Zeit) ---
  'Noch keine Aktivitäten': 'No activity yet',
  'gerade eben': 'just now',
  'vor {count} Min.': '{count} min ago',
  'vor {count} Std.': '{count}h ago',
  'gestern': 'yesterday',
  'vor {count} Tagen': '{count} days ago',

  // --- CompletenessWidget.vue / DataQualityWidget.vue ---
  'Keine Daten verfügbar': 'No data available',
  'Keine Produkte vorhanden': 'No products available',
  'Vollständig': 'Complete',
  'Unvollständig': 'Incomplete',
  'Produkten': 'products',

  // --- DataFlowWidget.vue ---
  'Keine Datenflüsse': 'No data flows',
  'Imports': 'Imports',
  'Exports': 'Exports',

  // --- DataQualityWidget.vue ---
  'Gesamt': 'Total',
  'Nächster Schritt:': 'Next step:',

  // --- MediaSpotlightWidget.vue ---
  'Medien meiner Merkliste': 'My watchlist media',
  'Medien-Spotlight': 'Media Spotlight',
  'Produkte auf der Merkliste haben noch keine Medien.': 'Products on the watchlist have no media yet.',
  'Noch keine Medien vorhanden': 'No media yet',
  'Alt-Text fehlt': 'Alt text missing',
  'ohne Alt-Text': 'without alt text',
  'Medien gesamt': 'media total',
  'Zur Mediathek': 'To media library',

  // --- MyTasksWidget.vue ---
  'Keine offenen Aufgaben': 'No open tasks',
  'Alle Aufgaben anzeigen': 'Show all tasks',

  // --- NotesWidget.vue ---
  'vor {count}m': '{count}m ago',
  'vor {count}h': '{count}h ago',
  'vor {count}d': '{count}d ago',
  'Notiz schreiben...': 'Write a note...',
  'Farbe': 'Color',
  'Für alle sichtbar': 'Visible to everyone',
  'Produkt verknüpfen (Name/SKU)...': 'Link product (name/SKU)...',
  'Noch keine Notizen': 'No notes yet',
  'Neue Notiz': 'New note',
  'Loslösen': 'Unpin',
  'Anheften': 'Pin',

  // --- ProfileCardConfigurator.vue ---
  'Profilkarte bearbeiten': 'Edit profile card',
  'Neue Profilkarte': 'New profile card',
  'Suchprofil wählen...': 'Select search profile...',
  'Gauge (Halbkreis)': 'Gauge (half circle)',
  'Zahl (groß)': 'Number (large)',
  'Trend (7 Tage)': 'Trend (7 days)',
  'Status-Verteilung': 'Status Distribution',
  'Donut (Multi-Metrik)': 'Donut (multi-metric)',
  'Vollständigkeit': 'Completeness',
  'Medien-Abdeckung': 'Media Coverage',
  'Preis-Abdeckung': 'Price Coverage',
  'Gesamt-Anzahl': 'Total Count',
  'Aktive Produkte': 'Active Products',
  'Entwürfe': 'Drafts',
  'Blau': 'Blue',
  'Grün': 'Green',
  'Orange': 'Orange',
  'Rot': 'Red',
  'Violett': 'Purple',
  'Cyan': 'Cyan',
  'Pink': 'Pink',
  'Dunkel': 'Dark',
  'Darstellung': 'Display',
  'Metrik': 'Metric',
  'Entfernen': 'Remove',

  // --- ProfileStatCard.vue ---
  'Profil': 'Profile',
  'Fehler': 'Error',
  'Preise': 'Prices',
  'Konfigurieren': 'Configure',
  'Kein Suchprofil ausgewählt': 'No search profile selected',
  'Letzte 7 Tage': 'Last 7 days',
  'Vollst.': 'Compl.',

  // --- ProjectTimelineWidget.vue ---
  'Planung': 'Planning',
  'Pausiert': 'Paused',
  '({count} Produkte)': '({count} products)',
  '{count}d': '{count}d',
  'Projekt-Timeline': 'Project Timeline',
  'Heute': 'Today',

  // --- QuickLinksWidget.vue ---
  'Tagesgeschäft': 'Daily Business',
  'Veröffentlichen': 'Publish',
  'Verbinden': 'Connect',
  'Kalender': 'Calendar',
  'Keine Schnellzugriffe eingerichtet': 'No quick links set up',
  'Menüpunkte auswählen': 'Select menu items',
  'Hinzufügen': 'Add',
  'Alle Menüpunkte hinzugefügt': 'All menu items added',
  'Mehr': 'More',

  // --- RecentlyEditedWidget.vue ---
  'Noch keine Bearbeitungen': 'No edits yet',
  '(kein Name)': '(no name)',

  // --- StatsOverviewWidget.vue ---
  'Produkte': 'Products',
  'Hierarchien': 'Hierarchies',
  'Attribute': 'Attributes',
  'Medien': 'Media',
  'aktiv': 'active',

  // --- TeamWorkloadWidget.vue ---
  'Team-Auslastung': 'Team Workload',
  '{count} offene Aufgaben': '{count} open tasks',
  'offene Aufgaben gesamt': 'open tasks total',

  // --- TranslationStatusWidget.vue ---
  'Übersetzungs-Status': 'Translation Status',
  'Keine Übersetzungsjobs': 'No translation jobs',
  'Wartend': 'Pending',
  'Abgeschlossen': 'Completed',
  'Fehlgeschlagen': 'Failed',
  'Alle Jobs': 'All Jobs',

  // --- WatchlistCompletenessWidget.vue / WatchlistDataQualityWidget.vue ---
  'Mein Füllstand (Merkliste)': 'My Completeness (Watchlist)',
  'Lege Produkte auf die Merkliste, um deinen Arbeitsfortschritt zu sehen.': 'Add products to the watchlist to see your work progress.',
  'Lege Produkte auf die Merkliste, um deine Datenqualität zu sehen.': 'Add products to the watchlist to see your data quality.',
  'Produkt(e) zu bearbeiten': 'product(s) to edit',
  'Alles erledigt — keine offenen Produkte. 🎉': 'All done — no open products. 🎉',
  'Produkt': 'Product',
  '… und {count} weitere': '… and {count} more',

  // --- WatchlistWidget.vue ---
  'Keine Produkte auf der Merkliste': 'No products on the watchlist',
  'gerade': 'just now',
  'vor {count} T.': '{count}d ago',
  'Export': 'Export',
  'Excel (.xlsx)': 'Excel (.xlsx)',
  'PDF': 'PDF',
  'PDF je Produkt (ZIP)': 'PDF per product (ZIP)',
  'Weitere Exporte …': 'More exports …',
  'Alle {count} anzeigen': 'Show all {count}',

  // --- WelcomeWidget.vue ---
  'Guten Morgen': 'Good morning',
  'Guten Tag': 'Good afternoon',
  'Guten Abend': 'Good evening',
  '{count} offene Aufgaben': '{count} open tasks',
  '{count} Produkte bearbeitet diese Woche': '{count} products edited this week',
  'Produkt anlegen': 'Create Product',
  'Import starten': 'Start Import',
  'Reports': 'Reports',
  'Katalog-Vorschau': 'Catalog Preview',

  // --- WorkflowStatusWidget.vue ---
  'Keine aktiven Workflows': 'No active workflows',
  'Aktiv': 'Active',
  'Bearbeitung': 'Editing',
  'Freigegeben': 'Approved',

  // --- GaugeChart.vue ---
  'Wert': 'Value',
  'Verbleibend': 'Remaining',

  // --- Wiederverwendet, aber mit abweichender Bedeutung als bestehende Keys ---
  'Entwurf': 'Draft',
  'Inaktiv': 'Inactive',
  'Eingestellt': 'Discontinued',

  // --- ProductNotesTab.vue (Produkteditor, gleiche relative-Zeit-Duplizierung) ---
  // 'vor {count}m'/'vor {count}h'/'vor {count}d'/'gerade eben' bereits oben definiert.
};

const de = Object.fromEntries(Object.keys(en).map((key) => [key, key]));

export const dashboardRawText = { de, en };
